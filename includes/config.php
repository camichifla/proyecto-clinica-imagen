<?php
// includes/config.php — LOCAL DEV VALUES. Git-ignored, never committed.
// Copied from config.sample.php; SMTP left blank on purpose so mailer.php
// falls back to logging links in storage/mail.log (Decision 2 dev mode).

// --- Database ---
define('DB_DSN', 'mysql:host=localhost;dbname=clinica_imagen;charset=utf8mb4');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- SMTP (empty -> dev-log fallback in includes/mailer.php) ---
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'no-responder@clinica-imagen.local');
define('SMTP_FROM_NAME', 'Clinica Imagen');

// --- App ---
define('APP_URL', 'http://localhost/proyecto');
define('APP_HTTPS', false);

// --- Locale ---
date_default_timezone_set('America/Montevideo');   // pinned: every DateTimeImmutable comparison depends on it
