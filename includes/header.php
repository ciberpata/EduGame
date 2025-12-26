<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Configuración básica
$vista = $_GET['view'] ?? 'dashboard';
$lang = $_SESSION['lang'] ?? 'es';
$tema_db = $_SESSION['tema_pref'] ?? '210'; 

// Datos para el título e icono según la vista
$headerData = [
    'dashboard' => ['icon' => 'fa-chart-line', 'title' => __('control_panel')],
    'usuarios' => ['icon' => 'fa-users', 'title' => __('user_management')],
    'partidas' => ['icon' => 'fa-play', 'title' => __('game_management')],
    'preguntas' => ['icon' => 'fa-circle-question', 'title' => __('question_management')],
    'facturacion' => ['icon' => 'fa-file-invoice-dollar', 'title' => __('billing')],
    'perfil' => ['icon' => 'fa-user-circle', 'title' => __('my_profile')],
    'config' => ['icon' => 'fa-gears', 'title' => __('configuration')],
    'proyector' => ['icon' => 'fa-tv', 'title' => 'Proyector']
];

$currentIcon = $headerData[$vista]['icon'] ?? 'fa-folder';
$currentTitle = $headerData[$vista]['title'] ?? ucfirst($vista);
?>

<script>
    (function() {
        const dbTheme = "<?php echo $tema_db; ?>";
        const sessionTheme = sessionStorage.getItem('temp_theme_color');
        document.documentElement.style.setProperty('--hue', sessionTheme || dbTheme);
    })();
</script>

<header class="top-header">
    
    <div class="header-left">
        <button id="sidebarToggle" class="btn-icon mobile-only" title="Abrir Menú">
            <i class="fa-solid fa-bars"></i>
        </button>
        
        <h1 class="header-page-title">
            <i class="fa-solid <?php echo $currentIcon; ?>"></i> 
            <span><?php echo $currentTitle; ?></span>
        </h1>
    </div>

    <div class="header-actions">
        
        <div class="dropdown-wrapper">
            <button class="btn-icon btn-mini" onclick="toggleHeaderMenu('langMenu')" title="Cambiar Idioma">
                <i class="fa-solid fa-globe"></i>
            </button>
            <div id="langMenu" class="dropdown-content mini-menu">
                <a href="#" onclick="changeLanguage('es')">🇪🇸 Español</a>
                <a href="#" onclick="changeLanguage('gl')">🌾 Galego</a>
                <a href="#" onclick="changeLanguage('en')">🇬🇧 English</a>
            </div>
        </div>

        <div class="dropdown-wrapper">
            <button class="btn-icon btn-mini" onclick="toggleHeaderMenu('colorMenu')" title="Tema de Color">
                <i class="fa-solid fa-palette"></i>
            </button>
            <div id="colorMenu" class="dropdown-content color-grid">
                <button class="color-dot" style="background:#2563eb" onclick="setSessionTheme('210')"></button>
                <button class="color-dot" style="background:#7c3aed" onclick="setSessionTheme('270')"></button>
                <button class="color-dot" style="background:#16a34a" onclick="setSessionTheme('142')"></button>
                <button class="color-dot" style="background:#f59e0b" onclick="setSessionTheme('35')"></button>
                <button class="color-dot" style="background:#ef4444" onclick="setSessionTheme('0')"></button>
                <button class="color-dot" style="background:#d946ef" onclick="setSessionTheme('300')"></button>
            </div>
        </div>

        <button onclick="toggleTheme()" class="btn-icon btn-mini" title="<?php echo __('night_mode'); ?>">
            <i class="fa-solid fa-moon"></i>
        </button>

        <a href="logout" onclick="sessionStorage.clear();" class="btn-icon btn-mini btn-logout-custom" title="Cerrar Sesión">
            <i class="fa-solid fa-power-off"></i>
        </a>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. FUNCIONALIDAD DEL BURGUER (Sidebar)
    const toggleBtn = document.getElementById('sidebarToggle');
    // Buscamos la sidebar y el overlay (fondo oscuro)
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay'); // Asegúrate de que este div exista en tu layout principal

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation(); // Evita que el clic se propague
            sidebar.classList.toggle('active');
            
            // Si tienes un overlay, actívalo también
            if (overlay) overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
        });

        // Cerrar al hacer clic fuera (en el documento o overlay)
        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('active') && 
                !sidebar.contains(e.target) && 
                !toggleBtn.contains(e.target)) {
                
                sidebar.classList.remove('active');
                if (overlay) overlay.style.display = 'none';
            }
        });
    }

    // 2. FUNCIONALIDAD DE MENÚS DESPLEGABLES (Header)
    window.toggleHeaderMenu = function(menuId) {
        // Cierra otros menús primero
        document.querySelectorAll('.dropdown-content').forEach(menu => {
            if (menu.id !== menuId) menu.classList.remove('show');
        });

        // Abre el actual
        const menu = document.getElementById(menuId);
        if (menu) {
            menu.classList.toggle('show');
        }
    }

    // Cerrar menús al hacer clic fuera
    window.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-wrapper') && !e.target.closest('.btn-mini')) {
            document.querySelectorAll('.dropdown-content').forEach(el => el.classList.remove('show'));
        }
    });

    // 3. FUNCIONES AUXILIARES
    window.changeLanguage = function(lang) {
        const url = new URL(window.location.href);
        url.searchParams.set('lang', lang);
        window.location.href = url.toString();
    }

    window.setSessionTheme = function(hue) {
        document.documentElement.style.setProperty('--hue', hue);
        sessionStorage.setItem('temp_theme_color', hue);
        document.getElementById('colorMenu').classList.remove('show');
    }
});
</script>