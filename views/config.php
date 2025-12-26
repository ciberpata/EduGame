<?php
// views/config.php
// NOTA: Este fichero NO debe contener el <header>, ya que se carga desde index.php
// Asumimos que el contenido original que tenías duplicado (el header) era un error.
?>

<section class="contenedor-configuracion animacion-entrada">
    <div class="tarjeta">
        <header class="encabezado-tarjeta">
            <h2 class="titulo-seccion">
                <i class="fa-solid fa-gears" aria-hidden="true"></i>
                <?php echo __('config') ?? 'Configuración'; ?>
            </h2>
            <p class="texto-secundario">Personaliza la apariencia y preferencias de tu aplicación.</p>
        </header>
        
        <hr class="separador">

        <article class="articulo-config">
            <h3 class="subtitulo-config">Apariencia Visual</h3>
            
            <div class="grupo-formulario">
                <label class="etiqueta-bloque">Color del Tema (Temporal)</label>
                <fieldset class="selector-temas" role="radiogroup" aria-label="Seleccionar color de acento">
                    <button onclick="cambiarColorTema(210)" class="muestra-color tema-azul" aria-label="Tema Azul" title="Azul"></button>
                    <button onclick="cambiarColorTema(270)" class="muestra-color tema-purpura" aria-label="Tema Púrpura" title="Púrpura"></button>
                    <button onclick="cambiarColorTema(142)" class="muestra-color tema-verde" aria-label="Tema Verde" title="Verde"></button>
                    <button onclick="cambiarColorTema(35)" class="muestra-color tema-naranja" aria-label="Tema Naranja" title="Naranja"></button>
                    <button onclick="cambiarColorTema(0)" class="muestra-color tema-rojo" aria-label="Tema Rojo" title="Rojo"></button>
                </fieldset>
                <small class="texto-ayuda">Selecciona el color principal para esta sesión. Para guardarlo permanentemente, ve a "Mi Perfil" > "Apariencia".</small>
            </div>

            <div class="grupo-formulario mt-4">
                <label class="etiqueta-interruptor">
                    <input type="checkbox" id="checkModoOscuro" onchange="alternarModoOscuroDesdeConfig()">
                    <span class="deslizador"></span>
                    <span class="texto-interruptor">Activar Modo Oscuro</span>
                </label>
            </div>
        </article>
    </div>
</section>

<script>
    // Se ejecuta al cargar esta vista específica
    document.addEventListener('DOMContentLoaded', () => {
        const esOscuro = document.body.classList.contains('dark');
        const check = document.getElementById('checkModoOscuro');
        if(check) check.checked = esOscuro;
    });

    function cambiarColorTema(hue) {
        // --- CORRECCIÓN ---
        // Usamos sessionStorage para que sea temporal, igual que el header.
        // localStorage.setItem('theme_color', hue); // <-- ESTA ERA LA LÍNEA DEL BUG
        sessionStorage.setItem('temp_theme_color', hue); // <-- ESTA ES LA CORRECCIÓN
        
        document.documentElement.style.setProperty('--hue', hue);
    }

    function alternarModoOscuroDesdeConfig() {
        // Llama a la función global (que debería estar en app.js o header.php)
        if (typeof toggleTheme === 'function') {
            toggleTheme();
        } else {
            // Fallback por si toggleTheme() no está definida globalmente
            document.body.classList.toggle('dark');
            const isDark = document.body.classList.contains('dark');
            localStorage.setItem('theme_mode', isDark ? 'dark' : 'light');
        }
    }
</script>