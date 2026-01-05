<?php
// api/partidas.php
header("Content-Type: application/json; charset=UTF-8");
session_start();
require_once '../config/db.php';
require_once '../helpers/logger.php'; 

$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $input = $_POST;
}

$action = $input['action'] ?? $_GET['action'] ?? '';
$public_actions = ['info_proyector', 'estado_juego', 'ver_jugadores', 'ranking_parcial']; 

if (!in_array($action, $public_actions)) {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 2, 3, 4, 5, 6])) {
        http_response_code(403); echo json_encode(['error' => 'No autorizado']); exit;
    }
}

$db = (new Database())->getConnection();
$uid = $_SESSION['user_id'] ?? 0;
$urole = $_SESSION['user_role'] ?? 0;

// --- FUNCIÓN DE OPTIMIZACIÓN (JSON CACHE) ---
function actualizarFicheroEstado($db, $idPartida) {
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
            // FORZAMOS EL CASTEO AQUÍ PARA QUE EL ALUMNO SIEMPRE TENGA EL TIEMPO CORRECTO
            $data['tiempo_limite'] = (int)($data['tiempo_limite'] ?? 0);
            $data['tiempo_restante'] = 0;
            
            if ($data['estado_pregunta'] === 'respondiendo' && $data['tiempo_inicio_pregunta']) {
                $inicio = new DateTime($data['tiempo_inicio_pregunta']);
                $ahora = new DateTime();
                $diff = $ahora->getTimestamp() - $inicio->getTimestamp();
                $restante = $data['tiempo_limite'] - $diff;
                $data['tiempo_restante'] = $restante > 0 ? (int)$restante : 0;
            }
            if ($data['estado_pregunta'] === 'intro') { $data['json_opciones'] = null; }

            $path = "../temp/partida_" . $data['id_partida'] . ".json";
            file_put_contents($path, json_encode(['success' => true, 'data' => $data]));
        }
    } catch (Exception $e) {
        error_log("Error escribiendo JSON partida $idPartida: " . $e->getMessage());
    }
}

try {
    switch ($action) {
        case 'crear':
            crearPartida($db, ($urole == 1 && !empty($input['target_user_id'])) ? $input['target_user_id'] : $uid, $input);
            break;
        case 'listar':
            listarPartidas($db, $uid, $urole);
            break;
        case 'borrar':
            borrarPartida($db, $uid, $urole, $input['id_partida']);
            break;
        case 'ver_jugadores':
            verJugadores($db, $input['id_partida'] ?? $_GET['id_partida']);
            break;
        case 'info_proyector':
            getInfoProyector($db, $input['codigo_pin'] ?? $_GET['codigo_pin']);
            break;
        case 'estado_juego':
            obtenerEstadoJuego($db, $input['codigo_pin'] ?? $_GET['codigo_pin']);
            break;
        case 'abrir_sala':
            cambiarEstadoPartida($db, $uid, $urole, $input['id_partida'], 'sala_espera');
            actualizarFicheroEstado($db, $input['id_partida']);
            break;
        case 'iniciar_juego':
            iniciarJuego($db, $uid, $urole, $input['id_partida']);
            actualizarFicheroEstado($db, $input['id_partida']);
            break;
        case 'siguiente_fase':
            avanzarFase($db, $uid, $input['id_partida']);
            actualizarFicheroEstado($db, $input['id_partida']);
            break;
        case 'finalizar':
            cambiarEstadoPartida($db, $uid, $urole, $input['id_partida'], 'finalizada');
            actualizarFicheroEstado($db, $input['id_partida']);
            break;
        case 'expulsar_jugador':
            expulsarJugador($db, $uid, $input['id_sesion']);
            break;
        case 'ranking_parcial':
            getRankingParcial($db, $input['id_partida'] ?? $_GET['id_partida']);
            break;
        case 'get_stats_pregunta':
            $id_partida = $_GET['id_partida'] ?? 0;
            // Obtenemos la pregunta actual de la partida
            $stmtP = $db->prepare("SELECT id_pregunta_actual FROM partidas WHERE id_partida = ?");
            $stmtP->execute([$id_partida]);
            $idPregunta = $stmtP->fetchColumn();

            // Contamos respuestas reales extrayendo el índice del JSON
            $stmt = $db->prepare("
                SELECT JSON_EXTRACT(respuesta_json, '$.indice') as indice, COUNT(*) as total 
                FROM respuestas_log 
                WHERE id_pregunta = ? AND id_sesion IN (SELECT id_sesion FROM jugadores_sesion WHERE id_partida = ?)
                GROUP BY indice");
            $stmt->execute([$idPregunta, $id_partida]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;

        default:
            throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// --- FUNCIONES ---

function generarPinUnico($db) {
    do {
        $pin = mt_rand(100000, 999999);
        $stmt = $db->prepare("SELECT COUNT(*) FROM partidas WHERE codigo_pin = ? AND estado != 'finalizada'");
        $stmt->execute([$pin]);
    } while ($stmt->fetchColumn() > 0);
    return $pin;
}

function crearPartida($db, $anfitrionId, $data) {
    if (empty($data['nombre']) || empty($data['preguntas_ids'])) throw new Exception("Faltan datos.");
    
    $db->beginTransaction();
    try {
        $pin = generarPinUnico($db);
        $sql = "INSERT INTO partidas (codigo_pin, id_anfitrion, id_modo, nombre_partida, estado) VALUES (?, ?, ?, ?, 'creada')";
        $stmt = $db->prepare($sql);
        $stmt->execute([$pin, $anfitrionId, $data['id_modo'] ?? 1, $data['nombre']]); 
        $idPartida = $db->lastInsertId();
        
        $sqlPreg = "INSERT INTO partida_preguntas (id_partida, id_pregunta, orden) VALUES (?, ?, ?)";
        $stmtPreg = $db->prepare($sqlPreg);
        foreach ($data['preguntas_ids'] as $index => $idPregunta) {
            $stmtPreg->execute([$idPartida, $idPregunta, $index + 1]); 
        }
        $db->commit();
        Logger::registrar($db, $anfitrionId, 'INSERT', 'partidas', $idPartida, ['nombre' => $data['nombre']]);
        echo json_encode(['success' => true, 'pin' => $pin, 'id_partida' => $idPartida]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function listarPartidas($db, $uid, $role) {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    
    // NUEVO: Filtro por número de jugadores
    $minPlayers = (int)($_GET['min_players'] ?? 0);

    $sql = "SELECT p.*, m.nombre as nombre_modo, u.nombre as nombre_creador,
            (SELECT COUNT(*) FROM partida_preguntas WHERE id_partida = p.id_partida) as total_preguntas,
            (SELECT COUNT(*) FROM jugadores_sesion WHERE id_partida = p.id_partida AND avatar_id > 0) as total_jugadores
            FROM partidas p
            LEFT JOIN modos_juego m ON p.id_modo = m.id_modo
            LEFT JOIN usuarios u ON p.id_anfitrion = u.id_usuario
            WHERE 1=1";
            
    $params = [];

    // Filtro de Rol
    if ($role != 1) { 
        if ($role == 2) {
            // Academia: Sus partidas y las de sus profesores
            $sql .= " AND (p.id_anfitrion = ? OR p.id_anfitrion IN (SELECT id_usuario FROM usuarios WHERE id_padre = ?))";
            $params[] = $uid; $params[] = $uid;
        } elseif ($role == 6) {
            // Alumno: Solo partidas donde haya jugado (registrado en la sesión)
            $sql .= " AND p.id_partida IN (SELECT id_partida FROM jugadores_sesion WHERE id_usuario_registrado = ?)";
            $params[] = $uid;
        } else {
            // Profesores: Solo sus partidas creadas
            $sql .= " AND p.id_anfitrion = ?"; 
            $params[] = $uid;
        }
    }

    // Filtros Búsqueda
    if (!empty($search)) {
        $sql .= " AND (p.nombre_partida LIKE ? OR p.codigo_pin LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    // Filtro Estado
    if (!empty($status)) {
        if ($status === 'active') {
            $sql .= " AND p.estado IN ('sala_espera', 'jugando')";
        } else {
            $sql .= " AND p.estado = ?";
            $params[] = $status;
        }
    } else {
        // Por defecto no mostrar finalizadas.
        // EXCEPCIONES: Si es Super Admin (1), Alumno (6) o Academia (2), mostramos TODO el historial por defecto.
        if (empty($search) && empty($dateFrom) && empty($dateTo) && $minPlayers == 0 && $role != 1 && $role != 6 && $role != 2) {
            $sql .= " AND p.estado != 'finalizada'";
        }
    }

    // Filtros Fecha
    if (!empty($dateFrom)) {
        $sql .= " AND p.fecha_inicio >= ?";
        $params[] = $dateFrom . " 00:00:00";
    }
    if (!empty($dateTo)) {
        $sql .= " AND p.fecha_inicio <= ?";
        $params[] = $dateTo . " 23:59:59";
    }
    
    // NUEVO: HAVING para filtrar sobre el alias calculado
    if ($minPlayers > 0) {
        $sql .= " HAVING total_jugadores >= $minPlayers";
    }

    $sql .= " ORDER BY p.fecha_inicio DESC LIMIT 100";

    $stmt = $db->prepare($sql); 
    $stmt->execute($params); 
    
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function borrarPartida($db, $uid, $role, $idPartida) {
    $idPartida = (int)$idPartida;
    $sqlCheck = "SELECT id_anfitrion FROM partidas WHERE id_partida = ?";
    $stmt = $db->prepare($sqlCheck);
    $stmt->execute([$idPartida]);
    $owner = $stmt->fetchColumn();
    
    if (!$owner) { echo json_encode(['success' => true]); return; }
    if ($role != 1 && $owner != $uid) throw new Exception("No tienes permiso.");
    
    $db->prepare("DELETE FROM partidas WHERE id_partida = ?")->execute([$idPartida]);
    Logger::registrar($db, $uid, 'DELETE', 'partidas', $idPartida, null);
    @unlink("../temp/partida_" . $idPartida . ".json");
    echo json_encode(['success' => true]);
}

function verJugadores($db, $idPartida) {
    $sql = "SELECT id_sesion, nombre_nick, avatar_id, puntuacion 
            FROM jugadores_sesion 
            WHERE id_partida = ? AND avatar_id > 0 
            ORDER BY puntuacion DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$idPartida]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function cambiarEstadoPartida($db, $uid, $role, $idPartida, $estado) {
    $sqlVal = "SELECT id_anfitrion FROM partidas WHERE id_partida = ?";
    $stmtVal = $db->prepare($sqlVal);
    $stmtVal->execute([$idPartida]);
    $owner = $stmtVal->fetchColumn();
    
    if ($role != 1 && $owner != $uid) throw new Exception("No es tu partida.");
    $db->prepare("UPDATE partidas SET estado = ? WHERE id_partida = ?")->execute([$estado, $idPartida]);
    Logger::registrar($db, $uid, 'UPDATE', 'partidas', $idPartida, ['estado' => $estado]);
    echo json_encode(['success' => true, 'nuevo_estado' => $estado]);
}

function getInfoProyector($db, $pin) {
    $sql = "SELECT p.*, 
            (SELECT COUNT(*) FROM partida_preguntas WHERE id_partida = p.id_partida) as total_preguntas,
            u.nombre as nombre_profesor, u.foto_perfil as foto_profesor,
            academia.nombre as nombre_academia, academia.foto_perfil as logo_academia
            FROM partidas p
            JOIN usuarios u ON p.id_anfitrion = u.id_usuario
            LEFT JOIN usuarios academia ON u.id_padre = academia.id_usuario
            WHERE p.codigo_pin = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$pin]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($data) {
        if (empty($data['nombre_academia'])) { 
            $data['nombre_visual'] = $data['nombre_profesor']; 
            $data['logo_visual'] = $data['foto_profesor']; 
        } else { 
            $data['nombre_visual'] = $data['nombre_academia']; 
            $data['logo_visual'] = $data['logo_academia']; 
        }
        echo json_encode(['success' => true, 'data' => $data]);
    } else { echo json_encode(['success' => false, 'error' => 'Partida no encontrada']); }
}

function obtenerEstadoJuego($db, $pin) {
    $sql = "SELECT p.id_partida, p.estado, p.estado_pregunta, p.pregunta_actual_index, p.tiempo_inicio_pregunta,
                   (SELECT COUNT(*) FROM partida_preguntas WHERE id_partida = p.id_partida) as total_preguntas,
                   pr.texto as texto_pregunta, pr.json_opciones, pr.tiempo_limite, pr.tipo, pr.imagen,
                   (SELECT COUNT(*) FROM jugadores_sesion WHERE id_partida = p.id_partida AND avatar_id > 0) as total_jugadores,
                   (SELECT COUNT(*) FROM respuestas_log rl 
                    JOIN jugadores_sesion js ON rl.id_sesion = js.id_sesion 
                    WHERE js.id_partida = p.id_partida AND rl.id_pregunta = p.id_pregunta_actual) as respuestas_recibidas
            FROM partidas p
            LEFT JOIN preguntas pr ON p.id_pregunta_actual = pr.id_pregunta
            WHERE p.codigo_pin = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$pin]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($data) {
        $data['tiempo_limite'] = (int)($data['tiempo_limite'] ?? 0);
        // CALCULAMOS EL TIEMPO RESTANTE REAL DESDE EL SERVIDOR
        if ($data['estado_pregunta'] === 'respondiendo' && !empty($data['tiempo_inicio_pregunta'])) {
            $inicio = new DateTime($data['tiempo_inicio_pregunta']);
            $ahora = new DateTime();
            $diff = $ahora->getTimestamp() - $inicio->getTimestamp();
            $restante = $data['tiempo_limite'] - $diff;
            $data['tiempo_restante'] = $restante > 0 ? (int)$restante : 0;
        } else {
            $data['tiempo_restante'] = 0;
        }
    }

    echo json_encode(['success' => true, 'data' => $data]);
}

function iniciarJuego($db, $uid, $role, $idPartida) {
    $sqlPreg = "SELECT id_pregunta FROM partida_preguntas WHERE id_partida = ? AND orden = 1";
    $stmt = $db->prepare($sqlPreg);
    $stmt->execute([$idPartida]);
    $primera = $stmt->fetchColumn();
    
    if (!$primera) throw new Exception("Sin preguntas.");
    
    $db->prepare("UPDATE partidas SET estado='jugando', pregunta_actual_index=1, id_pregunta_actual=?, estado_pregunta='intro', tiempo_inicio_pregunta=NULL WHERE id_partida=?")->execute([$primera, $idPartida]);
    Logger::registrar($db, $uid, 'UPDATE', 'partidas', $idPartida, ['action' => 'iniciar']);
    echo json_encode(['success' => true]);
}

function avanzarFase($db, $uid, $idPartida) {
    $stmt = $db->prepare("SELECT pregunta_actual_index, estado_pregunta, id_pregunta_actual FROM partidas WHERE id_partida = ?");
    $stmt->execute([$idPartida]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $fase = $actual['estado_pregunta'];
    $idx = (int)$actual['pregunta_actual_index'];
    
    $nuevaFase = '';
    $updateTime = false;
    $nuevoIdPregunta = null;
    $nuevoIdx = $idx;

    if ($fase === 'intro') {
        $nuevaFase = 'respondiendo';
        $updateTime = true; 
    } elseif ($fase === 'respondiendo') {
        $nuevaFase = 'resultados';
    } elseif ($fase === 'resultados') {
        $nuevoIdx = $idx + 1;
        $stmtP = $db->prepare("SELECT id_pregunta FROM partida_preguntas WHERE id_partida = ? AND orden = ?");
        $stmtP->execute([$idPartida, $nuevoIdx]);
        $nuevoIdPregunta = $stmtP->fetchColumn();
        
        if (!$nuevoIdPregunta) {
            $db->prepare("UPDATE partidas SET estado='finalizada' WHERE id_partida = ?")->execute([$idPartida]);
            actualizarFicheroEstado($db, $idPartida); 
            echo json_encode(['success' => true, 'estado' => 'finalizada']); 
            return;
        }
        $nuevaFase = 'intro';
    }

    // Construcción dinámica de la query para actualizar el ID de pregunta solo cuando cambia
    $sql = "UPDATE partidas SET estado_pregunta = ?, pregunta_actual_index = ?";
    $params = [$nuevaFase, $nuevoIdx];
    
    if ($nuevoIdPregunta) {
        $sql .= ", id_pregunta_actual = ?";
        $params[] = $nuevoIdPregunta;
    }
    
    if ($updateTime) { 
        $sql .= ", tiempo_inicio_pregunta = NOW()";
    } else {
        $sql .= ", tiempo_inicio_pregunta = NULL";
    }

    $sql .= " WHERE id_partida = ?";
    $params[] = $idPartida;

    $db->prepare($sql)->execute($params);
    echo json_encode(['success' => true, 'fase' => $nuevaFase]);
}

function expulsarJugador($db, $uid, $idSesion) {
    $sqlCheck = "SELECT p.id_anfitrion FROM jugadores_sesion js JOIN partidas p ON js.id_partida = p.id_partida WHERE js.id_sesion = ?";
    $stmt = $db->prepare($sqlCheck);
    $stmt->execute([$idSesion]);
    $anfitrion = $stmt->fetchColumn();

    if ($_SESSION['user_role'] != 1 && $anfitrion != $uid) {
        throw new Exception("No tienes permiso.");
    }
    $db->prepare("DELETE FROM jugadores_sesion WHERE id_sesion = ?")->execute([$idSesion]);
    echo json_encode(['success' => true]);
}

function getRankingParcial($db, $idPartida) {
    $sql = "SELECT nombre_nick, puntuacion, avatar_id, racha FROM jugadores_sesion WHERE id_partida = ? AND avatar_id > 0 ORDER BY puntuacion DESC LIMIT 5";
    $stmt = $db->prepare($sql);
    $stmt->execute([$idPartida]);
    echo json_encode(['success' => true, 'ranking' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}
?>