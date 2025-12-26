<?php
// api/preguntas.php
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);
header("Content-Type: application/json; charset=UTF-8");
session_start();

require_once '../config/db.php';
require_once '../helpers/logger.php'; 
require '../vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] == 6) { 
        http_response_code(403); throw new Exception('No autorizado'); 
    }

    $db = (new Database())->getConnection();
    $uid = $_SESSION['user_id'];
    $role = $_SESSION['user_role'];

    // Manejo de descarga de plantilla (GET directo)
    if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
        handleDownloadTemplate();
        exit;
    }

    $contentType = $_SERVER["CONTENT_TYPE"] ?? '';
    $input = (strpos($contentType, 'application/json') !== false) ? json_decode(file_get_contents("php://input"), true) : $_POST;
    $action = $input['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'list': handleList($db, $uid, $role); break;
        case 'get_one': handleGetOne($db, $uid, $role); break;
        case 'create_manual': handleCreateManual($db, $uid, $role, $input); break;
        case 'update': handleUpdate($db, $uid, $role, $input); break; 
        case 'update_manual': handleUpdate($db, $uid, $role, $input); break;
        case 'duplicate': handleDuplicate($db, $uid, $role, $input); break;
        case 'delete': handleDelete($db, $uid, $role, $input); break;
        case 'bulk_reassign': handleBulkReassign($db, $uid, $role, $input); break;
        case 'validate_import': handleValidateImport(); break;
        case 'execute_import': handleExecuteImport($db, $uid, $role, $input); break;
        default: throw new Exception('Acción no válida: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(['status' => 'error', 'mensaje' => $e->getMessage(), 'error' => $e->getMessage()]); 
    exit;
}

// --- FUNCIONES PRINCIPALES ---

function handleList($db, $uid, $role) {
    // Parámetros
    $scope = $_GET['scope'] ?? 'mine';
    $search = $_GET['search'] ?? '';
    $special = $_GET['special'] ?? ''; 
    
    // Filtros estándar
    $f_idioma = $_GET['f_idioma'] ?? '';
    $f_tipo = $_GET['f_tipo'] ?? '';
    $f_seccion = $_GET['f_seccion'] ?? '';
    $f_usuario = $_GET['f_usuario'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    
    // Ordenación
    $sort = $_GET['sort'] ?? 'id_pregunta';
    $order = $_GET['order'] ?? 'DESC';
    $allowedSorts = ['id_pregunta', 'texto', 'seccion', 'idioma', 'creado_en', 'doble_valor', 'veces_usada'];
    if(!in_array($sort, $allowedSorts)) $sort = 'id_pregunta';
    if($order !== 'ASC' && $order !== 'DESC') $order = 'DESC';

    // Query Base
    $sql = "SELECT SQL_CALC_FOUND_ROWS p.*, u.nombre as nombre_propietario, pad.nombre as nombre_academia,
            (SELECT COUNT(*) FROM partida_preguntas WHERE id_pregunta = p.id_pregunta) as veces_usada
            FROM preguntas p 
            JOIN usuarios u ON p.id_propietario = u.id_usuario 
            LEFT JOIN usuarios pad ON u.id_padre = pad.id_usuario 
            WHERE 1=1";
    $params = [];

    // --- LÓGICA DE VISIBILIDAD (SCOPE) ---
    
    if ($role == 1) {
        // Superadmin ve todo
    } 
    elseif ($role == 2) {
        // ACADEMIA: Ve sus preguntas Y las de sus subordinados (hijos: profesores y editores)
        $sql .= " AND (p.id_propietario = ? OR u.id_padre = ?)";
        $params[] = $uid;
        $params[] = $uid;
    } 
    else {
        // PROFESORES (Rol 3, 4) y EDITORES (Rol 5)
        if ($scope === 'shared_bank' && in_array($role, [3, 4, 5])) {
            // Verificar si tiene padre (Academia)
            $stmtP = $db->prepare("SELECT id_padre FROM usuarios WHERE id_usuario = ?");
            $stmtP->execute([$uid]);
            $padre = $stmtP->fetchColumn();
            
            if ($padre) {
                // LOGICA CORREGIDA: Ver preguntas de la Academia (Padre) Y de los Editores (Rol 5) de esa Academia
                // Excluimos las propias del usuario actual para que no salgan duplicadas o confusas en este filtro
                $sql .= " AND p.id_propietario != ? AND (
                            p.id_propietario = ? 
                            OR p.id_propietario IN (SELECT id_usuario FROM usuarios WHERE id_padre = ? AND id_rol = 5)
                          )";
                $params[] = $uid;   // Excluir mis propias preguntas de la vista "Compartidas"
                $params[] = $padre; // Preguntas de la Academia
                $params[] = $padre; // Preguntas de los Editores de la Academia
            } else {
                // Rol 4 (Independiente) o Rol 3 sin padre -> No ven nada en banco compartido
                $sql .= " AND 0"; 
            }
        } else {
            // Defecto (scope='mine'): Ver SOLO mis preguntas
            $sql .= " AND p.id_propietario = ?";
            $params[] = $uid;
        }
    }

    // --- FILTROS ---

    if ($special === 'orphan') {
        $sql .= " AND (p.seccion IS NULL OR p.seccion = '')";
    } elseif (!empty($f_seccion)) { 
        $sql .= " AND p.seccion LIKE ?"; 
        $params[] = "%$f_seccion%"; 
    }

    if (!empty($search)) { 
        $sql .= " AND (p.texto LIKE ? OR p.seccion LIKE ?)"; 
        $term = "%$search%";
        $params[] = $term; $params[] = $term;
    }
    if (!empty($f_idioma)) { $sql .= " AND p.idioma = ?"; $params[] = $f_idioma; }
    if (!empty($f_tipo)) { $sql .= " AND p.tipo = ?"; $params[] = $f_tipo; }
    if (!empty($f_usuario)) { $sql .= " AND p.id_propietario = ?"; $params[] = $f_usuario; }

    if (!empty($dateFrom)) { $sql .= " AND p.creado_en >= ?"; $params[] = $dateFrom . " 00:00:00"; }
    if (!empty($dateTo)) { $sql .= " AND p.creado_en <= ?"; $params[] = $dateTo . " 23:59:59"; }

    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;

    $sql .= " ORDER BY $sort $order LIMIT $limit OFFSET $offset";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = $db->query("SELECT FOUND_ROWS()")->fetchColumn();

    echo json_encode(['data' => $data, 'total' => $total, 'page' => $page, 'limit' => $limit]);
}

function handleGetOne($db, $uid, $role) {
    $id = $_GET['id'] ?? 0;
    // Lectura laxa para permitir duplicar preguntas compartidas
    $stmt = $db->prepare("SELECT * FROM preguntas WHERE id_pregunta = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$data) throw new Exception("Pregunta no encontrada");
    echo json_encode(['data' => $data, 'success'=>true]);
}

function handleCreateManual($db, $uid, $role, $data) { saveQuestion($db, $uid, $role, $data, null); }
function handleUpdate($db, $uid, $role, $data) { saveQuestion($db, $uid, $role, $data, $data['id_pregunta']); }

function saveQuestion($db, $uid, $role, $data, $idToUpdate = null) {
    // Lógica de asignación: Si es academia, puede asignar a otro. Si es editor, asigna a su padre si existe.
    $targetId = $uid;
    $compartida = 0;

    if ($role == 1 || $role == 2) {
        $targetId = !empty($data['target_user_id']) ? $data['target_user_id'] : $uid;
        // Si la academia se asigna a sí misma, es compartida por defecto
        if ($role == 2 && $targetId == $uid) $compartida = 1;
    } elseif ($role == 5) {
        // El editor asigna a su padre (Academia) automáticamente
        $stmtP = $db->prepare("SELECT id_padre FROM usuarios WHERE id_usuario = ?");
        $stmtP->execute([$uid]);
        $padre = $stmtP->fetchColumn();
        if ($padre) {
            $targetId = $padre;
            $compartida = 1; // Las preguntas de editor van al banco compartido
        }
    }
    
    $ops = $data['opciones'] ?? [];
    $opcionesF = [];
    foreach($ops as $o) {
        $opcionesF[] = ['texto'=>$o['texto'], 'es_correcta'=>(bool)($o['es_correcta']??false)];
    }
    $json = json_encode($opcionesF, JSON_UNESCAPED_UNICODE);
    
    $doble = (int)($data['doble_valor'] ?? 0);
    $tiempo = (int)($data['tiempo_limite'] ?? 20);

    if ($idToUpdate) {
        if (!checkOwner($db, $idToUpdate, $uid, $role)) throw new Exception("No permiso edición");
        $sql = "UPDATE preguntas SET texto=?, seccion=?, tipo=?, idioma=?, tiempo_limite=?, doble_valor=?, json_opciones=?, compartida=? WHERE id_pregunta=?";
        $db->prepare($sql)->execute([trim($data['texto']), trim($data['seccion']), $data['tipo'], $data['idioma'], $tiempo, $doble, $json, $compartida, $idToUpdate]);
        Logger::registrar($db, $uid, 'UPDATE', 'preguntas', $idToUpdate);
    } else {
        $sql = "INSERT INTO preguntas (id_propietario, texto, seccion, tipo, idioma, tiempo_limite, doble_valor, json_opciones, compartida) VALUES (?,?,?,?,?,?,?,?,?)";
        $db->prepare($sql)->execute([$targetId, trim($data['texto']), trim($data['seccion']), $data['tipo'], $data['idioma'], $tiempo, $doble, $json, $compartida]);
        Logger::registrar($db, $uid, 'INSERT', 'preguntas', $db->lastInsertId());
    }
    echo json_encode(['success' => true]);
}

function handleDuplicate($db, $uid, $role, $input) {
    $id = $input['id_pregunta'];
    $stmt = $db->prepare("SELECT texto, json_opciones, seccion, tipo, tiempo_limite FROM preguntas WHERE id_pregunta = ?");
    $stmt->execute([$id]);
    $orig = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$orig) throw new Exception("Original no encontrada");

    $sql = "INSERT INTO preguntas (texto, json_opciones, seccion, tipo, tiempo_limite, id_propietario) VALUES (?, ?, ?, ?, ?, ?)";
    $db->prepare($sql)->execute([
        $orig['texto'] . " (Copia)", 
        $orig['json_opciones'], 
        $orig['seccion'], 
        $orig['tipo'], 
        $orig['tiempo_limite'], 
        $uid 
    ]);

    Logger::registrar($db, $uid, 'INSERT', 'preguntas', $db->lastInsertId(), ['action'=>'duplicate', 'from'=>$id]);
    echo json_encode(['success' => true]);
}

function handleDelete($db, $uid, $role, $data) {
    $id = $data['id_pregunta'];
    if (!checkOwner($db, $id, $uid, $role)) throw new Exception("No autorizado");
    $db->prepare("DELETE FROM preguntas WHERE id_pregunta = ?")->execute([$id]);
    echo json_encode(['success' => true]);
}

function handleBulkReassign($db, $uid, $role, $data) {
    if ($role != 1 && $role != 2) throw new Exception("Solo Admin o Academia pueden reasignar.");
    $new_owner_id = $data['new_owner_id'];
    $question_ids = $data['question_ids'] ?? [];
    if (empty($question_ids)) throw new Exception("No seleccionaste preguntas.");
    $in  = str_repeat('?,', count($question_ids) - 1) . '?';
    
    if ($role == 2) {
        $stmtCheck = $db->prepare("SELECT id_padre FROM usuarios WHERE id_usuario = ?");
        $stmtCheck->execute([$new_owner_id]);
        if ($stmtCheck->fetchColumn() != $uid) throw new Exception("El usuario destino no es de tu academia.");
        
        $sql = "UPDATE preguntas SET id_propietario = ? WHERE id_pregunta IN ($in) AND id_propietario IN (SELECT id_usuario FROM usuarios WHERE id_usuario = ? OR id_padre = ?)";
        $params = array_merge([$new_owner_id], $question_ids, [$uid, $uid]);
    } else {
        $sql = "UPDATE preguntas SET id_propietario = ? WHERE id_pregunta IN ($in)";
        $params = array_merge([$new_owner_id], $question_ids);
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(["success" => true, "message" => $stmt->rowCount() . " preguntas reasignadas."]);
}

function checkOwner($db, $idPregunta, $uid, $role) {
    if ($role == 1) return true;
    $stmt = $db->prepare("SELECT id_propietario FROM preguntas WHERE id_pregunta = ?");
    $stmt->execute([$idPregunta]);
    $owner = $stmt->fetchColumn();
    if ($owner == $uid) return true;
    if ($role == 2) {
        $stmt2 = $db->prepare("SELECT id_padre FROM usuarios WHERE id_usuario = ?");
        $stmt2->execute([$owner]);
        if ($stmt2->fetchColumn() == $uid) return true;
    }
    return false;
}

// --- IMPORTACIÓN COMPLETA ---

function handleDownloadTemplate() {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=plantilla_preguntas.csv');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['texto_pregunta', 'seccion', 'idioma', 'opcion_a', 'opcion_b', 'opcion_c', 'opcion_d', 'respuesta_correcta', 'tiempo_limite', 'doble_valor'], ';', '"', '\\');
    fputcsv($out, ['Ejemplo: ¿Capital de España?', 'Geografía', 'es', 'Barcelona', 'Madrid', 'Sevilla', 'Valencia', '2', '20', '0'], ';', '"', '\\');
    fclose($out);
}

function handleValidateImport() {
    if (empty($_FILES['archivo'])) { echo json_encode(['status'=>'error', 'mensaje'=>'No archivo']); return; }
    $file = $_FILES['archivo']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    
    if (in_array($ext, ['xls', 'xlsx', 'ods'])) {
        try {
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $headers = [];
            foreach ($sheet->getRowIterator(1, 1) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $val = $cell->getValue();
                    if ($val !== null && $val !== '') $headers[] = (string)$val;
                }
            }
            echo json_encode(['status' => 'need_mapping', 'headers' => $headers]);
            return;
        } catch (Exception $e) {
            echo json_encode(['status'=>'error', 'mensaje'=>'Error leyendo archivo: ' . $e->getMessage()]); return;
        }
    } 
    $fh = fopen($file, 'r');
    $line = fgets($fh);
    $delim = (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';
    rewind($fh);
    $header = fgetcsv($fh, 0, $delim, '"', '\\');
    if(!$header) { echo json_encode(['status'=>'error', 'mensaje'=>'CSV inválido']); return; }
    $map = getCsvMap($header);
    if (!isset($map['texto'])) { echo json_encode(['status'=>'need_mapping', 'headers'=>$header]); return; }
    $count = 0; while(fgetcsv($fh, 0, $delim, '"', '\\')) $count++;
    echo json_encode(['status'=>'ok', 'filas_validas'=>$count]);
}

function handleExecuteImport($db, $uid, $role, $postData) {
    // Misma lógica de asignación que saveQuestion
    $targetId = $uid;
    $compartida = 0;

    if ($role == 1 || $role == 2) {
        $targetId = !empty($postData['target_user_id']) ? $postData['target_user_id'] : $uid;
        if ($role == 2 && $targetId == $uid) $compartida = 1;
    } elseif ($role == 5) {
        $stmtP = $db->prepare("SELECT id_padre FROM usuarios WHERE id_usuario = ?");
        $stmtP->execute([$uid]);
        $padre = $stmtP->fetchColumn();
        if ($padre) {
            $targetId = $padre;
            $compartida = 1;
        }
    }

    $file = $_FILES['archivo']['tmp_name'];
    $mappingJSON = $postData['mapping'] ?? null;
    $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    
    $inserted = 0; $skipped = 0;
    $stmtCheck = $db->prepare("SELECT id_pregunta FROM preguntas WHERE texto = ? AND id_propietario = ?");
    $stmtIns = $db->prepare("INSERT INTO preguntas (id_propietario, texto, seccion, tipo, idioma, tiempo_limite, doble_valor, json_opciones, compartida) VALUES (?,?,?,?,?,?,?,?,?)");

    // Lógica Excel
    if ($mappingJSON && in_array($ext, ['xls', 'xlsx', 'ods'])) {
        $map = json_decode($mappingJSON, true);
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        for ($rowIdx = 2; $rowIdx <= $highestRow; $rowIdx++) {
            $getVal = function($key) use ($map, $sheet, $rowIdx) {
                if (!isset($map[$key])) return '';
                $colIndex = $map[$key] + 1; 
                $colString = Coordinate::stringFromColumnIndex($colIndex); 
                return trim($sheet->getCell($colString . $rowIdx)->getValue() ?? '');
            };
            $texto = $getVal('texto');
            if (empty($texto)) continue;
            $stmtCheck->execute([$texto, $targetId]);
            if ($stmtCheck->fetch()) { $skipped++; continue; }
            
            $opciones = [];
            $rcRaw = strtolower($getVal('correcta'));
            $rcIndex = -1;
            if (in_array($rcRaw, ['1', 'a', 'opcion_a', 'si', 'sí', 'verdadero'])) $rcIndex = 0;
            elseif (in_array($rcRaw, ['2', 'b', 'opcion_b', 'no', 'falso'])) $rcIndex = 1;
            elseif (in_array($rcRaw, ['3', 'c'])) $rcIndex = 2;
            elseif (in_array($rcRaw, ['4', 'd'])) $rcIndex = 3;

            $opciones[] = ['texto' => $getVal('a'), 'es_correcta' => ($rcIndex===0)];
            $opciones[] = ['texto' => $getVal('b'), 'es_correcta' => ($rcIndex===1)];
            if($getVal('c')) $opciones[] = ['texto'=>$getVal('c'), 'es_correcta'=>($rcIndex===2)];
            if($getVal('d')) $opciones[] = ['texto'=>$getVal('d'), 'es_correcta'=>($rcIndex===3)];

            $tipo = (count($opciones)==2 && strtolower($opciones[0]['texto'])=='verdadero') ? 'verdadero_falso' : 'quiz';
            $tVal = $getVal('tiempo'); $dVal = $getVal('doble');
            $stmtIns->execute([$targetId, $texto, $getVal('seccion'), $tipo, $getVal('idioma') ?: 'es', (int)($tVal ?: 20), (int)($dVal ?: 0), json_encode($opciones, JSON_UNESCAPED_UNICODE), $compartida]);
            $inserted++;
        }
    } else {
        // Lógica CSV
        $fh = fopen($file, 'r');
        $line = fgets($fh);
        $delim = (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';
        rewind($fh);
        $header = fgetcsv($fh, 0, $delim, '"', '\\');
        $map = $mappingJSON ? json_decode($mappingJSON, true) : getCsvMap($header);
        while (($row = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
            $v = function($k) use ($row, $map) { return isset($map[$k]) ? trim($row[$map[$k]] ?? '') : ''; };
            $texto = $v('texto');
            if (empty($texto)) continue;
            $stmtCheck->execute([$texto, $targetId]);
            if ($stmtCheck->fetch()) { $skipped++; continue; }
            
            $opciones = [];
            $rcRaw = strtolower($v('correcta'));
            $rcIndex = -1;
            if (in_array($rcRaw, ['1', 'a', 'opcion_a', 'si', 'sí', 'verdadero'])) $rcIndex = 0;
            elseif (in_array($rcRaw, ['2', 'b', 'opcion_b', 'no', 'falso'])) $rcIndex = 1;
            elseif (in_array($rcRaw, ['3', 'c', 'opcion_c'])) $rcIndex = 2;
            elseif (in_array($rcRaw, ['4', 'd', 'opcion_d'])) $rcIndex = 3;

            $opciones[] = ['texto' => $v('a'), 'es_correcta' => ($rcIndex===0)];
            $opciones[] = ['texto' => $v('b'), 'es_correcta' => ($rcIndex===1)];
            if($v('c')) $opciones[] = ['texto'=>$v('c'), 'es_correcta'=>($rcIndex===2)];
            if($v('d')) $opciones[] = ['texto'=>$v('d'), 'es_correcta'=>($rcIndex===3)];

            $tipo = (count($opciones)==2 && strtolower($opciones[0]['texto'])=='verdadero') ? 'verdadero_falso' : 'quiz';
            $doble = (int)$v('doble');
            $stmtIns->execute([$targetId, $texto, $v('seccion'), $tipo, $v('idioma') ?: 'es', (int)($v('tiempo') ?: 20), $doble, json_encode($opciones, JSON_UNESCAPED_UNICODE), $compartida]);
            $inserted++;
        }
    }
    Logger::registrar($db, $uid, 'IMPORT', 'preguntas', null, ['inserted' => $inserted, 'skipped' => $skipped]);
    echo json_encode(['status'=>'ok', 'insertadas'=>$inserted, 'saltados'=>$skipped, 'mensaje'=>"$inserted preguntas importadas."]);
}

function getCsvMap($header) {
    $map = [];
    foreach ($header as $i => $col) {
        $k = strtolower(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $col)));
        if (strpos($k, 'texto') !== false || strpos($k, 'pregunta') !== false) $map['texto'] = $i;
        if (strpos($k, 'seccion') !== false) $map['seccion'] = $i;
        if (strpos($k, 'opcion_a') !== false || $k=='a') $map['a'] = $i;
        if (strpos($k, 'opcion_b') !== false || $k=='b') $map['b'] = $i;
        if (strpos($k, 'opcion_c') !== false || $k=='c') $map['c'] = $i;
        if (strpos($k, 'opcion_d') !== false || $k=='d') $map['d'] = $i;
        if (strpos($k, 'correcta') !== false) $map['correcta'] = $i;
        if (strpos($k, 'tiempo') !== false) $map['tiempo'] = $i;
        if (strpos($k, 'idioma') !== false) $map['idioma'] = $i;
        if (strpos($k, 'doble') !== false) $map['doble'] = $i;
    }
    return $map;
}
?>