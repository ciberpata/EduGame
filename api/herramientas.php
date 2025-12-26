<?php
// api/herramientas.php
require_once '../config/db.php';
session_start();

header('Content-Type: application/json');

// 1. SEGURIDAD: Obtener datos de sesión casteados a enteros
$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$role = isset($_SESSION['user_role']) ? (int)$_SESSION['user_role'] : 0;

// 2. CONTROL DE ACCESO BÁSICO
// CORRECCIÓN: Roles permitidos para ver diagnósticos/herramientas: SOLO 1 (Admin) y 2 (Academia).
// El Rol 4 (Independiente) NO debe tener acceso a herramientas de sistema.
if ($uid === 0 || !in_array($role, [1, 2])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado.']);
    exit;
}

$db = (new Database())->getConnection();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    // --- ACCIÓN: BORRADO TOTAL (SÓLO SUPERADMIN) ---
    if ($action === 'wipe_games') {
        if ($role !== 1) throw new Exception("Acción restringida a Superadministrador.");
        if (($input['confirm'] ?? '') !== 'CONFIRMAR') throw new Exception("Confirmación incorrecta.");

        $db->beginTransaction();
        try {
            $db->query("DELETE FROM respuestas_log");
            $db->query("DELETE FROM jugadores_sesion");
            $db->query("DELETE FROM historial_partidas");
            $db->query("DELETE FROM partida_preguntas");
            $db->query("DELETE FROM partidas");
            $db->query("DELETE FROM auditoria WHERE accion LIKE 'JUEGO_%' OR entidad = 'partidas'");
            $db->commit();
            array_map('unlink', glob("../temp/*.json"));
            try { $db->query("ALTER TABLE partidas AUTO_INCREMENT = 1"); } catch (Exception $e) {}
            echo json_encode(['success' => true, 'message' => 'Limpieza total completada.']);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

    // --- ACCIÓN: LIMPIEZA AVANZADA CONFIGURABLE ---
    } elseif ($action === 'clean_data') {
        if ($role !== 1) throw new Exception("Acción restringida a Superadministrador.");

        $scope = $input['scope'] ?? 'global';
        $val = (int)($input['val'] ?? 30);
        $unit = strtoupper($input['unit'] ?? 'DAY');

        $allowedUnits = ['DAY', 'WEEK', 'MONTH', 'YEAR'];
        if (!in_array($unit, $allowedUnits)) throw new Exception("Unidad de tiempo no válida.");

        $whereClause = "WHERE p.fecha_inicio < NOW() - INTERVAL $val $unit";
        $params = [];

        if ($scope !== 'global') {
            $whereClause .= " AND p.id_anfitrion = ?";
            $params[] = $scope;
        }

        $sqlClose = "UPDATE partidas p SET p.estado = 'finalizada' $whereClause AND p.estado != 'finalizada'";
        $stmtClose = $db->prepare($sqlClose);
        $stmtClose->execute($params);
        $closedCount = $stmtClose->rowCount();

        $sqlLogs = "DELETE rl FROM respuestas_log rl 
                    INNER JOIN jugadores_sesion js ON rl.id_sesion = js.id_sesion
                    INNER JOIN partidas p ON js.id_partida = p.id_partida 
                    $whereClause";
        $stmtLogs = $db->prepare($sqlLogs);
        $stmtLogs->execute($params);
        $logsDeleted = $stmtLogs->rowCount();

        $sqlPlayers = "DELETE js FROM jugadores_sesion js 
                       INNER JOIN partidas p ON js.id_partida = p.id_partida 
                       $whereClause";
        $stmtPlayers = $db->prepare($sqlPlayers);
        $stmtPlayers->execute($params);
        $playersDeleted = $stmtPlayers->rowCount();

        $files = glob('../temp/partida_*.json');
        $deletedFiles = 0;
        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file) >= 86400)) { 
                unlink($file);
                $deletedFiles++;
            }
        }

        echo json_encode([
            'success' => true,
            'closed' => $closedCount,
            'logs_deleted' => $logsDeleted,
            'players_deleted' => $playersDeleted,
            'temp_files' => $deletedFiles
        ]);

    // --- ACCIÓN: EJECUTAR SQL (DIAGNÓSTICO) ---
    } elseif ($action === 'run_sql') {
        if ($role !== 1) throw new Exception("Restringido.");
        $sql = $input['sql'] ?? '';
        if (empty($sql)) throw new Exception("SQL vacío");
        $forbidden = ['DROP', 'TRUNCATE', 'ALTER', 'GRANT', 'REVOKE'];
        foreach($forbidden as $word) if (stripos($sql, $word) !== false) throw new Exception("Comando '$word' no permitido.");

        $stmt = $db->prepare($sql);
        $stmt->execute();
        if (stripos(trim($sql), 'SELECT') === 0 || stripos(trim($sql), 'SHOW') === 0) {
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'type' => 'read']);
        } else {
            echo json_encode(['success' => true, 'affected' => $stmt->rowCount(), 'type' => 'write']);
        }

    // --- ACCIÓN: OBTENER DIAGNÓSTICO ---
    } elseif ($action === 'get_diagnostics') {
        $stats = [];
        $tables = ['usuarios', 'partidas', 'preguntas', 'jugadores_sesion', 'respuestas_log', 'auditoria'];
        foreach($tables as $t) { try { $stats[$t] = $db->query("SELECT COUNT(*) FROM $t")->fetchColumn(); } catch(Exception $e) { $stats[$t] = 'Error'; } }
        
        $tempPath = '../temp';
        $checks = [
            ['desc' => 'Permisos /temp', 'count' => (is_writable($tempPath)?'OK':'NO'), 'status' => (is_writable($tempPath)?'ok':'error')],
            ['desc' => 'Max Upload', 'count' => ini_get('upload_max_filesize'), 'status' => 'ok']
        ];
        echo json_encode(['success' => true, 'stats' => $stats, 'checks' => $checks]);

    } else {
        throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    http_response_code(200); 
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>