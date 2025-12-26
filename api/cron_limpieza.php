<?php
// api/cron_limpieza.php
// Este script debe ser llamado por una tarea programada del servidor (Cron Job)
// Ejemplo: 0 4 * * * wget -q -O - https://tu-dominio.com/api/cron_limpieza.php >/dev/null 2>&1

require_once '../config/db.php';

header('Content-Type: text/plain');

$db = (new Database())->getConnection();

try {
    echo "Iniciando limpieza diaria...\n";

    // 1. Marcar partidas antiguas como finalizadas si quedaron colgadas (hace más de 24h)
    $stmtUpdate = $db->query("UPDATE partidas SET estado = 'finalizada' WHERE estado != 'finalizada' AND fecha_inicio < NOW() - INTERVAL 24 HOUR");
    echo "Partidas colgadas cerradas: " . $stmtUpdate->rowCount() . "\n";

    // 2. Eliminar logs de respuestas de partidas antiguas (hace más de 30 días)
    // Usamos DELETE con JOIN (MariaDB sintaxis)
    $sqlDeleteLogs = "DELETE rl FROM respuestas_log rl
                      INNER JOIN jugadores_sesion js ON rl.id_sesion = js.id_sesion
                      INNER JOIN partidas p ON js.id_partida = p.id_partida
                      WHERE p.fecha_inicio < NOW() - INTERVAL 30 DAY";
    
    $stmtLogs = $db->query($sqlDeleteLogs);
    echo "Logs de respuestas eliminados: " . $stmtLogs->rowCount() . "\n";

    // 3. Eliminar jugadores de esas sesiones antiguas
    $sqlDeletePlayers = "DELETE js FROM jugadores_sesion js
                         INNER JOIN partidas p ON js.id_partida = p.id_partida
                         WHERE p.fecha_inicio < NOW() - INTERVAL 30 DAY";
    
    $stmtPlayers = $db->query($sqlDeletePlayers);
    echo "Jugadores antiguos eliminados: " . $stmtPlayers->rowCount() . "\n";

    // 4. Limpiar ficheros JSON temporales de la carpeta /temp que tengan más de 24h
    $files = glob('../temp/partida_*.json');
    $deletedFiles = 0;
    $now = time();
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= 86400) { // 24 horas
                unlink($file);
                $deletedFiles++;
            }
        }
    }
    echo "Ficheros JSON temporales borrados: $deletedFiles\n";

    echo "Limpieza finalizada con éxito.";

} catch (Exception $e) {
    echo "Error durante la limpieza: " . $e->getMessage();
    http_response_code(500);
}
?>