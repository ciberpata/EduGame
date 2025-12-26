<?php
// api/auditoria.php
header("Content-Type: application/json; charset=UTF-8");
session_start();
include_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 2, 4])) {
    http_response_code(403); echo json_encode(["error" => "No autorizado"]); exit;
}

$db = (new Database())->getConnection();
$uid = $_SESSION['user_id'];
$urole = $_SESSION['user_role'];

// Filtros y Parámetros
$fUser = $_GET['user'] ?? '';
$fAction = $_GET['action'] ?? '';
$fEntity = $_GET['entity'] ?? '';
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Ordenación
$sort = $_GET['sort'] ?? 'fecha';
$order = $_GET['order'] ?? 'DESC';
$sortMap = [
    'fecha' => 'a.fecha',
    'usuario' => 'u.nombre',
    'accion' => 'a.accion',
    'entidad' => 'a.entidad'
];
$orderBy = $sortMap[$sort] ?? 'a.fecha';
if ($order !== 'ASC' && $order !== 'DESC') $order = 'DESC';

// Paginación
$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 20);
$offset = ($page - 1) * $limit;

// --- FIX: OBTENER LÍMITE DE DÍAS DEL PLAN ---
$limitDays = 0;
try {
    // Obtenemos el plan del usuario actual
    $stmtPlan = $db->prepare("
        SELECT p.limite_auditoria_dias 
        FROM usuarios u 
        JOIN planes p ON u.id_plan = p.id_plan 
        WHERE u.id_usuario = ?
    ");
    $stmtPlan->execute([$uid]);
    $limitDays = (int)$stmtPlan->fetchColumn();
} catch(Exception $e) {
    // Si falla, asumimos 30 días por seguridad o 0 si es superadmin
    $limitDays = ($urole == 1) ? 0 : 30;
}
// ---------------------------------------------

// Base SQL
$sql = "SELECT SQL_CALC_FOUND_ROWS a.*, u.nombre as nombre_usuario, u.id_rol 
        FROM auditoria a 
        LEFT JOIN usuarios u ON a.id_usuario = u.id_usuario 
        WHERE 1=1";
$params = [];

// APLICAR LÍMITE DE PLAN
if ($limitDays > 0) {
    $sql .= " AND a.fecha >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $params[] = $limitDays;
}

$excludeSelf = $_GET['exclude_self'] ?? false; 

// Permisos de datos
if ($urole == 2) {
    if ($excludeSelf === 'true') {
        // Solo acciones de mis hijos (profesores/alumnos), NO las mías
        $sql .= " AND u.id_padre = ?";
        $params[] = $uid;
    } else {
        // Comportamiento normal: Yo + Mis hijos
        $sql .= " AND (a.id_usuario = ? OR u.id_padre = ?)";
        $params[] = $uid; $params[] = $uid;
    }
} elseif ($urole == 4) {
    $sql .= " AND a.id_usuario = ?";
    $params[] = $uid;
}

// Filtros dinámicos
if ($fUser) { $sql .= " AND a.id_usuario = ?"; $params[] = $fUser; }

// LOGICA MEJORADA PARA ACCIONES MULTIPLES (ej: UPDATE,DELETE)
if ($fAction) { 
    if (strpos($fAction, ',') !== false) {
        $actions = explode(',', $fAction);
        // Crear placeholders ?,?,? según cantidad
        $placeholders = implode(',', array_fill(0, count($actions), '?'));
        $sql .= " AND a.accion IN ($placeholders)";
        foreach($actions as $act) $params[] = trim($act);
    } else {
        $sql .= " AND a.accion = ?"; 
        $params[] = $fAction; 
    }
}

if ($fEntity) { $sql .= " AND a.entidad = ?"; $params[] = $fEntity; }

// Filtros de Fecha
if (!empty($dateFrom)) {
    $sql .= " AND a.fecha >= ?";
    $params[] = $dateFrom . " 00:00:00";
}
if (!empty($dateTo)) {
    $sql .= " AND a.fecha <= ?";
    $params[] = $dateTo . " 23:59:59";
}

// Búsqueda general
if ($search) { 
    $sql .= " AND (a.detalles LIKE ? OR u.nombre LIKE ? OR a.accion LIKE ? OR a.entidad LIKE ? OR a.id_afectado LIKE ? OR a.ip LIKE ?)"; 
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
}

$sql .= " ORDER BY $orderBy $order LIMIT $limit OFFSET $offset";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total = $db->query("SELECT FOUND_ROWS()")->fetchColumn();
    
    echo json_encode([
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit)
    ]);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(["error" => $e->getMessage()]);
}
?>