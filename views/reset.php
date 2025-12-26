<?php
// views/reset.php
// IMPORTANTE: Este archivo se carga desde index.php cuando la ruta es '/reset'

// 1. Obtener y sanear el token de la URL
$token = isset($_GET['token']) ? trim($_GET['token']) : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduGame - Restablecer Contraseña</title>
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Estilos específicos para centrar el formulario */
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background-color: var(--bg-body);
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }
    </style>
</head>
<body class="centered-layout">

<?php
// Si no hay token, mostramos mensaje de error directamente
if (!$token) {
    echo "<div class='card login-card text-center'>"; 
    echo "<h3 style='color:var(--danger-color)'>Enlace inválido</h3>";
    echo "<p class='text-muted'>El enlace de recuperación de contraseña es incorrecto o está incompleto.</p>";
    echo "<a href='login' class='btn-primary' style='display:inline-block; margin-top:1rem; text-decoration:none;'>Ir al Login</a>";
    echo "</div>";
    // No continuamos con el resto del body
} else {
    // Si hay token, mostramos el formulario
?>

<div class="card login-card">
    <div class="text-center mb-4">
        <h2 style="color:var(--primary);"><i class="fa-solid fa-key"></i> Restablecer Contraseña</h2>
        <p class="text-muted" style="font-size:0.9rem;">Introduce tu nueva contraseña. Debe ser diferente a la antigua.</p>
    </div>

    <div id="resetAlert" class="hidden" style="background:#fee2e2; color:#991b1b; padding:0.8rem; border-radius:0.5rem; margin-bottom:1rem; text-align:center;"></div>

    <form id="resetPasswordForm">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        
        <div class="mb-4">
            <label style="display:block; margin-bottom:0.5rem; color:var(--text-muted);">Nueva Contraseña</label>
            <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
        </div>
        
        <div class="mb-4">
            <label style="display:block; margin-bottom:0.5rem; color:var(--text-muted);">Confirmar Contraseña</label>
            <input type="password" name="confirm_password" class="form-control" required minlength="6" placeholder="Repite la contraseña">
        </div>

        <button type="submit" class="btn-primary" style="width:100%; padding:0.8rem;">
            Guardar Nueva Contraseña
        </button>
    </form>
</div>

<script>
document.getElementById('resetPasswordForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const btn = e.target.querySelector('button[type="submit"]');
    const alertBox = document.getElementById('resetAlert');
    const newPass = e.target.new_password.value;
    const confirmPass = e.target.confirm_password.value;
    const originalText = btn.innerText;

    if (newPass !== confirmPass) {
        alertBox.innerText = 'Las contraseñas no coinciden.';
        alertBox.classList.remove('hidden');
        return;
    }

    btn.disabled = true;
    btn.innerText = 'Guardando...';
    alertBox.classList.add('hidden');

    try {
        const res = await fetch('api/reset_handler.php', { 
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                token: e.target.token.value, 
                new_password: newPass 
            })
        });
        
        const data = await res.json();
        
        if (data.success) {
            alertBox.style.background = '#d1fae5';
            alertBox.style.color = '#065f46';
            alertBox.innerText = '¡Contraseña actualizada! Redirigiendo...';
            alertBox.classList.remove('hidden');
            
            setTimeout(() => {
                window.location.href = 'login';
            }, 2000);
        } else {
            // Este error ya debería contener el mensaje de "la nueva contraseña debe ser diferente a la antigua"
            throw new Error(data.error || 'Error desconocido al restablecer.');
        }
    } catch (err) {
        alertBox.style.background = '#fee2e2';
        alertBox.style.color = '#991b1b';
        alertBox.innerText = err.message;
        alertBox.classList.remove('hidden');
        btn.disabled = false;
        btn.innerText = originalText;
    }
});
</script>

<?php
} // Cierre del else (if token)
?>
</body>
</html>