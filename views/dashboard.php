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
    $rol_nombre = $stmtRol->fetchColumn() ?: 'Usuario';
    $_SESSION['rol_nombre'] = $rol_nombre; 
}

// Inicializar contadores
// Nota: Usamos nombres de colores simples ('blue', 'green') que coinciden con las clases CSS .stat-blue, etc.
$stats = [
    'c1' => ['label' => __('dash_stat_teachers'), 'val' => 0, 'icon' => 'fa-chalkboard-user', 'color' => 'blue'],
    'c2' => ['label' => __('dash_stat_students'), 'val' => 0, 'icon' => 'fa-graduation-cap', 'color' => 'green'],
    'c3' => ['label' => __('dash_stat_questions'), 'val' => 0, 'icon' => 'fa-circle-question', 'color' => 'purple'],
    'c4' => ['label' => __('dash_stat_games'), 'val' => 0, 'icon' => 'fa-gamepad', 'color' => 'orange']
];
$activeGames = 0;

// Consultas SQL según Rol (Lógica intacta)
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
    $stats['c3'] = ['label' => 'Total Respuestas', 'val' => 0, 'icon' => 'fa-check-double', 'color' => 'green'];
    $stats['c4'] = ['label' => 'Ranking Global', 'val' => '#-', 'icon' => 'fa-ranking-star', 'color' => 'purple'];

    $stmt = $db->prepare("SELECT COUNT(DISTINCT id_partida) FROM jugadores_sesion WHERE id_usuario_registrado = ?");
    $stmt->execute([$uid]);
    $stats['c1']['val'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT AVG(puntuacion) FROM jugadores_sesion WHERE id_usuario_registrado = ?");
    $stmt->execute([$uid]);
    $stats['c2']['val'] = round($stmt->fetchColumn() ?: 0);

    $stmt = $db->prepare("SELECT COUNT(*) FROM respuestas_log r INNER JOIN jugadores_sesion j ON r.id_sesion = j.id_sesion WHERE j.id_usuario_registrado = ?");
    $stmt->execute([$uid]);
    $stats['c3']['val'] = $stmt->fetchColumn();

} else { // Profesores
    $stats['c1']['label'] = "Mis Alumnos";
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

// DATOS PARA GRÁFICOS (Lógica intacta)
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
    $chartTypeLabels = ['Aciertos', 'Fallos'];
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
        
        <div class="header-actions">
            <?php if($role == 6): ?>
                <a href="play/index.php" target="_blank" class="btn-primary" style="text-decoration: none;">
                    <i class="fa-solid fa-gamepad"></i> Unirse a Partida
                </a>
            <?php elseif($activeGames > 0): ?>
                <button class="active-games-card pulse-animation" onclick="window.location.href='partidas'">
                    <div class="active-games-title">
                        <i class="fa-solid fa-satellite-dish"></i> <?php echo $activeGames; ?> <?php echo __('dash_stat_active_games'); ?>
                    </div>
                    <span class="active-games-arrow">Ir a Partidas &rarr;</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="stats-grid">
        <?php foreach($stats as $key => $s): ?>
        <div class="card stat-card stat-<?php echo $s['color']; ?>">
            <div class="stat-info">
                <p><?php echo $s['label']; ?></p>
                <h3><?php echo $s['val']; ?></h3>
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
                <?php echo ($role != 6) ? __('dash_chart_activity') : 'Progreso de Aprendizaje'; ?>
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
            
            <div class="quick-actions-footer">
                <h4 class="quick-actions-label"><?php echo __('dash_quick_actions'); ?></h4>
                <div class="quick-actions-buttons">
                    <?php if($role == 6): ?>
                        <a href="partidas" class="btn-action-quick btn-action-green">
                            <i class="fa-solid fa-clock-rotate-left"></i> Mi Historial
                        </a>
                    <?php else: ?>
                        <a href="partidas" class="btn-action-quick btn-action-blue">
                            <i class="fa-solid fa-plus"></i> <?php echo __('dash_btn_new_game'); ?>
                        </a>
                        <?php if($role == 1 || $role == 2): ?>
                        <a href="usuarios" class="btn-action-quick btn-action-green">
                            <i class="fa-solid fa-user-plus"></i> <?php echo __('dash_btn_new_user'); ?>
                        </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    // Obtener color primario de las variables CSS para los gráficos
    const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#6366f1';
    
    // --- GRÁFICO PRINCIPAL ---
    const ctxMain = document.getElementById('mainChart').getContext('2d');
    const mainChart = new Chart(ctxMain, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartLabels ?: ['Sin datos']); ?>,
            datasets: [{
                label: '<?php echo ($role != 6) ? "Partidas Creadas" : "Puntos"; ?>',
                data: <?php echo json_encode($chartData ?: [0]); ?>,
                borderColor: primaryColor,
                backgroundColor: primaryColor + '20', // Opacidad 20%
                borderWidth: 2,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, grid: { color: '#e5e7eb' } }, x: { grid: { display: false } } }
        }
    });

    // --- GRÁFICO SECUNDARIO ---
    const ctxSec = document.getElementById('secondaryChart').getContext('2d');
    const secChart = new Chart(ctxSec, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($chartTypeLabels ?: ['Sin datos']); ?>,
            datasets: [{
                data: <?php echo json_encode($chartTypeData ?: [1]); ?>,
                backgroundColor: [
                    '#3b82f6', '#ef4444', '#22c55e', '#eab308', '#a855f7'
                ],
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