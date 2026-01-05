<?php
// api/juego.php
header("Content-Type: application/json; charset=UTF-8");
session_start(); // NECESARIO para identificar al alumno logueado

require_once '../config/db.php';
require_once '../helpers/logger.php'; 

$db = (new Database())->getConnection();
$input = json_decode(file_get_contents("php://input"), true) ?? $_POST;
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'unirse': unirsePartida($db, $input); break;
        case 'seleccionar_avatar': seleccionarAvatar($db, $input); break;
        case 'responder': procesarRespuesta($db, $input); break;
        case 'estado_jugador': obtenerEstado($db, $input['id_sesion']); break;
        default: echo json_encode(['error' => 'Acción desconocida']);
    }
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
}

// --- FUNCIONES ---

function unirsePartida($db, $data) {
    $pin = $data['pin'] ?? '';
    // Aseguramos NULL si no está logueado para que no falle la FK de la base de datos
    $idUsuarioRegistrado = (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) ? $_SESSION['user_id'] : null;
    
    if ($idUsuarioRegistrado) {
        $stmtU = $db->prepare("SELECT nick, avatar_id FROM usuarios WHERE id_usuario = ?");
        $stmtU->execute([$idUsuarioRegistrado]);
        $uProfile = $stmtU->fetch(PDO::FETCH_ASSOC);
        $nick = !empty($uProfile['nick']) ? $uProfile['nick'] : trim($data['nick'] ?? '');
        $avatarId = ($uProfile['avatar_id'] > 0) ? $uProfile['avatar_id'] : 0;
    } else {
        $nick = trim($data['nick'] ?? '');
        $avatarId = 0; 
    }

    if (empty($nick)) throw new Exception("¡Ups! No puedes jugar sin un apodo.");
    
    $stmt = $db->prepare("SELECT id_partida, estado FROM partidas WHERE codigo_pin = ? AND estado IN ('sala_espera', 'creada')");
    $stmt->execute([$pin]);
    $partida = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$partida) throw new Exception("No encontramos esa partida o ya ha empezado. ¡Llegas tarde!");

    $stmtNick = $db->prepare("SELECT COUNT(*) FROM jugadores_sesion WHERE id_partida = ? AND nombre_nick = ?");
    $stmtNick->execute([$partida['id_partida'], $nick]);
    if ($stmtNick->fetchColumn() > 0) throw new Exception("Ese nombre ya está pillado. Sé más original.");

    try {
        $sql = "INSERT INTO jugadores_sesion (id_partida, nombre_nick, avatar_id, ip, id_usuario_registrado) VALUES (?, ?, ?, ?, ?)";
        $db->prepare($sql)->execute([$partida['id_partida'], $nick, $avatarId, $_SERVER['REMOTE_ADDR'], $idUsuarioRegistrado]);
        
        echo json_encode([
            'success' => true, 
            'id_sesion' => $db->lastInsertId(), 
            'id_partida' => $partida['id_partida'],
            'nick' => $nick,
            'has_avatar' => ($avatarId > 0)
        ]);
    } catch (PDOException $e) {
        // Mensaje comprensible para humanos ante errores de integridad o base de datos
        throw new Exception("Lo sentimos, hubo un problema al entrar en la partida. Por favor, inténtalo de nuevo.");
    }
}

function seleccionarAvatar($db, $data) {
    $idSesion = $data['id_sesion'];
    $avatarId = (int)$data['avatar_id'];
    if($avatarId <= 0) throw new Exception("Avatar inválido");
    $stmt = $db->prepare("UPDATE jugadores_sesion SET avatar_id = ? WHERE id_sesion = ?");
    $stmt->execute([$avatarId, $idSesion]);
    echo json_encode(['success' => true]);
}

function procesarRespuesta($db, $data) {
    $idSesion = $data['id_sesion'];
    $respuestaJson = json_encode($data['respuesta']); 
    
    $sql = "SELECT p.id_partida, p.tiempo_inicio_pregunta, p.estado_pregunta, p.id_anfitrion,
            pr.id_pregunta, pr.json_opciones, pr.tiempo_limite,
            js.nombre_nick
            FROM partidas p
            JOIN preguntas pr ON p.id_pregunta_actual = pr.id_pregunta
            JOIN jugadores_sesion js ON js.id_sesion = ? AND js.id_partida = p.id_partida
            WHERE p.id_partida = js.id_partida";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([$idSesion]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$info || $info['estado_pregunta'] !== 'respondiendo') {
        echo json_encode(['success' => false, 'error' => 'Tiempo agotado o pregunta cerrada']);
        return;
    }

    $opciones = json_decode($info['json_opciones'], true);
    $esCorrecta = false;
    $indiceResp = $data['respuesta']['indice'] ?? -1;
    if (isset($opciones[$indiceResp]) && $opciones[$indiceResp]['es_correcta']) {
        $esCorrecta = true;
    }

    $puntosGanados = 0;
    $stmtRacha = $db->prepare("SELECT racha FROM jugadores_sesion WHERE id_sesion = ?");
    $stmtRacha->execute([$idSesion]);
    $rachaActual = $stmtRacha->fetchColumn();

    if ($esCorrecta) {
        $inicio = new DateTime($info['tiempo_inicio_pregunta']);
        $ahora = new DateTime();
        $segundosTranscurridos = ($ahora->getTimestamp() - $inicio->getTimestamp()) + ($ahora->format('u') - $inicio->format('u')) / 1000000;
        if ($segundosTranscurridos < 0) $segundosTranscurridos = 0;
        
        $tLimite = (int)$info['tiempo_limite'];
        if ($segundosTranscurridos > $tLimite) $segundosTranscurridos = $tLimite;

        $factorTiempo = 1 - ($segundosTranscurridos / $tLimite);
        $puntosGanados = round(500 + (500 * $factorTiempo));
        
        $nuevaRacha = $rachaActual + 1;
        $bonusRacha = min(($nuevaRacha - 1) * 100, 500); 
        if ($nuevaRacha > 1) $puntosGanados += $bonusRacha;

        $db->prepare("UPDATE jugadores_sesion SET puntuacion = puntuacion + ?, racha = ? WHERE id_sesion = ?")
           ->execute([$puntosGanados, $nuevaRacha, $idSesion]);
    } else {
        $db->prepare("UPDATE jugadores_sesion SET racha = 0 WHERE id_sesion = ?")->execute([$idSesion]);
    }

    $db->prepare("INSERT INTO respuestas_log (id_sesion, id_pregunta, respuesta_json, es_correcta, tiempo_tardado) VALUES (?, ?, ?, ?, ?)")
       ->execute([$idSesion, $info['id_pregunta'], $respuestaJson, $esCorrecta ? 1 : 0, $segundosTranscurridos ?? 0]);

    echo json_encode(['success' => true, 'correcta' => $esCorrecta, 'puntos' => $puntosGanados]);
}

// Nueva función para forzar el avance del proyector
function actualizarFicheroCache($db, $idPartida) {
    try {
        $sql = "SELECT p.estado, p.estado_pregunta, p.pregunta_actual_index, p.tiempo_inicio_pregunta, p.id_partida, p.codigo_pin,
                       pr.texto as texto_pregunta, pr.json_opciones, pr.tipo, pr.tiempo_limite,
                       u.nombre as nombre_anfitrion
                FROM partidas p
                JOIN usuarios u ON p.id_anfitrion = u.id_usuario
                LEFT JOIN preguntas pr ON p.id_pregunta_actual = pr.id_pregunta
                WHERE p.id_partida = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$idPartida]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            // Sincronización de tiempo para la caché: calculamos el tiempo restante real
            $data['tiempo_limite'] = (int)($data['tiempo_limite'] ?? 0);
            if ($data['estado_pregunta'] === 'respondiendo' && !empty($data['tiempo_inicio_pregunta'])) {
                $inicio = new DateTime($data['tiempo_inicio_pregunta']);
                $ahora = new DateTime();
                $diff = $ahora->getTimestamp() - $inicio->getTimestamp();
                $restante = $data['tiempo_limite'] - $diff;
                $data['tiempo_restante'] = ($restante > 0) ? (int)$restante : 0;
            } else {
                $data['tiempo_restante'] = 0;
            }

            $path = "../temp/partida_" . $data['id_partida'] . ".json";
            file_put_contents($path, json_encode(['success' => true, 'data' => $data]), LOCK_EX);
        }
    } catch (Exception $e) {}
}

function obtenerEstado($db, $idSesion) {
    $sql = "SELECT p.estado, p.estado_pregunta, p.pregunta_actual_index, p.tiempo_inicio_pregunta, p.id_partida, p.codigo_pin,
                   js.puntuacion, js.racha, js.avatar_id,
                   pr.texto as texto_pregunta, pr.json_opciones, pr.tipo, pr.tiempo_limite,
                   u.nombre as nombre_anfitrion, u.foto_perfil as foto_anfitrion
            FROM jugadores_sesion js
            JOIN partidas p ON js.id_partida = p.id_partida
            JOIN usuarios u ON p.id_anfitrion = u.id_usuario
            LEFT JOIN preguntas pr ON p.id_pregunta_actual = pr.id_pregunta
            WHERE js.id_sesion = ?";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([$idSesion]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) { echo json_encode(['error' => 'Sesión no encontrada']); return; }
    
    // ASEGURAMOS QUE EL TIEMPO LÍMITE SEA SIEMPRE UN ENTERO PARA EL CONTADOR
    $data['tiempo_limite'] = (int)($data['tiempo_limite'] ?? 0);
    
    if ($data['estado_pregunta'] === 'respondiendo' && $data['tiempo_inicio_pregunta']) {
        $inicio = new DateTime($data['tiempo_inicio_pregunta']);
        $ahora = new DateTime();
        $diff = $ahora->getTimestamp() - $inicio->getTimestamp();
        $restante = $data['tiempo_limite'] - $diff;
        $data['tiempo_restante'] = $restante > 0 ? (int)$restante : 0;
    } else {
        $data['tiempo_restante'] = 0;
    }

    if ($data['estado'] === 'finalizada') {
        $stmtTop = $db->prepare("SELECT nombre_nick, puntuacion, avatar_id FROM jugadores_sesion WHERE id_partida = ? AND avatar_id > 0 ORDER BY puntuacion DESC LIMIT 3");
        $stmtTop->execute([$data['id_partida']]);
        $data['top_ranking'] = $stmtTop->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
}
?>