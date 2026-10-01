<?php
// includes/config.sample.php — committed template.
// Copy to includes/config.php and fill in real values.
// includes/config.php is git-ignored and MUST NEVER be committed.

// --- Database ---
define('DB_DSN', 'mysql:host=localhost;dbname=clinica_imagen;charset=utf8mb4');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- SMTP (leave any value empty/placeholder to use the storage/mail.log dev fallback) ---
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'no-responder@clinica-imagen.local');
define('SMTP_FROM_NAME', 'Clinica Imagen');

// --- App ---
define('APP_URL', 'http://localhost/proyecto');
define('APP_HTTPS', false); // set true only once the site is actually served over HTTPS

// --- Locale ---
date_default_timezone_set('America/Montevideo');   // pinned: every DateTimeImmutable comparison depends on it
