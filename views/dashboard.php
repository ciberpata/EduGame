<?php
// views/dashboard.php

// 1. Obtener datos según el Rol
$db = (new Database())->getConnection();
$uid = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

// --- FIX ERROR: Asegurar que tenemos el nombre del rol ---
$rol_nombre = $_SESSION['rol_nombre'] ?? '';
if (empty($rol_nombre)) {
    $stmtRol = $db->prepare("SELECT nombre FROM roles WHERE id_rol = ?");
    $stmtRol->execute([$role]);
    $rol_nombre = $stmtRol->fetchColumn() ?: __('key_role_user'); 
    $_SESSION['rol_nombre'] = $rol_nombre; 
}

// Inicializar contadores
$stats = [
    'c1' => ['label' => __('dash_stat_teachers'), 'val' => 0, 'icon' => 'fa-chalkboard-user', 'color' => 'blue'],
    'c2' => ['label' => __('dash_stat_students'), 'val' => 0, 'icon' => 'fa-graduation-cap', 'color' => 'green'],
    'c3' => ['label' => __('dash_stat_questions'), 'val' => 0, 'icon' => 'fa-circle-question', 'color' => 'purple'],
    'c4' => ['label' => __('dash_stat_games'), 'val' => 0, 'icon' => 'fa-gamepad', 'color' => 'orange']
];
$activeGames = 0;

// Consultas SQL según Rol
if ($role == 1) { // Superadmin
    $stats['c1']['val'] = $db->query("SELECT COUNT(*) FROM usuarios WHERE id_rol IN (2,3,4,5)")->fetchColumn();
    $stats['c2']['val'] = $db->query("SELECT COUNT(*) FROM usuarios WHERE id_rol = 6")->fetchColumn();
    $stats['c3']['val'] = $db->query("SELECT COUNT(*) FROM preguntas")->fetchColumn();
    $stats['c4']['val'] = $db->query("SELECT COUNT(*) FROM partidas")->fetchColumn();
    $activeGames = $db->query("SELECT COUNT(*) FROM partidas WHERE estado IN ('sala_espera', 'jugando')")->fetchColumn();

} elseif ($role == 2) { // Academia
    $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE id_padre = ? AND id_rol IN (3,5)");
    $stmt->execute([$uid]);
    $stats['c1']['val'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE id_padre = ? AND id_rol = 6");
    $stmt->execute([$uid]);
    $stats['c2']['val'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM preguntas WHERE id_propietario = ? OR id_propietario IN (SELECT id_usuario FROM usuarios WHERE id_padre = ?)");
    $stmt->execute([$uid, $uid]);
    $stats['c3']['val'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM partidas WHERE id_anfitrion = ? OR id_anfitrion IN (SELECT id_usuario FROM usuarios WHERE id_padre = ?)");
    $stmt->execute([$uid, $uid]);
    $stats['c4']['val'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM partidas WHERE (id_anfitrion = ? OR id_anfitrion IN (SELECT id_usuario FROM usuarios WHERE id_padre = ?)) AND estado IN ('sala_espera', 'jugando')");
    $stmt->execute([$uid, $uid]);
    $activeGames = $stmt->fetchColumn();

} elseif ($role == 6) { // Alumno
    $stats['c1'] = ['label' => __('dash_stat_games_played'), 'val' => 0, 'icon' => 'fa-trophy', 'color' => 'blue'];
    $stats['c2'] = ['label' => __('dash_stat_avg_score'), 'val' => 0, 'icon' => 'fa-star', 'color' => 'yellow'];
    $stats['c3'] = ['label' => __('dash_stat_total_responses'), 'val' => 0, 'icon' => 'fa-check-double', 'color' => 'green'];
    $stats['c4'] = ['label' => __('dash_stat_your_rankings'), 'val' => [], 'icon' => 'fa-ranking-star', 'color' => 'purple', 'is_ranking' => true];

    $stmt = $db->prepare("SELECT COUNT(DISTINCT id_partida) FROM jugadores_sesion WHERE id_usuario_registrado = ?");
    $stmt->execute([$uid]);
    $stats['c1']['val'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT AVG(puntuacion) FROM jugadores_sesion WHERE id_usuario_registrado = ?");
    $stmt->execute([$uid]);
    $avgScore = $stmt->fetchColumn() ?: 0;
    $stats['c2']['val'] = round($avgScore);

    $stmt = $db->prepare("SELECT COUNT(*) FROM respuestas_log r INNER JOIN jugadores_sesion j ON r.id_sesion = j.id_sesion WHERE j.id_usuario_registrado = ?");
    $stmt->execute([$uid]);
    $stats['c3']['val'] = $stmt->fetchColumn();

    // --- LÓGICA DE RANKINGS ---
    $stmtGlobal = $db->prepare("SELECT COUNT(*) + 1 FROM (SELECT AVG(puntuacion) as promedio FROM jugadores_sesion WHERE id_usuario_registrado IS NOT NULL GROUP BY id_usuario_registrado) as r WHERE promedio > ?");
    $stmtGlobal->execute([$avgScore]);
    $stats['c4']['val'][] = ['label' => __('dash_rank_global'), 'pos' => $stmtGlobal->fetchColumn()];

    $stmtParent = $db->prepare("SELECT u.id_padre, p.id_rol FROM usuarios u LEFT JOIN usuarios p ON u.id_padre = p.id_usuario WHERE u.id_usuario = ?");
    $stmtParent->execute([$uid]);
    $parent = $stmtParent->fetch(PDO::FETCH_ASSOC);

    if ($parent && $parent['id_padre']) {
        $idPadre = $parent['id_padre'];
        $rolPadre = $parent['id_rol'];
        if ($rolPadre == 3) {
            $stmtAcad = $db->prepare("SELECT id_padre FROM usuarios WHERE id_usuario = ?");
            $stmtAcad->execute([$idPadre]);
            $idPadre = $stmtAcad->fetchColumn() ?: $idPadre;
        }
        $stmtLocal = $db->prepare("
            SELECT COUNT(*) + 1 FROM (
                SELECT AVG(js.puntuacion) as promedio 
                FROM jugadores_sesion js
                JOIN usuarios u ON js.id_usuario_registrado = u.id_usuario
                WHERE u.id_padre = ? OR u.id_usuario = ?
                GROUP BY js.id_usuario_registrado
            ) as r WHERE promedio > ?
        ");
        $stmtLocal->execute([$idPadre, $idPadre, $avgScore]);
        $labelLocal = ($rolPadre == 4) ? __('dash_rank_professor') : __('dash_rank_academy');
        $stats['c4']['val'][] = ['label' => $labelLocal, 'pos' => $stmtLocal->fetchColumn()];
    }

} else { // Profesores
    $stats['c1']['label'] = __('dash_stat_my_students');
    $stats['c1']['val'] = "-"; 
    $stmt = $db->prepare("SELECT COUNT(*) FROM preguntas WHERE id_propietario = ?");
    $stmt->execute([$uid]);
    $stats['c3']['val'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM partidas WHERE id_anfitrion = ?");
    $stmt->execute([$uid]);
    $stats['c4']['val'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM partidas WHERE id_anfitrion = ? AND estado IN ('sala_espera', 'jugando')");
    $stmt->execute([$uid]);
    $activeGames = $stmt->fetchColumn();
}

// DATOS PARA GRÁFICOS
$chartLabels = [];
$chartData = [];
$chartTypeLabels = [];
$chartTypeData = [];

if ($role != 6) {
    $whereUser = ($role == 1) ? "1=1" : "id_anfitrion = $uid"; 
    $sqlDate = "SELECT DATE(fecha_inicio) as fecha, COUNT(*) as total FROM partidas 
                WHERE $whereUser AND fecha_inicio >= DATE(NOW()) - INTERVAL 7 DAY 
                GROUP BY DATE(fecha_inicio) ORDER BY fecha ASC";
    $resDate = $db->query($sqlDate)->fetchAll(PDO::FETCH_ASSOC);
    foreach($resDate as $r) {
        $chartLabels[] = date('d/m', strtotime($r['fecha']));
        $chartData[] = $r['total'];
    }

    $sqlMode = "SELECT m.nombre, COUNT(p.id_partida) as total 
                FROM partidas p JOIN modos_juego m ON p.id_modo = m.id_modo 
                WHERE $whereUser GROUP BY m.id_modo";
    $resMode = $db->query($sqlMode)->fetchAll(PDO::FETCH_ASSOC);
    foreach($resMode as $r) {
        $chartTypeLabels[] = $r['nombre'];
        $chartTypeData[] = $r['total'];
    }
} else {
    $sqlProgress = "SELECT DATE(ultima_conexion) as f, SUM(puntuacion) as total 
                    FROM jugadores_sesion 
                    WHERE id_usuario_registrado = $uid 
                    AND ultima_conexion >= DATE(NOW()) - INTERVAL 7 DAY 
                    GROUP BY DATE(ultima_conexion) 
                    ORDER BY f ASC";
    $resProgress = $db->query($sqlProgress)->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($resProgress as $p) {
        $chartLabels[] = date('d/m', strtotime($p['f']));
        $chartData[] = (int)$p['total'];
    }

    $sqlHits = "SELECT r.es_correcta, COUNT(*) as total 
                FROM respuestas_log r 
                JOIN jugadores_sesion j ON r.id_sesion = j.id_sesion 
                WHERE j.id_usuario_registrado = $uid 
                GROUP BY r.es_correcta";
    $resHits = $db->query($sqlHits)->fetchAll(PDO::FETCH_ASSOC);
    $aciertos = 0; $fallos = 0;
    foreach($resHits as $h) {
        if($h['es_correcta'] == 1) $aciertos = $h['total'];
        else $fallos = $h['total'];
    }
    $chartTypeLabels = [__('dash_chart_hits'), __('dash_chart_misses')];
    $chartTypeData = [$aciertos, $fallos];
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<section class="fade-in">
    <div class="dashboard-header">
        <div class="welcome-section">
            <h2 class="welcome-title">
                <?php echo __('dash_welcome'); ?> <?php echo $_SESSION['user_name']; ?>
            </h2>
            <p class="welcome-subtitle"><?php echo date('d/m/Y'); ?> | <?php echo htmlspecialchars($rol_nombre); ?></p>
        </div>
        
        <div class="header-actions" style="display: flex; gap: 10px; align-items: center;">
            <?php if($role == 6): ?>
                <a href="play/index.php" target="_blank" class="btn-primary" style="text-decoration: none;">
                    <i class="fa-solid fa-gamepad"></i> <span><?php echo __('dash_btn_join_game'); ?></span>
                </a>
            <?php else: ?>
                <?php if($activeGames > 0): ?>
                    <button class="active-games-card pulse-animation" onclick="window.location.href='partidas'" title="<?php echo __('dash_btn_active_games'); ?>" style="margin: 0; padding: 0.6rem 1rem; cursor:pointer;">
                        <div class="active-games-title" style="margin:0; border:none; padding:0;">
                            <i class="fa-solid fa-satellite-dish"></i> <?php echo $activeGames; ?>
                        </div>
                    </button>
                <?php endif; ?>

                <a href="partidas" class="btn-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-plus"></i> <span><?php echo __('dash_btn_new_game'); ?></span>
                </a>
                
                <?php if($role == 1 || $role == 2): ?>
                <a href="usuarios" class="btn-icon" style="border:1px solid var(--border-color); border-radius: var(--radius); width: auto; padding: 0.6rem 1.2rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; color: var(--text-color);">
                    <i class="fa-solid fa-user-plus"></i> <span><?php echo __('dash_btn_new_user'); ?></span>
                </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="stats-grid">
    <?php foreach($stats as $key => $s): ?>
    <div class="card stat-card stat-<?php echo $s['color']; ?>">
        <div class="stat-info">
            <p><?php echo $s['label']; ?></p>
            <?php if(isset($s['is_ranking']) && is_array($s['val'])): ?>
                <div class="ranking-container" style="margin-top: 5px;">
                    <?php foreach($s['val'] as $r): ?>
                        <div style="display: flex; justify-content: space-between; align-items: baseline; gap: 10px; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 2px 0;">
                            <span style="font-size: 0.85rem; opacity: 0.8;"><?php echo $r['label']; ?>:</span>
                            <span style="font-size: 1.1rem; font-weight: bold;">#<?php echo $r['pos']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <h3><?php echo $s['val']; ?></h3>
            <?php endif; ?>
        </div>
        <div class="stat-icon">
            <i class="fa-solid <?php echo $s['icon']; ?>"></i>
        </div>
    </div>
    <?php endforeach; ?>
</div>

    <div class="charts-layout">
        <div class="card">
            <h3 class="chart-header">
                <i class="fa-solid fa-chart-area"></i> 
                <?php echo ($role != 6) ? __('dash_chart_activity') : __('dash_chart_learning_progress'); ?>
            </h3>
            <div class="main-chart-container">
                <canvas id="mainChart"></canvas>
            </div>
        </div>

        <div class="card chart-card-wrapper">
            <h3 class="chart-header">
                <i class="fa-solid fa-chart-pie"></i> 
                <?php echo ($role != 6) ? __('dash_chart_modes') : __('dash_chart_performance'); ?>
            </h3>
            <div class="donut-chart-container">
                <canvas id="secondaryChart"></canvas>
            </div>
        </div>
    </div>
</section>

<script>
    const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#6366f1';
    
    const ctxMain = document.getElementById('mainChart').getContext('2d');
    const mainChart = new Chart(ctxMain, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartLabels ?: [__('key_no_data')]); ?>,
            datasets: [{
                label: '<?php echo ($role != 6) ? __("dash_label_games_created") : __("dash_label_points"); ?>',
                data: <?php echo json_encode($chartData ?: [0]); ?>,
                borderColor: primaryColor,
                backgroundColor: primaryColor + '15',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointRadius: 0,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: { 
                y: { 
                    beginAtZero: true, 
                    grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false, borderDash: [5, 5] } 
                }, 
                x: { grid: { display: false } } 
            }
        }
    });

    const ctxSec = document.getElementById('secondaryChart').getContext('2d');
    const secChart = new Chart(ctxSec, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($chartTypeLabels ?: [__('key_no_data')]); ?>,
            datasets: [{
                data: <?php echo json_encode($chartTypeData ?: [1]); ?>,
                backgroundColor: ['#3b82f6', '#ef4444', '#22c55e', '#eab308', '#a855f7'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } 
            }
        }
    });
</script>