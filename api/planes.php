<?php
// api/planes.php
header("Content-Type: application/json; charset=UTF-8");
session_start();
require_once '../config/db.php';

// Solo Superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    http_response_code(403); echo json_encode(["error" => "No autorizado"]); exit;
}

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);

try {
    switch ($method) {
        case 'GET':
            $stmt = $db->query("SELECT *, (SELECT COUNT(*) FROM usuarios WHERE id_plan = planes.id_plan) as usuarios_count FROM planes ORDER BY precio_mensual ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

            case 'POST': // Crear o Editar
                $id = $input['id_plan'] ?? null;
                $nombre = $input['nombre'];
                $precio = $input['precio_mensual'];
                $jug = (int)$input['limite_jugadores'];
                $par = (int)$input['limite_partidas'];
                
                // Features existentes
                $f_marca = isset($input['feature_marca_blanca']) ? 1 : 0;
                $f_exp = isset($input['feature_exportar']) ? 1 : 0;
                $f_modos = isset($input['feature_modos_extra']) ? 1 : 0;
                
                // NUEVAS FEATURES
                $lim_audit = (int)$input['limite_auditoria_dias'];
                $f_filtros = isset($input['feature_filtros_avanzados']) ? 1 : 0;
                $t_sql = isset($input['tool_sql']) ? 1 : 0;
                $t_wipe = isset($input['tool_wipe']) ? 1 : 0;
    
                if ($id) {
                    $sql = "UPDATE planes SET nombre=?, precio_mensual=?, limite_jugadores=?, limite_partidas=?, 
                            feature_marca_blanca=?, feature_exportar=?, feature_modos_extra=?,
                            limite_auditoria_dias=?, feature_filtros_avanzados=?, tool_sql=?, tool_wipe=?
                            WHERE id_plan=?";
                    $db->prepare($sql)->execute([$nombre, $precio, $jug, $par, $f_marca, $f_exp, $f_modos, $lim_audit, $f_filtros, $t_sql, $t_wipe, $id]);
                } else {
                    $sql = "INSERT INTO planes (nombre, precio_mensual, limite_jugadores, limite_partidas, feature_marca_blanca, feature_exportar, feature_modos_extra, limite_auditoria_dias, feature_filtros_avanzados, tool_sql, tool_wipe) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $db->prepare($sql)->execute([$nombre, $precio, $jug, $par, $f_marca, $f_exp, $f_modos, $lim_audit, $f_filtros, $t_sql, $t_wipe]);
                }
                echo json_encode(['success' => true]);
                break;

        case 'DELETE':
            $id = $input['id_plan'];
            if ($id == 1) throw new Exception("No se puede borrar el plan base.");
            
            $db->beginTransaction();
            // Mover usuarios al plan 1 antes de borrar
            $db->prepare("UPDATE usuarios SET id_plan = 1 WHERE id_plan = ?")->execute([$id]);
            $db->prepare("DELETE FROM planes WHERE id_plan = ?")->execute([$id]);
            $db->commit();
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
}
?>