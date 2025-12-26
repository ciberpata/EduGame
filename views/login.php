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
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="centered-layout"> 
    <div class="lang-selector">
        <select onchange="changeLanguage(this.value)">
            <?php foreach ($supported_langs as $code => $name): ?>
                <option value="<?php echo $code; ?>" <?php echo $code === $current_lang ? 'selected' : ''; ?>>
                    <?php echo ($code == 'es' ? '🇪🇸' : ($code == 'en' ? '🇬🇧' : '🇪🇸')) . ' ' . $name; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="card login-container">
        <div class="text-center mb-5" style="display: flex; flex-direction: column; align-items: center; gap: 10px;">
            <div style="width: 80px; height: 80px; background: var(--primary-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 40px; box-shadow: var(--shadow-md);">
                <i class="fa-solid fa-gamepad"></i>
            </div>
            <div>
                <h1 style="color: var(--primary); font-weight: 800; margin: 0; letter-spacing: -1px;">EduGame</h1>
                <p class="text-muted" style="margin: 0; font-size: 0.9rem;">Plataforma de Gamificación Educativa</p>
            </div>
        </div>
        <div id="alertBox" class="hidden" style="background:#fee2e2; color:#991b1b; padding:0.8rem; border-radius:0.5rem; margin-bottom:1rem; text-align:center;"></div>

        <form id="loginForm">
            <input type="hidden" name="lang" id="formLang" value="<?php echo $current_lang; ?>">
            <div class="mb-4">
                <label class="block text-muted mb-2"><?php echo $trans['email_label']; ?></label>
                <input type="email" name="correo" class="form-control" required autofocus>
            </div>
            <div class="mb-4">
                <label class="block text-muted mb-2"><?php echo $trans['password_label']; ?></label>
                <input type="password" name="contrasena" class="form-control" required>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;">
                <?php echo $trans['btn_login']; ?>
            </button>
        </form>
        
        <div class="text-center mt-4">
            <button type="button" onclick="openModal()" class="btn-icon" style="color:var(--text-muted); font-size:0.9rem; text-decoration:underline; width:auto;">
                <?php echo $trans['forgot_password']; ?>
            </button>
        </div>
    </div>

    <div id="recoverModal" class="modal">
        <div class="modal-content text-center">
            <div id="recoverStep1">
                <h3 class="mb-4"><?php echo $trans['modal_recover_title']; ?></h3>
                <p class="text-muted mb-4"><?php echo $trans['modal_recover_desc']; ?></p>
                <form id="recoverForm">
                    <input type="email" name="rec_correo" class="form-control mb-4" placeholder="usuario@ejemplo.com" required>
                    <input type="hidden" name="lang_pref" value="<?php echo $current_lang; ?>">
                    <div style="display:flex; gap:10px; justify-content:center;">
                        <button type="button" onclick="closeModal()" class="btn-icon" style="border:1px solid var(--border-color); border-radius:var(--radius); width:auto; padding: 0.6rem 1.2rem;"><?php echo $trans['key_btn_cancel']; ?></button>
                        <button type="submit" class="btn-primary"><?php echo $trans['btn_send']; ?></button>
                    </div>
                </form>
                <div id="recoverError" class="mt-3 text-center hidden" style="color:var(--danger-color)"></div>
            </div>
            <div id="recoverStep2" class="hidden">
               <h3 style="color:var(--success-color); margin-top:1rem;">¡Enviado!</h3>
               <p class="text-muted mt-2 mb-4"><?php echo $trans['success_email_sent']; ?></p>
               <button onclick="closeModal()" class="btn-primary" style="background:var(--success-color);">OK</button>
            </div>
        </div>
    </div>
    <script>
        function changeLanguage(lang) {
            const url = new URL(window.location.href);
            url.searchParams.set('lang', lang);
            window.location.href = url.toString();
        }

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
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(formData)
                });
                
                // CORRECCIÓN 5: Leer siempre el JSON, incluso si es 401 o 403
                let data;
                try {
                    data = await res.json();
                } catch (parseError) {
                    throw new Error("Error de comunicación con el servidor (JSON inválido).");
                }

                if (res.ok && data.success) {
                    window.location.href = data.redirect;
                } else {
                    // Determinar mensaje de error
                    let errorMsg = data.error || "<?php echo $trans['unknown_error']; ?>";
                    
                    // Traducir mensajes conocidos
                    if (res.status === 401 || data.error === "Credenciales inválidas") {
                        errorMsg = "<?php echo $trans['error_credentials']; ?>";
                    } else if (res.status === 403) {
                        errorMsg = "<?php echo $trans['error_inactive']; ?>";
                    }
                    
                    throw new Error(errorMsg);
                }
            } catch (err) {
                alertBox.innerText = err.message;
                alertBox.classList.remove('hidden');
                btn.disabled = false;
                btn.innerText = originalText;
            }
        });

        // Lógica Modal
        const modal = document.getElementById('recoverModal');
        const openModal = () => { modal.classList.add('active'); document.getElementById('recoverStep1').classList.remove('hidden'); document.getElementById('recoverStep2').classList.add('hidden'); };
        const closeModal = () => modal.classList.remove('active');
        document.getElementById('recoverForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                const res = await fetch('api/recuperar_pass.php', { method: 'POST', body: JSON.stringify({correo: e.target.rec_correo.value})});
                const d = await res.json();
                if(d.success) { document.getElementById('recoverStep1').classList.add('hidden'); document.getElementById('recoverStep2').classList.remove('hidden'); }
            } catch(e){}
        });
    </script>
</body>
</html>