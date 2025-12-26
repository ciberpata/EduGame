<?php
// includes/sidebar.php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['user_role'] ?? 0;

function isActive($viewName) {
    global $view; 
    return $view === $viewName ? 'active' : '';
}
?>
<style>
    /* Compactar cabecera */
    .sidebar-header {
        padding: 1rem 1.5rem !important; /* Menos altura vertical */
    }
    
    /* Compactar pie de página (Perfil) para bajar la barra de separación */
    .sidebar-footer {
        padding: 0.75rem 1rem !important; /* Más delgado */
    }

    /* Reducir separación vertical de los elementos del menú */
    .sidebar .nav-item {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
        margin-bottom: 1px !important; /* Mínima separación */
        font-size: 0.95rem; /* Ajuste sutil de fuente si es necesario */
    }
    
    /* Reducir separación de las etiquetas de grupo (Herramientas) */
    .sidebar .nav-group-label {
        margin-top: 0.5rem !important;
        margin-bottom: 0.2rem !important;
        padding-top: 0 !important;
    }

    /* Ocultar visualmente la barra de desplazamiento pero permitir hacer scroll si la pantalla es muy pequeña */
    #sidebar-nav {
        overflow-y: auto;       
        scrollbar-width: none;  /* Firefox */
        -ms-overflow-style: none; /* IE/Edge */
    }
    #sidebar-nav::-webkit-scrollbar {
        display: none; /* Chrome/Safari */
    }
</style>

<aside class="sidebar" id="sidebar" style="display: flex; flex-direction: column; height: 100vh;">
    <div class="sidebar-header" style="text-align: center; border-bottom: 1px solid var(--border-color); flex-shrink: 0;">
        <h2 style="color: var(--primary); font-size: 1.5rem; margin: 0;">
            <i class="fa-solid fa-gamepad"></i> EduGame
        </h2>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 5px;">
            <?php echo $_SESSION['user_name'] ?? 'Usuario'; ?>
        </div>
    </div>

    <nav id="sidebar-nav" style="flex: 1; padding: 0.5rem 0;">
        
        <a href="panel-de-control" class="nav-item <?php echo isActive('panel-de-control'); ?>">
            <i class="fa-solid fa-chart-line"></i> 
            <?php echo __('control_panel'); ?>
        </a>

        <?php if (in_array($role, [1, 2])): ?>
        <a href="usuarios" class="nav-item <?php echo isActive('usuarios'); ?>">
            <i class="fa-solid fa-users"></i> 
            <?php echo ($role == 1) ? __('user_management') : __('user_management'); ?>
        </a>
        <?php endif; ?>

        <a href="partidas" class="nav-item <?php echo isActive('partidas'); ?>">
            <i class="fa-solid fa-play"></i> 
            <?php echo ($role == 6) ? 'Mi Historial' : __('game_management'); ?>
        </a>

        <?php if ($role != 6): // No alumnos ?>
        <a href="preguntas" class="nav-item <?php echo isActive('preguntas'); ?>">
            <i class="fa-solid fa-circle-question"></i> 
            <?php echo __('question_management'); ?>
        </a>
        <?php endif; ?>
        
        <?php if ($role != 6): // No mostrar a alumnos ?>
        <a href="auditoria" class="nav-item <?php echo isActive('auditoria'); ?>">
            <i class="fa-solid fa-clipboard-list"></i> 
            <?php echo __('audit'); ?>
        </a>
        <?php endif; ?>

        <?php if (in_array($role, [1, 2, 4])): // Admin, Academia, Profe Indep ?>
        <div class="nav-group-label" style="padding-left: 1.5rem; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: bold;">
            <i class="fa-solid fa-toolbox"></i> 
            <?php echo __('tools_title'); ?>
        </div>
        <a href="diagnostico" class="nav-item <?php echo isActive('diagnostico'); ?>" style="padding-left: 2.5rem;">
            <i class="fa-solid fa-heart-pulse"></i>
            <?php echo __('diag_title'); ?>
        </a>
        <?php if ($role == 1): ?>
        <a href="limpieza" class="nav-item <?php echo isActive('limpieza'); ?>" style="padding-left: 2.5rem;">
            <i class="fa-solid fa-broom"></i>
            <?php echo __('tools_cleaner'); ?>
        </a>
		<a href="planes" class="nav-item <?php echo isActive('planes'); ?>" style="padding-left: 2.5rem;">
            <i class="fa-solid fa-tags"></i>
            <?php echo __('plans_title'); ?>
        </a>
        <?php endif; ?>
        <?php endif; ?>

    </nav>

    <div class="sidebar-footer" style="border-top: 1px solid var(--border-color); flex-shrink: 0;">
        <a href="perfil" class="nav-item <?php echo isActive('perfil'); ?>" style="border-radius: var(--radius);">
            <?php if(isset($_SESSION['user_photo']) && !empty($_SESSION['user_photo'])): ?>
                <img src="<?php echo $_SESSION['user_photo'].'?t='.time(); ?>" alt="Profile" style="width:30px; height:30px; border-radius:50%; margin-right:10px; object-fit:cover;">
            <?php else: ?>
                <i class="fa-solid fa-user-circle" style="margin-right: 10px;"></i> 
            <?php endif; ?>
            <span><?php echo __('my_profile'); ?></span>
        </a>
    </div>
</aside>