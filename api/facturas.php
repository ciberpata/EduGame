<?php
// api/facturas.php
header("Content-Type: application/json; charset=UTF-8");
session_start();
include_once '../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = (new Database())->getConnection();

// --- SEGURIDAD ---
// En producción: $uid = $_SESSION['user_id'];
$current_user_id = 1; // ID simulado de la Academia/Profesor Indep. conectado

if ($method === 'GET') {
    handleListFacturas($db, $current_user_id);
} else {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
}

function handleListFacturas($db, $uid) {
    try {
        // Seleccionar facturas del usuario, ordenadas por fecha de emisión descendente
        $sql = "SELECT id_factura, numero_factura, concepto, monto, estado, url_archivo, fecha_emision, fecha_pago 
                FROM facturas 
                WHERE id_anfitrion = :uid 
                ORDER BY fecha_emision DESC";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':uid', $uid);
        $stmt->execute();
        
        $facturas = $stmt->fetchAll();
        
        // Simulación: Si no hay facturas, creamos una estructura vacía
        // En el futuro aquí podrías añadir lógica de permisos si un subordinado intenta acceder
        
        echo json_encode(["data" => $facturas]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener facturas: " . $e->getMessage()]);
    }
}
?>