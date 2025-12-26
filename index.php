<?php
// index.php
session_start();

// --- 1. SISTEMA DE RUTAS (ROUTER) ---
$view = $_GET['view'] ?? 'dashboard'; 

if (!isset($_GET['view'])) {
    $request_uri = strtok($_SERVER['REQUEST_URI'], '?');
    $script_name = $_SERVER['SCRIPT_NAME'];
    $base_path = str_replace('/index.php', '', $script_name);
    $route = str_replace($base_path, '', $request_uri);
    $clean_route = trim($route, '/');
    if ($clean_route && $clean_route !== 'index.php') {
        $view = $clean_route;
    }
}

// --- 2. ACCIONES PÚBLICAS Y LOGIN ---

if ($view === 'logout') {
    session_unset();
    session_destroy();
    header("Location: login");
    exit();
}

if ($view === 'reset') {
    if (isset($_SESSION['user_id'])) { session_unset(); session_destroy(); session_start(); }
    require 'views/reset.php'; 
    exit();
}

if ($view === 'login') {
    if (isset($_SESSION['user_id'])) { 
        header("Location: dashboard"); 
        exit(); 
    }
    require 'login.php'; 
    exit(); // <--- IMPORTANTE: Detiene la ejecución aquí.
}

// --- 3. SEGURIDAD ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login"); 
    exit();
}

// --- 4. IDIOMAS ---
$supported_langs = ['es' => 'Español', 'gl' => 'Galego', 'en' => 'Inglés'];
$current_lang = $_SESSION['lang'] ?? 'es';

if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $supported_langs)) {
    $_SESSION['lang'] = $_GET['lang'];
    $current_lang = $_GET['lang'];
    $query_params = $_GET;
    unset($query_params['lang']);
    $redirect_url = strtok($_SERVER['REQUEST_URI'], '?');
    if (!empty($query_params)) $redirect_url .= '?' . http_build_query($query_params);
    header("Location: " . $redirect_url);
    exit();
} elseif (isset($_SESSION['lang']) && array_key_exists($_SESSION['lang'], $supported_langs)) {
    $current_lang = $_SESSION['lang'];
}

$trans_file = __DIR__ . "/locales/i18n.{$current_lang}.json";
$translations = file_exists($trans_file) ? json_decode(file_get_contents($trans_file), true) : [];
function __($key) { global $translations; return $translations[$key] ?? $key; }

// --- 5. RENDERIZADO DEL DASHBOARD ---
$allowed_views = ['dashboard', 'usuarios', 'partidas', 'preguntas', 'config', 'facturacion', 'perfil', 'proyector', 'auditoria', 'diagnostico', 'limpieza', 'planes'];
if ($view === 'panel-de-control') $view = 'dashboard';
if (!in_array($view, $allowed_views)) $view = 'dashboard';

if (!class_exists('Database')) include 'config/db.php';
$isCleanView = ($view === 'proyector');
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduGame - <?php echo ucfirst($view); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=2.3">
    <script>
        if(localStorage.getItem('theme_mode') === 'dark') document.body.classList.add('dark');
        const savedHue = sessionStorage.getItem('temp_theme_color');
        if(savedHue) document.documentElement.style.setProperty('--hue', savedHue);
    </script>
</head>
<body>
    <?php if (!$isCleanView) include 'includes/sidebar.php'; ?>

    <main class="<?php echo $isCleanView ? '' : 'main-content'; ?>">
        <?php if (!$isCleanView) include 'includes/header.php'; ?>

        <div class="content-scroll-area">
            <?php 
            $file = "views/{$view}.php";
            if (file_exists($file)) include $file;
            else echo "<div class='card'><h2>Error 404</h2><p>Vista no encontrada.</p></div>";
            ?>
        </div>
    </main>
    <script src="assets/js/app.js"></script>
</body>
</html>