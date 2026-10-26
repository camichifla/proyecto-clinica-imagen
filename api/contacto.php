<?php
// Public contact form. No login: CSRF token comes from api/sesion.php?opcional=1
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validation.php';

require_post_json();

// Honeypot: a hidden field real visitors never fill. Pretend success, store nothing.
if (trim((string) ($_POST['sitio_web'] ?? '')) !== '') {
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if (demasiados_eventos('contacto', 'ip', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 5, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'errores' => ['general' => 'Enviaste varios mensajes seguidos. Probá de nuevo más tarde.']], JSON_UNESCAPED_UNICODE);
    exit;
}

$nombre   = trim((string) ($_POST['nombre'] ?? ''));
$email    = trim((string) ($_POST['email'] ?? ''));
$telefono = trim((string) ($_POST['telefono'] ?? ''));
$mensaje  = trim((string) ($_POST['mensaje'] ?? ''));

$errores = [];
if ($nombre === '' || mb_strlen($nombre) > 80) {
    $errores['nombre'] = 'El nombre es obligatorio (máximo 80 caracteres).';
}
if ($email === '' || mb_strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errores['email'] = 'Ingresá un correo válido (nombre@dominio.com).';
}
if ($telefono !== '' && !preg_match(REGLAS_PACIENTE['numero']['pattern'], $telefono)) {
    $errores['telefono'] = 'Formato de Teléfono inválido. ' . REGLAS_PACIENTE['numero']['hint'];
}
if (mb_strlen($mensaje) < 10 || mb_strlen($mensaje) > 2000) {
    $errores['mensaje'] = 'El mensaje debe tener entre 10 y 2000 caracteres.';
}
if ($errores) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
    exit;
}

db()->prepare('INSERT INTO mensajes_contacto (nombre, email, telefono, mensaje) VALUES (?, ?, ?, ?)')
    ->execute([$nombre, $email, $telefono === '' ? null : $telefono, $mensaje]);
registrar_evento('contacto', 'mensaje=' . db()->lastInsertId());

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
