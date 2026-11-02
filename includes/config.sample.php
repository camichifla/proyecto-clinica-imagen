<?php

define('DB_DSN', 'mysql:host=localhost;dbname=clinica_imagen;charset=utf8mb4');
// En producción usar un usuario de BD con privilegios mínimos (SELECT/INSERT/UPDATE/DELETE sobre este esquema), nunca root.
define('DB_USER', 'root');
define('DB_PASS', '');

// Clave para cifrar notas clínicas en reposo. Generar una sola vez con: php -r "echo base64_encode(random_bytes(32));"
// Respaldarla: perderla vuelve ilegibles las notas existentes. No cambiarla una vez que haya notas.
define('APP_KEY', '');

define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'no-responder@clinica-imagen.local');
define('SMTP_FROM_NAME', 'Clínica Imagen');

define('APP_URL', 'http://localhost/proyecto');
define('APP_HTTPS', false);

date_default_timezone_set('America/Montevideo');
