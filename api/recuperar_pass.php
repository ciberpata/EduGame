<?php
// api/recuperar_pass.php
header("Content-Type: application/json; charset=UTF-8");
session_start();

// Cargar configuración y BD
include_once '../config/db.php';
// Intentar cargar settings si existen, si no, array vacío
$settings = file_exists('../config/settings.php') ? require('../config/settings.php') : [];

// Intentar cargar PHPMailer si existe
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload_path)) require $autoload_path;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit();
}

$data = json_decode(file_get_contents("php://input"));
if (empty($data->correo)) {
    echo json_encode(["error" => "Email requerido"]); exit();
}

$db = (new Database())->getConnection();

// Función auxiliar de envío
function trySendEmail($config, $to, $name, $subject, $body) {
    if (!class_exists(PHPMailer::class) || empty($config)) return false;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['secure'];
        $mail->Port = $config['port'];
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to, $name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        return true;
    } catch (Exception $e) { 
        return false; // Falló el envío real
    }
}

try {
    // 1. Verificar si el usuario existe
    $stmt = $db->prepare("SELECT id_usuario, nombre FROM usuarios WHERE correo = ?");
    $stmt->execute([$data->correo]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // 2. Generar Token y Expiración (1 hora)
        $token = bin2hex(random_bytes(32));
        // Usamos fecha actual + 1 hora
        $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));

        // 3. Guardar token en la BD
        $update = $db->prepare("UPDATE usuarios SET reset_token = ?, reset_expires = ? WHERE id_usuario = ?");
        $update->execute([$token, $expires, $user['id_usuario']]);

        // 4. Preparar el Enlace (Detectar protocolo http o https)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domain = $_SERVER['HTTP_HOST'];
        // Ajusta la ruta si tu proyecto está en una subcarpeta, ej: /kahoot/index.php
        // Detectamos la ruta base del script actual para construir la URL relativa
        $path = dirname($_SERVER['PHP_SELF']); // /api
        $path = str_replace('/api', '', $path); // Raíz del proyecto
        
        $link = $protocol . $domain . $path . "/index.php?view=reset&token=" . $token;
        
        // 5. Intentar enviar correo real
        $smtp_config = $settings['smtp'] ?? null;
        $cuerpo = "<h1>Hola {$user['nombre']}</h1><p>Para recuperar tu contraseña, pulsa aquí:</p><p><a href='$link'>$link</a></p>";
        
        $enviado = trySendEmail($smtp_config, $data->correo, $user['nombre'], "Recuperar Contraseña EduGame", $cuerpo);

        if ($enviado) {
            echo json_encode(["success" => true, "message" => "Correo enviado correctamente."]);
        } else {
            // MODO DESARROLLO: Si falla el correo, devolvemos el link en el JSON para probar
            echo json_encode([
                "success" => true, 
                "message" => "Simulación: Correo no configurado.",
                "debug_link" => $link // <--- AQUÍ ESTÁ LA CLAVE PARA PROBAR
            ]);
        }
    } else {
        // Por seguridad, no decimos si el correo existe o no, decimos que se envió.
        echo json_encode(["success" => true, "message" => "Si el correo existe, se ha enviado."]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error del servidor: " . $e->getMessage()]);
}
?>