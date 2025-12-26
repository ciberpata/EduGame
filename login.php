<?php
// login.php
session_start();

$supported_langs = ['es' => 'Español', 'gl' => 'Galego', 'en' => 'Inglés'];

if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $supported_langs)) {
    $_SESSION['lang'] = $_GET['lang'];
    $current_lang = $_GET['lang'];
} elseif (isset($_SESSION['lang']) && array_key_exists($_SESSION['lang'], $supported_langs)) {
    $current_lang = $_SESSION['lang'];
} else {
    $current_lang = 'es';
}

$lang_file_path = "locales/i18n.{$current_lang}.json";
$trans = file_exists($lang_file_path) ? json_decode(file_get_contents($lang_file_path), true) : json_decode(file_get_contents("locales/i18n.es.json"), true);

if (isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduGame - <?php echo $trans['login_title']; ?></title>
    
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- ESTILOS ESPECÍFICOS PARA ESTA PÁGINA --- */
        /* Aseguran que el login y el modal se vean bien sin depender del style.css general */
        
        /* 1. Selector de Idioma Flotante */
        .lang-selector-wrapper { position: absolute; top: 1.5rem; right: 1.5rem; z-index: 10; }
        .lang-select { 
            appearance: none; -webkit-appearance: none;
            background: white; border: 1px solid #e2e8f0; 
            padding: 8px 15px; border-radius: 20px; 
            cursor: pointer; color: #333; font-size: 0.9rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        /* 2. Enlaces de texto */
        .btn-text-link { 
            background: none; border: none; 
            color: #3b82f6; cursor: pointer; 
            text-decoration: none; font-size: 0.95rem; 
        }
        .btn-text-link:hover { text-decoration: underline; color: #2563eb; }

        /* 3. MODAL (Estilos forzados para garantizar visualización) */
        .modal-overlay {
            display: none; /* Oculto por defecto */
            position: fixed; /* Flotante */
            z-index: 10000; /* Encima de todo */
            left: 0; top: 0;
            width: 100%; height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5); /* Fondo oscuro semitransparente */
            backdrop-filter: blur(4px); /* Efecto borroso */
            align-items: center; justify-content: center;
        }
        
        .modal-box {
            background-color: #fff;
            padding: 2.5rem;
            border-radius: 0.75rem;
            width: 90%; max-width: 450px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            position: relative;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        /* Ajustes responsive */
        @media (max-width: 480px) {
            .centered-layout { display: block !important; padding-top: 5rem; }
            .login-container { width: 100% !important; box-shadow: none !important; border: none !important; background: transparent !important; }
        }
    </style>
</head>
<body class="centered-layout"> 
    
    <div class="lang-selector-wrapper">
        <select class="lang-select" onchange="window.location.href='?lang='+this.value">
            <?php foreach ($supported_langs as $code => $name): ?>
                <option value="<?php echo $code; ?>" <?php echo $code === $current_lang ? 'selected' : ''; ?>>
                    <?php echo ($code == 'es' ? '🇪🇸' : ($code == 'en' ? '🇬🇧' : '🇪🇸')) . ' ' . $name; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="login-container">
        <div class="login-header-wrapper">
            <div class="login-logo-circle">
                <i class="fa-solid fa-gamepad"></i>
            </div>
            <h1 class="login-brand-title">EduGame</h1>
            <p style="color:#64748b; margin-top:-5px;">Gamificación Educativa</p>
        </div>

        <div id="alertBox" class="login-alert hidden"></div>

        <form id="loginForm">
            <input type="hidden" name="lang" id="formLang" value="<?php echo $current_lang; ?>">
            
            <div style="margin-bottom: 1rem;">
                <label style="display:block; color:#64748b; margin-bottom:0.5rem;"><?php echo $trans['email_label']; ?></label>
                <input type="email" name="correo" class="form-control" style="width:100%; padding:0.7rem; border:1px solid #e2e8f0; border-radius:0.5rem;" required autofocus>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; color:#64748b; margin-bottom:0.5rem;"><?php echo $trans['password_label']; ?></label>
                <input type="password" name="contrasena" class="form-control" style="width:100%; padding:0.7rem; border:1px solid #e2e8f0; border-radius:0.5rem;" required>
            </div>

            <button type="submit" class="btn-primary btn-full-width">
                <?php echo $trans['btn_login']; ?>
            </button>
        </form>
        
        <div style="text-align:center; margin-top:1.5rem;">
            <button type="button" onclick="openRecoverModal()" class="btn-text-link">
                <?php echo $trans['forgot_password']; ?>
            </button>
        </div>
    </div>

    <div id="recoverModalOverlay" class="modal-overlay">
        <div class="modal-box text-center">
            
            <div id="recoverStep1">
                <h3 style="margin-top:0; color:#333; margin-bottom:1rem;"><?php echo $trans['modal_recover_title']; ?></h3>
                <p style="color:#64748b; font-size:0.95rem; margin-bottom:1.5rem;"><?php echo $trans['modal_recover_desc']; ?></p>
                
                <form id="recoverForm">
                    <input type="email" name="rec_correo" class="form-control" placeholder="nombre@email.com" required style="width:100%; padding:0.7rem; border:1px solid #e2e8f0; border-radius:0.5rem; margin-bottom:1.5rem;">
                    
                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" onclick="closeRecoverModal()" style="background:transparent; border:1px solid #cbd5e1; color:#64748b; padding:0.6rem 1.2rem; border-radius:0.5rem; cursor:pointer;">
                            <?php echo $trans['key_btn_cancel'] ?? 'Cancelar'; ?>
                        </button>
                        <button type="submit" class="btn-primary" style="padding:0.6rem 1.2rem; border-radius:0.5rem;">
                            <?php echo $trans['btn_send']; ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <div id="recoverStep2" class="hidden" style="text-align: center;">
               <div style="font-size: 3rem; color: #22c55e; margin-bottom: 1rem;">
                    <i class="fa-solid fa-envelope-circle-check"></i>
               </div>
               <h3 style="color:#22c55e; margin:0 0 0.5rem 0;">¡Enviado!</h3>
               <p style="color:#64748b; margin-bottom:1.5rem;"><?php echo $trans['success_email_sent']; ?></p>
               <button onclick="closeRecoverModal()" class="btn-primary" style="padding:0.6rem 2rem; border-radius:0.5rem;">OK</button>
            </div>

        </div>
    </div>

    <script>
        // --- LÓGICA DE LOGIN ---
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const alertBox = document.getElementById('alertBox');
            const originalText = btn.innerText;
            const lang = document.getElementById('formLang').value; 

            btn.disabled = true;
            btn.innerText = "<?php echo $trans['btn_loading']; ?>";
            alertBox.classList.add('hidden');

            const formData = { correo: e.target.correo.value, contrasena: e.target.contrasena.value, lang: lang };
            
            try {
                const res = await fetch('api/login_handler.php', {
                    method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(formData)
                });
                const data = await res.json();

                if (res.ok && data.success) {
                    window.location.href = data.redirect || 'index.php';
                } else {
                    let errorMsg = data.error || "<?php echo $trans['unknown_error']; ?>";
                    if (res.status === 401 || data.error === "Credenciales inválidas") errorMsg = "<?php echo $trans['error_credentials']; ?>";
                    else if (res.status === 403) errorMsg = "<?php echo $trans['error_inactive']; ?>";
                    throw new Error(errorMsg);
                }
            } catch (err) {
                alertBox.innerText = err.message;
                alertBox.classList.remove('hidden');
                alertBox.style.display = 'block';
                btn.disabled = false;
                btn.innerText = originalText;
            }
        });

        // --- LÓGICA DEL MODAL ---
        const modalOverlay = document.getElementById('recoverModalOverlay');

        // Abre el modal forzando display flex para que se centre y muestre
        window.openRecoverModal = function() {
            modalOverlay.style.display = 'flex';
            document.getElementById('recoverStep1').style.display = 'block';
            document.getElementById('recoverStep2').classList.add('hidden');
            document.getElementById('recoverStep2').style.display = 'none';
        };

        // Cierra el modal
        window.closeRecoverModal = function() {
            modalOverlay.style.display = 'none';
        };

        // Cerrar si se hace clic en el fondo oscuro
        window.onclick = function(event) {
            if (event.target == modalOverlay) {
                window.closeRecoverModal();
            }
        };

        // Envío del formulario de recuperación
        document.getElementById('recoverForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            // Aquí iría tu lógica de fetch real a api/recuperar_pass.php
            
            // Simulamos éxito visual inmediato
            document.getElementById('recoverStep1').style.display = 'none';
            document.getElementById('recoverStep2').classList.remove('hidden');
            document.getElementById('recoverStep2').style.display = 'block';
        });
    </script>
</body>
</html>