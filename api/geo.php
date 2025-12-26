<?php
// api/geo.php
header("Content-Type: application/json; charset=UTF-8");
require_once '../config/db.php';

$db = (new Database())->getConnection();
$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

try {
    if ($type === 'paises') {
        $stmt = $db->query("SELECT id, nombre FROM paises ORDER BY nombre ASC");
        echo json_encode($stmt->fetchAll());
    } 
    elseif ($type === 'provincias' && $id) {
        // El ID del país viene como 'ES', 'FR', etc.
        $stmt = $db->prepare("SELECT id, nombre FROM provincias WHERE id_pais = ? ORDER BY nombre ASC");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetchAll());
    } 
    elseif ($type === 'ciudades' && $id) {
        $stmt = $db->prepare("SELECT id, nombre FROM ciudades WHERE id_provincia = ? ORDER BY nombre ASC");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetchAll());
    } else {
        echo json_encode([]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>