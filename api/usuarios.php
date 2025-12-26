<?php
// api/usuarios.php
error_reporting(0);
ini_set('display_errors', 0);
header("Content-Type: application/json; charset=UTF-8");
session_start();

require_once '../config/db.php';
require_once '../helpers/logger.php'; 
require '../vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Seguridad: Solo usuarios logueados
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); 
    echo json_encode(["error" => "No autorizado."]); 
    exit();
}

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$uid = $_SESSION['user_id'];
$urole = $_SESSION['user_role'];

// Determinar acción (soporte para JSON y FormData)
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents("php://input"), true);
if (!empty($input['action'])) $action = $input['action'];
if ($method === 'POST' && isset($_POST['action'])) $action = $_POST['action'];

try {
    switch ($method) {
        case 'GET':
            if ($action === 'download_template') handleDownloadUserTemplate();
            elseif (isset($_GET['id'])) getUsuario($db, $_GET['id']);
            elseif (isset($_GET['type']) && $_GET['type'] === 'academias') listarAcademias($db, $urole);
            else listarUsuarios($db, $uid, $urole);
            break;
        case 'POST':
            if ($action === 'validate_import') handleValidateUserImport();
            elseif ($action === 'execute_import') handleExecuteUserImport($db, $uid, $urole);
            elseif ($action === 'update_profile') actualizarPerfilPropio($db, $uid);
            else crearUsuario($db, $uid, $urole);
            break;
        case 'PUT':
            if(isset($input['action']) && $input['action'] === 'change_password') cambiarPassAdmin($db, $uid, $urole, $input);
            elseif(isset($input['action']) && $input['action'] === 'toggle_status') toggleStatusUsuario($db, $uid, $urole, $input);
            else actualizarUsuarioCRUD($db, $uid, $urole, $input);
            break;
        case 'DELETE': 
            eliminarUsuario($db, $uid, $urole); 
            break;
    }
} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(["status" => "error", "error" => $e->getMessage()]);
}

// ==========================================
//           FUNCIONES CRUD Y LISTADO
// ==========================================

function listarUsuarios($db, $uid, $urole) {
    $search = $_GET['global'] ?? '';
    $fRole = $_GET['f_rol'] ?? '';
    $fEstado = $_GET['f_estado'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    $specialFilter = $_GET['special_filter'] ?? ''; 
    
    // Paginación
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if ($limit <= 0) $limit = 1000000; // Sin límite práctico
    $offset = ($page - 1) * $limit;

    // Ordenación
    $sortCol = $_GET['sort'] ?? 'id_usuario';
    $sortOrder = $_GET['order'] ?? 'ASC';
    
    // Mapeo seguro de columnas
    $sortMap = [
        'id_usuario' => 'u.id_usuario',
        'nombre' => 'u.nombre',
        'id_rol' => 'u.id_rol',
        'fiscal' => 'df.nif', 
        'activo' => 'u.activo',
        'total_preguntas' => 'total_preguntas', // Alias subconsulta
        'promedio_puntos' => 'promedio_puntos'  // Alias subconsulta
    ];
    $orderBy = $sortMap[$sortCol] ?? 'u.id_usuario';

    $whereClause = " WHERE 1=1";
    $params = [];

    // 1. Lógica de Permisos (SuperAdmin vs Academia)
    if ($urole == 2) { 
        // La Academia solo ve a sus usuarios creados (id_padre = su ID)
        $whereClause .= " AND u.id_padre = ?"; 
        $params[] = $uid; 
    } elseif ($urole != 1) { 
        // Otros roles no pueden listar usuarios (seguridad extra)
        echo json_encode(["data" => [], "total" => 0]); return; 
    }

    // 2. Filtros Estándar
    if (!empty($search)) {
        $whereClause .= " AND (u.nombre LIKE ? OR u.correo LIKE ? OR df.nif LIKE ?)";
        $term = "%$search%";
        $params[] = $term; $params[] = $term; $params[] = $term;
    }
    if (!empty($fRole)) {
        $whereClause .= " AND u.id_rol = ?";
        $params[] = $fRole;
    }
    if ($fEstado !== '') {
        $whereClause .= " AND u.activo = ?";
        $params[] = $fEstado;
    }
    if (!empty($dateFrom)) {
        $whereClause .= " AND u.creado_en >= ?";
        $params[] = $dateFrom . " 00:00:00";
    }
    if (!empty($dateTo)) {
        $whereClause .= " AND u.creado_en <= ?";
        $params[] = $dateTo . " 23:59:59";
    }

    // 3. Filtros Especiales (Accesos Directos)
    if ($specialFilter === 'active_teachers') {
        // Profesores (3,4) y Editores (5) que están activos
        $whereClause .= " AND u.id_rol IN (3,4,5) AND u.activo = 1";
    }
    elseif ($specialFilter === 'inactive_teachers') {
        // Profesores/Editores que NO tienen actividad en auditoría en los últimos 30 días
        $whereClause .= " AND u.id_rol IN (3,4,5) AND NOT EXISTS (
            SELECT 1 FROM auditoria a 
            WHERE a.id_usuario = u.id_usuario 
            AND a.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        )";
    }
    elseif ($specialFilter === 'risk_students') {
        // Alumnos (Rol 6). Se ordenarán por nota ascendente mediante $orderBy
        $whereClause .= " AND u.id_rol = 6";
    }
    elseif ($specialFilter === 'top_creators') {
        // Roles que pueden crear contenido. Se ordenarán por total_preguntas DESC
        $whereClause .= " AND u.id_rol IN (2,3,4,5)";
    }
    
    // Filtros exclusivos Superadmin
    elseif ($specialFilter === 'new_academies' && $urole == 1) {
        $whereClause .= " AND u.id_rol = 2";
    }
    elseif ($specialFilter === 'ghost_users' && $urole == 1) {
        $whereClause .= " AND u.activo = 1"; 
    }

    // 4. Ejecutar Consultas
    
    // Total de registros para paginación
    $sqlCount = "SELECT COUNT(*) as total FROM usuarios u LEFT JOIN datos_fiscales df ON u.id_usuario = df.id_usuario" . $whereClause;
    $stmtCount = $db->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    // Obtener datos con métricas calculadas
    $sqlData = "SELECT u.id_usuario, u.nombre, u.correo, u.activo, u.foto_perfil, u.id_rol, u.id_padre, u.creado_en,
                       r.nombre as nombre_rol, 
                       df.razon_social, df.nif, df.telefono,
                       (SELECT COUNT(*) FROM preguntas WHERE id_propietario = u.id_usuario) as total_preguntas,
                       (SELECT AVG(puntuacion) FROM jugadores_sesion WHERE id_usuario_registrado = u.id_usuario) as promedio_puntos
                FROM usuarios u
                JOIN roles r ON u.id_rol = r.id_rol
                LEFT JOIN datos_fiscales df ON u.id_usuario = df.id_usuario
                $whereClause
                ORDER BY $orderBy $sortOrder
                LIMIT $limit OFFSET $offset";

    $stmt = $db->prepare($sqlData);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear números para el frontend
    foreach ($rows as &$row) {
        $row['promedio_puntos'] = $row['promedio_puntos'] === null ? 0 : round($row['promedio_puntos'], 2);
        $row['total_preguntas'] = (int)$row['total_preguntas'];
    }

    echo json_encode(['data' => $rows, 'total' => (int)$total, 'page' => $page, 'limit' => $limit]);
}

function getUsuario($db, $id) {
    $stmt = $db->prepare("SELECT u.*, df.razon_social, df.nombre_negocio, df.nif, df.roi, df.telefono, df.direccion, df.direccion_numero, df.cp, df.id_pais, df.id_provincia, df.id_ciudad FROM usuarios u LEFT JOIN datos_fiscales df ON u.id_usuario = df.id_usuario WHERE u.id_usuario = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if($data) unset($data['contrasena']);
    echo json_encode($data ?: ["error" => "No encontrado"]);
}

function crearUsuario($db, $creatorId, $creatorRole) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Validación básica
    if (empty($data['nombre']) || empty($data['correo']) || empty($data['contrasena'])) { 
        echo json_encode(["error" => "Datos incompletos"]); return; 
    }

    // Verificar duplicados
    $check = $db->prepare("SELECT id_usuario FROM usuarios WHERE correo = ?");
    $check->execute([$data['correo']]);
    if($check->fetch()) { echo json_encode(["error" => "Correo duplicado"]); return; }

    $db->beginTransaction();
    $rol = $data['rol'];
    
    // Asignación de padre
    if ($creatorRole != 1) {
        // Si no es Superadmin, no puede crear otros Admins ni Academias
        if ($rol == 1 || $rol == 2) { 
            $db->rollBack(); echo json_encode(["error" => "No autorizado"]); return; 
        }
        $padre = $creatorId; // El padre es quien lo crea
    } else {
        $padre = $data['id_padre'] ?? null;
    }
    
    // Insertar Usuario
    $stmt = $db->prepare("INSERT INTO usuarios (nombre, correo, contrasena, id_rol, id_padre, activo, idioma_pref) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['nombre'], 
        $data['correo'], 
        password_hash($data['contrasena'], PASSWORD_DEFAULT), 
        $rol, 
        $padre, 
        $data['activo']??1, 
        $data['idioma_pref']??'es'
    ]);
    $newId = $db->lastInsertId();

    // Insertar Datos Fiscales
    $sqlF = "INSERT INTO datos_fiscales (id_usuario, razon_social, nombre_negocio, nif, roi, telefono, direccion, direccion_numero, cp, id_pais, id_provincia, id_ciudad) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $db->prepare($sqlF)->execute([
        $newId, 
        $data['razon_social']??'', $data['nombre_negocio']??'', 
        strtoupper($data['nif']??''), strtoupper($data['roi']??''), 
        $data['telefono']??'', $data['direccion']??'', 
        $data['direccion_numero']??'', $data['cp']??'', 
        $data['id_pais']??'ES', 
        !empty($data['id_provincia'])?$data['id_provincia']:null, 
        !empty($data['id_ciudad'])?$data['id_ciudad']:null
    ]);

    $db->commit();
    Logger::registrar($db, $creatorId, 'INSERT', 'usuarios', $newId, ['nombre' => $data['nombre']]);
    echo json_encode(["success" => true]);
}

function actualizarUsuarioCRUD($db, $editorId, $editorRole, $data) {
    $id = $data['id_usuario'];
    if (!$id) throw new Exception("ID inválido");

    // Seguridad: Si no es SuperAdmin, verificar que el usuario a editar es "hijo" del editor
    if ($editorRole != 1) {
        $stmt = $db->prepare("SELECT id_padre FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id]);
        if($stmt->fetchColumn() != $editorId) throw new Exception("No autorizado");
    }

    $db->beginTransaction();
    
    // Update tabla usuarios
    $db->prepare("UPDATE usuarios SET nombre=?, correo=?, id_rol=?, activo=?, idioma_pref=? WHERE id_usuario=?")
       ->execute([$data['nombre'], $data['correo'], $data['rol'], $data['activo'], $data['idioma_pref']??'es', $id]);

    // Update/Insert tabla datos_fiscales
    $sqlF = "INSERT INTO datos_fiscales (id_usuario, razon_social, nombre_negocio, nif, roi, telefono, direccion, direccion_numero, cp, id_pais, id_provincia, id_ciudad) 
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?) 
             ON DUPLICATE KEY UPDATE 
             razon_social=VALUES(razon_social), nombre_negocio=VALUES(nombre_negocio), 
             nif=VALUES(nif), roi=VALUES(roi), telefono=VALUES(telefono), 
             direccion=VALUES(direccion), direccion_numero=VALUES(direccion_numero), 
             cp=VALUES(cp), id_pais=VALUES(id_pais), id_provincia=VALUES(id_provincia), id_ciudad=VALUES(id_ciudad)";
    
    $db->prepare($sqlF)->execute([
        $id, 
        $data['razon_social']??'', $data['nombre_negocio']??'', 
        strtoupper($data['nif']??''), strtoupper($data['roi']??''), 
        $data['telefono']??'', $data['direccion']??'', 
        $data['direccion_numero']??'', $data['cp']??'', 
        $data['id_pais']??'ES', 
        !empty($data['id_provincia'])?$data['id_provincia']:null, 
        !empty($data['id_ciudad'])?$data['id_ciudad']:null
    ]);

    $db->commit();
    Logger::registrar($db, $editorId, 'UPDATE', 'usuarios', $id, ['nombre' => $data['nombre']]);
    echo json_encode(["success" => true]);
}

function toggleStatusUsuario($db, $uid, $urole, $data) {
    $id = $data['id_usuario'];
    if ($urole != 1) {
        $stmt = $db->prepare("SELECT id_padre FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id]);
        if($stmt->fetchColumn() != $uid) throw new Exception("No autorizado");
    }
    $db->prepare("UPDATE usuarios SET activo = ? WHERE id_usuario = ?")->execute([$data['nuevo_estado'], $id]);
    Logger::registrar($db, $uid, 'UPDATE', 'usuarios', $id, ['accion' => 'toggle_status', 'estado' => $data['nuevo_estado']]);
    echo json_encode(["success" => true]);
}

function actualizarPerfilPropio($db, $uid) {
    $nombre = $_POST['nombre'] ?? '';
    $idioma = $_POST['idioma_pref'] ?? 'es';
    $tema   = $_POST['tema_pref'] ?? null;
    $nick   = $_POST['nick'] ?? null;
    $avatar_id = $_POST['avatar_id'] ?? 1;
    $fotoPath = null;
    
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === 0) {
        $ext = pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION);
        $path = "../assets/uploads/" . $uid . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $path)) $fotoPath = str_replace("../", "", $path);
    }

    $db->beginTransaction();
    $updates = ["nombre = ?", "idioma_pref = ?"];
    $params = [$nombre, $idioma];

    if ($nick !== null) { $updates[] = "nick = ?"; $params[] = $nick; }
    $updates[] = "avatar_id = ?"; $params[] = (int)$avatar_id;

    if ($fotoPath) { $updates[] = "foto_perfil = ?"; $params[] = $fotoPath; }
    if ($tema) { $updates[] = "tema_pref = ?"; $params[] = $tema; }
    if (!empty($_POST['new_password'])) {
        $updates[] = "contrasena = ?";
        $params[] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    }
    $params[] = $uid;
    $db->prepare("UPDATE usuarios SET " . implode(", ", $updates) . " WHERE id_usuario = ?")->execute($params);

    // Actualizar fiscales también desde perfil
    $sqlF = "INSERT INTO datos_fiscales (id_usuario, razon_social, nombre_negocio, nif, roi, telefono, direccion, direccion_numero, cp, id_pais, id_provincia, id_ciudad) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE razon_social=VALUES(razon_social), nombre_negocio=VALUES(nombre_negocio), nif=VALUES(nif), roi=VALUES(roi), telefono=VALUES(telefono), direccion=VALUES(direccion), direccion_numero=VALUES(direccion_numero), cp=VALUES(cp), id_pais=VALUES(id_pais), id_provincia=VALUES(id_provincia), id_ciudad=VALUES(id_ciudad)";
    $db->prepare($sqlF)->execute([
        $uid, 
        $_POST['razon_social']??'', $_POST['nombre_negocio']??'', 
        strtoupper($_POST['nif']??''), strtoupper($_POST['roi']??''), 
        $_POST['telefono']??'', $_POST['direccion']??'', 
        $_POST['direccion_numero']??'', $_POST['cp']??'', 
        $_POST['id_pais']??'ES', 
        !empty($_POST['id_provincia'])?$_POST['id_provincia']:null, 
        !empty($_POST['id_ciudad'])?$_POST['id_ciudad']:null
    ]);

    $_SESSION['user_name'] = $nombre;
    $_SESSION['lang'] = $idioma;
    if($tema) $_SESSION['tema_pref'] = $tema;
    if($fotoPath) $_SESSION['user_photo'] = $fotoPath;
    $db->commit();
    echo json_encode(["success" => true, "foto" => $fotoPath]);
}

function cambiarPassAdmin($db, $uid, $urole, $data) {
    if ($urole != 1) {
        $stmt = $db->prepare("SELECT id_padre FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$data['id_usuario']]);
        if ($stmt->fetchColumn() != $uid) throw new Exception("No autorizado");
    }
    $db->prepare("UPDATE usuarios SET contrasena = ? WHERE id_usuario = ?")->execute([password_hash($data['new_password'], PASSWORD_DEFAULT), $data['id_usuario']]);
    Logger::registrar($db, $uid, 'UPDATE', 'usuarios', $data['id_usuario'], ['accion' => 'admin_change_pass']);
    echo json_encode(["success" => true]);
}

function eliminarUsuario($db, $uid, $urole) {
    $id = json_decode(file_get_contents('php://input'), true)['id'];
    if($id == $uid) throw new Exception("No puedes borrarte a ti mismo");
    
    if ($urole != 1) {
        $stmt = $db->prepare("SELECT id_padre FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() != $uid) throw new Exception("No autorizado");
    }
    
    try {
        // Ejecutar borrado (corregida la variable $id)
        $db->prepare("DELETE FROM usuarios WHERE id_usuario = ?")->execute([$id]);
        Logger::registrar($db, $uid, 'DELETE', 'usuarios', $id);
        echo json_encode(["success" => true]);
    } catch (PDOException $e) {
        // Si el error es de integridad (registros asociados)
        if ($e->getCode() == '23000') {
            echo json_encode([
                "success" => false, 
                "error" => "El usuario no puede eliminarse porque tiene partidas o preguntas asociadas."
            ]);
        } else {
            // Otros errores de base de datos
            echo json_encode(["success" => false, "error" => "Error de base de datos: " . $e->getMessage()]);
        }
    }
}

function listarAcademias($db, $urole) {
    echo json_encode(["data" => $db->query("SELECT id_usuario, nombre FROM usuarios WHERE id_rol = 2 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC)]);
}

// ==========================================
//          FUNCIONES DE IMPORTACIÓN
// ==========================================

function handleDownloadUserTemplate() {
    // Genera un CSV de ejemplo
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=plantilla_usuarios.csv');
    $out = fopen('php://output', 'w');
    // BOM para Excel
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); 
    fputcsv($out, ['nombre', 'correo', 'contrasena', 'rol_id', 'telefono', 'nif', 'razon_social', 'direccion', 'cp', 'pais_codigo'], ';', '"', '\\');
    fputcsv($out, ['EJEMPLO: Juan Alumno', 'alumno@ejemplo.com', '123456', '6', '600123456', '12345678Z', 'Juan S.L.', 'Calle Falsa 123', '28001', 'ES'], ';', '"', '\\');
    fputcsv($out, ['INFO ROLES: 1=Superadmin, 2=Academia, 3=Profesor Plantilla, 4=Profesor Indep, 5=Editor, 6=Alumno. PAIS=Codigo ISO 2 letras (ES, FR...)', '', '', '', '', '', '', '', '', ''], ';', '"', '\\');
    fclose($out);
    exit;
}

function handleValidateUserImport() {
    if (empty($_FILES['archivo'])) { echo json_encode(['status'=>'error', 'mensaje'=>'No se recibió archivo']); return; }
    
    $file = $_FILES['archivo']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    
    // Soporte Excel (xls, xlsx, ods) usando PhpSpreadsheet
    if (in_array($ext, ['xls', 'xlsx', 'ods'])) {
        try {
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $headers = [];
            // Leer solo la primera fila
            foreach ($sheet->getRowIterator(1, 1) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $val = $cell->getValue();
                    if ($val !== null && $val !== '') $headers[] = (string)$val;
                }
            }
            // Devolvemos headers para que el usuario haga el mapeo
            echo json_encode(['status' => 'need_mapping', 'headers' => $headers]);
            return;
        } catch (Exception $e) {
            echo json_encode(['status'=>'error', 'mensaje'=>'Error leyendo archivo: ' . $e->getMessage()]); return;
        }
    } 
    
    // Soporte CSV
    $fh = fopen($file, 'r');
    $line = fgets($fh);
    $delim = (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';
    rewind($fh);
    $header = fgetcsv($fh, 0, $delim, '"', '\\');
    
    if(!$header) { echo json_encode(['status'=>'error', 'mensaje'=>'CSV inválido o vacío']); return; }
    
    // Intenta mapeo automático
    $map = getUserCsvMap($header);
    if (!isset($map['nombre']) || !isset($map['correo'])) {
        // Si fallan columnas clave, pedimos mapeo manual al frontend
        echo json_encode(['status' => 'need_mapping', 'headers' => $header]);
        return;
    }
    
    // Contar filas
    $count = 0; while(fgetcsv($fh, 0, $delim, '"', '\\')) $count++;
    echo json_encode(['status'=>'ok', 'filas_validas'=>$count]);
}

function validateNIF($nif) {
    $nif = strtoupper(trim($nif));
    if (empty($nif)) return false;
    // Regex básica ES
    if (!preg_match('/^[0-9XYZ][0-9]{7}[TRWAGMYFPDXBNJZSQVHLCKE]$/', $nif)) return false;
    $validChars = 'TRWAGMYFPDXBNJZSQVHLCKE';
    $nie = str_replace(['X','Y','Z'], ['0','1','2'], $nif);
    $numbers = substr($nie, 0, 8);
    $letter = substr($nie, -1);
    $calcIndex = intval($numbers) % 23;
    return $validChars[$calcIndex] === $letter;
}

function handleExecuteUserImport($db, $uid, $urole) {
    $file = $_FILES['archivo']['tmp_name'];
    $mappingJSON = $_POST['mapping'] ?? null;
    $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    
    $inserted = 0; $skipped = 0;
    
    $stmtCheck = $db->prepare("SELECT id_usuario FROM usuarios WHERE correo = ?");
    $stmtIns = $db->prepare("INSERT INTO usuarios (nombre, correo, contrasena, id_rol, id_padre, activo, idioma_pref) VALUES (?, ?, ?, ?, ?, 1, 'es')");
    $stmtFis = $db->prepare("INSERT INTO datos_fiscales (id_usuario, telefono, nif, razon_social, direccion, cp, id_pais) VALUES (?, ?, ?, ?, ?, ?, ?)");

    // Si quien importa es Academia, los usuarios son sus hijos
    $idPadre = ($urole == 2) ? $uid : null;

    // Función auxiliar para procesar cada fila
    $processRow = function($nombre, $correo, $pass, $rolRaw, $tel, $nif, $razon, $dir, $cp, $pais) use ($db, $stmtCheck, $stmtIns, $stmtFis, $idPadre, &$inserted, &$skipped) {
        if(empty($nombre) || empty($correo) || stripos($nombre, 'EJEMPLO') !== false || stripos($nombre, 'INFO') !== false) return;
        
        // Check duplicado email
        $stmtCheck->execute([$correo]);
        if($stmtCheck->fetch()) { $skipped++; return; }

        // Validar NIF si es España
        if ($pais === 'ES' && !validateNIF($nif)) { $skipped++; return; }

        // Parseo de rol (flexible)
        $idRol = 6; // Por defecto Alumno
        $r = strtolower(trim($rolRaw));
        if ($r == '1' || strpos($r, 'admin') !== false) $idRol = 1;
        elseif ($r == '2' || strpos($r, 'acad') !== false) $idRol = 2;
        elseif ($r == '3' || strpos($r, 'profe') !== false) $idRol = 3;
        elseif ($r == '4' || strpos($r, 'indep') !== false) $idRol = 4;
        elseif ($r == '5' || strpos($r, 'edit') !== false) $idRol = 5;
        
        if (empty($pass)) $pass = '123456';
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        try {
            $stmtIns->execute([$nombre, $correo, $hash, $idRol, $idPadre]);
            $newId = $db->lastInsertId();
            
            $stmtFis->execute([$newId, $tel, strtoupper($nif), $razon, $dir, $cp, strtoupper($pais) ?: 'ES']);
            $inserted++;
        } catch (Exception $e) {
            $skipped++;
        }
    };

    // PROCESAMIENTO EXCEL
    if ($mappingJSON && in_array($ext, ['xls', 'xlsx', 'ods'])) {
        $map = json_decode($mappingJSON, true);
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        
        for ($rowIdx = 2; $rowIdx <= $highestRow; $rowIdx++) {
            $getVal = function($key) use ($map, $sheet, $rowIdx) {
                if (!isset($map[$key])) return '';
                $colString = Coordinate::stringFromColumnIndex($map[$key] + 1);
                return trim($sheet->getCell($colString . $rowIdx)->getValue() ?? '');
            };
            
            $processRow(
                $getVal('nombre'), $getVal('correo'), $getVal('contrasena'), $getVal('rol'), 
                $getVal('telefono'), $getVal('nif'), $getVal('razon_social'), 
                $getVal('direccion'), $getVal('cp'), $getVal('pais')
            );
        }
    } 
    // PROCESAMIENTO CSV
    else {
        $fh = fopen($file, 'r');
        $line = fgets($fh);
        $delim = (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';
        rewind($fh);
        $header = fgetcsv($fh, 0, $delim, '"', '\\');
        
        // Si viene mapping del frontend lo usamos, si no intentamos deducir
        $map = $mappingJSON ? json_decode($mappingJSON, true) : getUserCsvMap($header);
        
        while (($row = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
            // Función helper para sacar valor seguro del array
            $v = function($k) use ($row, $map) { return isset($map[$k]) && isset($row[$map[$k]]) ? trim($row[$map[$k]]) : ''; };
            
            $processRow(
                $v('nombre'), $v('correo'), $v('contrasena'), $v('rol'), 
                $v('telefono'), $v('nif'), $v('razon_social'), 
                $v('direccion'), $v('cp'), $v('pais')
            );
        }
    }

    Logger::registrar($db, $uid, 'IMPORT', 'usuarios', null, ['inserted' => $inserted, 'skipped' => $skipped]);
    echo json_encode(['status'=>'ok', 'insertados'=>$inserted, 'saltados'=>$skipped, 'mensaje'=>"$inserted usuarios creados. $skipped omitidos."]);
}

function getUserCsvMap($header) {
    $map = [];
    foreach ($header as $i => $col) {
        $k = strtolower(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $col)));
        // Mapa flexible de columnas
        if (strpos($k, 'nombre') !== false || strpos($k, 'name') !== false) $map['nombre'] = $i;
        if (strpos($k, 'corr') !== false || strpos($k, 'email') !== false) $map['correo'] = $i;
        if (strpos($k, 'pass') !== false || strpos($k, 'contra') !== false) $map['contrasena'] = $i;
        if (strpos($k, 'rol') !== false || strpos($k, 'type') !== false) $map['rol'] = $i;
        if (strpos($k, 'tel') !== false || strpos($k, 'phone') !== false) $map['telefono'] = $i;
        if (strpos($k, 'nif') !== false || strpos($k, 'dni') !== false) $map['nif'] = $i;
        if (strpos($k, 'razon') !== false || strpos($k, 'social') !== false) $map['razon_social'] = $i;
        if (strpos($k, 'direcc') !== false || strpos($k, 'address') !== false) $map['direccion'] = $i;
        if (strpos($k, 'cp') !== false || strpos($k, 'postal') !== false) $map['cp'] = $i;
        if (strpos($k, 'pais') !== false || strpos($k, 'country') !== false) $map['pais'] = $i;
    }
    return $map;
}
?>