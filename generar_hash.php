<?php
// Contraseña deseada en texto plano
$contrasena_plana = '123456';

// Generar el hash de la contraseña usando el algoritmo PASSWORD_DEFAULT (Bcrypt)
$nuevo_hash = password_hash($contrasena_plana, PASSWORD_DEFAULT);

echo "Contraseña en texto plano: " . $contrasena_plana . "\n";
echo "Nuevo Hash de Contraseña: " . $nuevo_hash . "\n\n";
echo "¡Copia la cadena que está DESPUÉS de 'Nuevo Hash de Contraseña:'!";

// EJEMPLO de salida:
// Nuevo Hash de Contraseña: $2y$10$9Gv1X.3s5r1.p/u1.2j3.u4k5l6m7n8o9p0q1r2s3t4u5v6w7x8y9
?>