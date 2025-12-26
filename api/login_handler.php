<?php
// api/login_handler.php
ob_start();

header("Content-Type: application/json; charset=UTF-8");
session_start();

include_once '../config/db.php';
include_once '../helpers/logger.php'; 

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    http_response_code(405); 
    ob_clean(); 
    echo json_encode(["error" => "Método no permitido"]); 
    exit();
}

$input = file_get_contents("php://input");
$data = json_decode($input);

$correo = $data->correo ?? '';
$contrasena = $data->contrasena ?? '';

$db = (new Database())->getConnection();
$userId = null; 
$status = 'FALLIDO';

try {
    // 1. Consulta actualizada para obtener foto_perfil
    $sql = "SELECT u.id_usuario, u.nombre, u.contrasena, u.id_rol, u.activo, u.idioma_pref, u.tema_pref, u.foto_perfil, r.nombre as nombre_rol 
            FROM usuarios u 
            JOIN roles r ON u.id_rol = r.id_rol 
            WHERE u.correo = :correo LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(':correo', $correo);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $userId = $user['id_usuario'] ?? null;

    if ($user && password_verify($contrasena, $user['contrasena'])) {
        
        if ($user['activo'] == 0) {
            $status = 'INACTIVO';
            http_response_code(403);
            ob_clean(); 
            echo json_encode(["error" => "Cuenta inactiva. Contacte al administrador."]);
            exit(); 
        }

        $status = 'EXITOSO';
        
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id_usuario'];
        $_SESSION['user_role'] = $user['id_rol'];
        $_SESSION['user_name'] = $user['nombre'];
        $_SESSION['rol_nombre'] = $user['nombre_rol']; 
        $_SESSION['lang'] = $user['idioma_pref'];
        $_SESSION['tema_pref'] = !empty($user['tema_pref']) ? $user['tema_pref'] : '210'; 
        
        // --- NUEVO: Guardar foto en sesión ---
        $_SESSION['user_photo'] = !empty($user['foto_perfil']) ? $user['foto_perfil'] : null;

        ob_clean(); 
        echo json_encode([
            "success" => true,
            "redirect" => "index.php",
            "user" => ["nombre" => $user['nombre'], "rol" => $user['nombre_rol']]
        ]);
        
    } else {
        $status = 'CREDENCIALES_INVALIDAS';
        http_response_code(401);
        ob_clean(); 
        echo json_encode(["error" => "Credenciales inválidas"]);
    }

} catch (Exception $e) {
    $status = 'ERROR_SERVER';
    http_response_code(500);
    error_log("Login error: " . $e->getMessage());
    ob_clean(); 
    echo json_encode(["error" => "Error del servidor."]);
} finally {
    $logUserId = $userId ?? 0; 
    $detalles = ($status !== 'EXITOSO') ? ["correo" => $correo, "status" => $status] : null;
    if (class_exists('Logger')) {
        Logger::registrar($db, $logUserId, 'LOGIN', 'sesion', $userId, $detalles);
    }
}
?>