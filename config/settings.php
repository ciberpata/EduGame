<?php
// config/settings.php
return [
    'smtp' => [
        'host' => 'mail.obradoiroweb.com',    // Servidor SMTP
        'username' => 'kahoot@obradoiroweb.com', // Usuario
        'password' => '$kaho20A10b96C*11d01', // Contraseña
        'secure' => 'ssl',                // tls o ssl
        'port' => 465,                    // Puerto estándar (587 para tls, 465 para ssl)
        'from_email' => 'kahoot@obradoiroweb.com',
        'from_name' => 'EduGame. Sericio de Soporte'
    ],
    // Otros ajustes globales aquí
];
?>