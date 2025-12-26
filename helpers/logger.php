<?php
// helpers/logger.php
class Logger {
    public static function registrar($db, $userId, $accion, $entidad, $idAfectado = null, $detalles = null) {
        try {
            // Corrección Error 1452: Si el usuario es 0 o null, enviamos NULL explícito
            // Esto requiere que la columna id_usuario en BD permita NULL.
            $finalUserId = (!empty($userId) && $userId > 0) ? $userId : null;

            $sql = "INSERT INTO auditoria (id_usuario, accion, entidad, id_afectado, detalles, ip) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            
            // Si detalles es un array, lo convertimos a JSON
            if (is_array($detalles)) {
                $detalles = json_encode($detalles, JSON_UNESCAPED_UNICODE);
            }
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $stmt->execute([$finalUserId, $accion, $entidad, $idAfectado, $detalles, $ip]);
        } catch (Exception $e) {
            // Si falla (por ejemplo, si no se ejecutó el ALTER TABLE y el usuario es NULL), 
            // lo registramos en el log de errores de PHP pero no detenemos la ejecución.
            error_log("EduGame Audit Error: " . $e->getMessage());
        }
    }
}
?>