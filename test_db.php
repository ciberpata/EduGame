<?php
// test_db.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnóstico de EduGame</h1>";

// 1. Verificar Estructura de Carpetas
echo "<h2>1. Verificando Archivos Críticos</h2>";
$files = [
    'config/db.php',
    'helpers/Logger.php',
    'api/login_handler.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p style='color:green'>✅ Encontrado: $file</p>";
    } else {
        echo "<p style='color:red'>❌ FALTA: $file (Revisa mayúsculas/minúsculas y carpeta)</p>";
    }
}

// 2. Probar Conexión a BD
echo "<h2>2. Probando Conexión a Base de Datos</h2>";
if (file_exists('config/db.php')) {
    try {
        require_once 'config/db.php';
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            echo "<p style='color:green'>✅ Conexión Exitosa a la BD.</p>";
            
            // 3. Verificar Datos del Usuario Admin
            echo "<h2>3. Verificando Usuario Admin</h2>";
            $sql = "SELECT id_usuario, nombre, correo, contrasena, id_rol FROM usuarios WHERE correo = 'admin@edugame.com'";
            $stmt = $conn->query($sql);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                echo "<p style='color:green'>✅ Usuario Admin encontrado: " . htmlspecialchars($user['nombre']) . "</p>";
                echo "<p>Rol ID: " . $user['id_rol'] . "</p>";
                
                // Verificar hash
                if (password_verify('123456', $user['contrasena'])) {
                     echo "<p style='color:green'>✅ La contraseña '123456' coincide con el hash.</p>";
                } else {
                     echo "<p style='color:red'>❌ La contraseña '123456' NO coincide con el hash almacenado.</p>";
                }
            } else {
                echo "<p style='color:red'>❌ No se encontró el usuario 'admin@edugame.com'. ¿Ejecutaste el script SQL?</p>";
            }
            
        } else {
            echo "<p style='color:red'>❌ La conexión devolvió null (Revisa config/db.php).</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ Error de Conexión: " . $e->getMessage() . "</p>";
    }
}
?>