<?php
// api/export_pdf.php
// Sistema de generación de reportes PDF para EduGame
// Evita regresiones usando lógica de lectura independiente.

session_start();
require_once '../config/db.php';
require_once '../helpers/logger.php';
require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. Seguridad
if (!isset($_SESSION['user_id'])) {
    die("Acceso denegado");
}

$uid = $_SESSION['user_id'];
$role = $_SESSION['user_role'];
$lang = $_SESSION['lang'] ?? 'es';

// 2. Cargar Traducciones (Mini-loader local para no depender de index.php)
$trans_file = "../locales/i18n.{$lang}.json";
$trans = file_exists($trans_file) ? json_decode(file_get_contents($trans_file), true) : [];
function _t($key) { global $trans; return $trans[$key] ?? $key; }

$type = $_GET['type'] ?? '';
$db = (new Database())->getConnection();
$htmlContent = "";
$reportTitle = "";

try {
    // 3. Lógica según el tipo de informe
    switch ($type) {
        case 'usuarios':
            // Solo Superadmin (1) o Academia (2) pueden exportar usuarios
            if (!in_array($role, [1, 2])) die("No autorizado");
            
            $reportTitle = _t('pdf_title_users');
            $search = $_GET['search'] ?? '';
            
            $sql = "SELECT u.nombre, u.correo, r.nombre as rol, u.activo, u.creado_en 
                    FROM usuarios u 
                    JOIN roles r ON u.id_rol = r.id_rol 
                    WHERE 1=1";
            $params = [];

            if ($role == 2) {
                $sql .= " AND u.id_padre = ?";
                $params[] = $uid;
            } elseif ($role != 1) {
                die("No autorizado");
            }

            if (!empty($search)) {
                $sql .= " AND (u.nombre LIKE ? OR u.correo LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            $sql .= " ORDER BY u.nombre ASC LIMIT 500"; // Limite seguridad memoria

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Construir Tabla HTML
            $htmlContent = '
            <thead>
                <tr>
                    <th>'._t('name_label').'</th>
                    <th>'._t('email_label').'</th>
                    <th>'._t('key_users_header_role').'</th>
                    <th>'._t('key_users_header_status').'</th>
                    <th>'._t('audit_col_date').'</th>
                </tr>
            </thead>
            <tbody>';
            
            foreach ($rows as $row) {
                $estado = $row['activo'] ? _t('key_users_status_active') : _t('key_users_status_blocked');
                $fecha = date('d/m/Y', strtotime($row['creado_en']));
                $htmlContent .= "<tr>
                    <td>".htmlspecialchars($row['nombre'])."</td>
                    <td>".htmlspecialchars($row['correo'])."</td>
                    <td>".htmlspecialchars($row['rol'])."</td>
                    <td>{$estado}</td>
                    <td>{$fecha}</td>
                </tr>";
            }
            break;

        case 'partidas':
            if (!in_array($role, [1, 2, 3, 4])) die("No autorizado");
            $reportTitle = _t('pdf_title_games');
            
            $sql = "SELECT p.nombre_partida, p.codigo_pin, p.estado, p.fecha_inicio, 
                           m.nombre as modo, 
                           (SELECT COUNT(*) FROM jugadores_sesion WHERE id_partida = p.id_partida) as jugadores
                    FROM partidas p
                    LEFT JOIN modos_juego m ON p.id_modo = m.id_modo
                    WHERE 1=1";
            $params = [];

            if ($role != 1) {
                // Academias y Profes ven lo suyo
                if ($role == 2) {
                    $sql .= " AND (p.id_anfitrion = ? OR p.id_anfitrion IN (SELECT id_usuario FROM usuarios WHERE id_padre = ?))";
                    $params[] = $uid; $params[] = $uid;
                } else {
                    $sql .= " AND p.id_anfitrion = ?";
                    $params[] = $uid;
                }
            }
            
            $sql .= " ORDER BY p.fecha_inicio DESC LIMIT 200";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $htmlContent = '
            <thead>
                <tr>
                    <th>'._t('game_form_name').'</th>
                    <th>PIN</th>
                    <th>'._t('game_form_mode').'</th>
                    <th>'._t('key_users_header_status').'</th>
                    <th>Jug.</th>
                    <th>'._t('audit_col_date').'</th>
                </tr>
            </thead>
            <tbody>';

            foreach ($rows as $row) {
                $fecha = date('d/m/Y H:i', strtotime($row['fecha_inicio']));
                $htmlContent .= "<tr>
                    <td>".htmlspecialchars($row['nombre_partida'])."</td>
                    <td><b>{$row['codigo_pin']}</b></td>
                    <td>".htmlspecialchars($row['modo'])."</td>
                    <td>".ucfirst($row['estado'])."</td>
                    <td>{$row['jugadores']}</td>
                    <td>{$fecha}</td>
                </tr>";
            }
            break;

        case 'preguntas':
            $reportTitle = _t('pdf_title_questions');
            // Reutilizamos lógica de permisos simple
            $sql = "SELECT p.texto, p.seccion, p.tipo, p.tiempo_limite 
                    FROM preguntas p WHERE 1=1";
            $params = [];
            
            if ($role != 1) {
                // Simplificación: Exporta solo mis preguntas para evitar lógica compleja de "compartidas" en PDF rápido
                $sql .= " AND p.id_propietario = ?";
                $params[] = $uid;
            }
            
            $sql .= " ORDER BY p.texto ASC LIMIT 300";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $htmlContent = '
            <thead>
                <tr>
                    <th>'._t('key_field_question').'</th>
                    <th>'._t('key_field_section').'</th>
                    <th>'._t('key_filter_type').'</th>
                    <th>'._t('key_form_time').'</th>
                </tr>
            </thead>
            <tbody>';

            foreach ($rows as $row) {
                $htmlContent .= "<tr>
                    <td>".htmlspecialchars($row['texto'])."</td>
                    <td>".htmlspecialchars($row['seccion'])."</td>
                    <td>{$row['tipo']}</td>
                    <td>{$row['tiempo_limite']}s</td>
                </tr>";
            }
            break;

        default:
            die("Tipo de reporte no válido");
    }

    $htmlContent .= "</tbody>";

    // 4. Plantilla HTML completa para el PDF
    $fullHtml = '
    <html>
    <head>
        <style>
            body { font-family: sans-serif; font-size: 12px; color: #333; }
            h1 { color: #46178f; border-bottom: 2px solid #eee; padding-bottom: 10px; }
            .meta { font-size: 10px; color: #666; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background: #f3f4f6; padding: 8px; text-align: left; border-bottom: 2px solid #ddd; font-weight: bold; }
            td { padding: 8px; border-bottom: 1px solid #eee; }
            tr:nth-child(even) { background: #f9fafb; }
        </style>
    </head>
    <body>
        <h1>EduGame - ' . $reportTitle . '</h1>
        <div class="meta">
            ' . _t('pdf_generated_on') . ': ' . date('d/m/Y H:i') . ' | 
            ' . _t('pdf_generated_by') . ': ' . $_SESSION['user_name'] . '
        </div>
        <table>
            ' . $htmlContent . '
        </table>
    </body>
    </html>';

    // 5. Generar PDF con Dompdf
    $options = new Options();
    $options->set('defaultFont', 'Helvetica');
    $options->set('isRemoteEnabled', true); // Permitir imágenes si fuera necesario

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($fullHtml);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // 6. Auditoría
    Logger::registrar($db, $uid, 'EXPORT_PDF', $type, null, ['status' => 'success']);

    // 7. Salida al navegador
    $dompdf->stream("edugame_reporte_{$type}.pdf", ["Attachment" => false]);

} catch (Exception $e) {
    die("Error generando PDF: " . $e->getMessage());
}
?>