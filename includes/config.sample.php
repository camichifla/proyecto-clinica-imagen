<?php
// Copy to includes/config.php and fill in real values.
// includes/config.php is git-ignored and MUST NEVER be committed.

define('DB_DSN', 'mysql:host=localhost;dbname=clinica_imagen;charset=utf8mb4');
// Production: use a dedicated least-privilege DB user (SELECT/INSERT/UPDATE/DELETE on this schema only), never root.
define('DB_USER', 'root');
define('DB_PASS', '');

// Key for encrypting clinical notes at rest (AES-256-GCM). Generate once with:
//   php -r "echo base64_encode(random_bytes(32));"
// Back it up: losing it makes existing notes unreadable. Never change it once notes exist.
define('APP_KEY', '');

// --- SMTP (leave any value empty/placeholder to use the storage/mail.log dev fallback) ---
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'no-responder@clinica-imagen.local');
define('SMTP_FROM_NAME', 'Clínica Imagen');

define('APP_URL', 'http://localhost/proyecto');
define('APP_HTTPS', false); // set true only once the site is actually served over HTTPS (production: always true)

date_default_timezone_set('America/Montevideo');   // pinned: every DateTimeImmutable comparison depends on it
