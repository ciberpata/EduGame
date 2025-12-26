<?php
// api/reset_handler.php
header("Content-Type: application/json; charset=UTF-8");
include_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(); }

$data = json_decode(file_get_contents("php://input"));
$token = $data->token ?? '';
$new_pass = $data->new_password ?? '';

if (empty($token) || strlen($new_pass) < 6) {
    echo json_encode(["error" => "Datos inválidos."]); exit();
}

$db = (new Database())->getConnection();

try {
    // 1. Validar Token y Expiración (Usando NOW() de MySQL para evitar líos de zona horaria PHP)
    $stmt = $db->prepare("SELECT id_usuario, contrasena FROM usuarios WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(["error" => "El enlace ha caducado o es inválido."]); exit();
    }

    // 2. Verificar que no sea igual a la anterior
    if (password_verify($new_pass, $user['contrasena'])) {
        echo json_encode(["error" => "La nueva contraseña debe ser diferente a la actual."]); exit();
    }

    // 3. Actualizar
    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
    // Limpiamos el token para que no se pueda reusar
    $update = $db->prepare("UPDATE usuarios SET contrasena = ?, reset_token = NULL, reset_expires = NULL WHERE id_usuario = ?");
    $update->execute([$new_hash, $user['id_usuario']]);

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error del servidor."]);
}
?>