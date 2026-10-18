<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

require_post_json();

const MAX_FALLOS_EMAIL = 5;
const MAX_FALLOS_IP    = 20;
const VENTANA_MINUTOS  = 15;
const DUMMY_HASH = '$2y$10$frrN.cZWEHqFbIH6xRp4R.WkE5mQDxy4sE35jd6QagOhrVYweahrS';

$email    = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

// El limite por email cuenta solo los fallos desde esta IP: un tercero no puede bloquear al duenio desde otra.
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
if (demasiados_eventos('login_fallo', 'email', $email, MAX_FALLOS_EMAIL, VENTANA_MINUTOS, $ip)
    || demasiados_eventos('login_fallo', 'ip', $ip, MAX_FALLOS_IP, VENTANA_MINUTOS)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Demasiados intentos. Proba de nuevo en 15 minutos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = db()->prepare('SELECT id, password_hash, rol, ref_id, verificado, activo FROM cuentas WHERE email = ?');
$stmt->execute([$email]);
$cuenta = $stmt->fetch();

$error = null;
if (!$cuenta) {
    password_verify($password, DUMMY_HASH);
    $error = 'Correo o contrasena invalidos.';
} elseif (!password_verify($password, $cuenta['password_hash'])) {
    $error = 'Correo o contrasena invalidos.';
} elseif ((int) $cuenta['verificado'] === 0) {
    $error = 'Debes verificar tu correo antes de iniciar sesion.';
} elseif ((int) $cuenta['activo'] === 0) {
    $error = 'Tu cuenta fue desactivada. Contacta a la clinica.';
}

if ($error !== null) {
    registrar_evento('login_fallo', null, $email);
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

session_regenerate_id(true);

if (password_needs_rehash($cuenta['password_hash'], PASSWORD_BCRYPT)) {
    $nuevoHash = password_hash($password, PASSWORD_BCRYPT);
    db()->prepare('UPDATE cuentas SET password_hash = ? WHERE id = ?')->execute([$nuevoHash, $cuenta['id']]);
}

$nombre = $email;
$tablaPerfil = TABLA_POR_ROL[$cuenta['rol']] ?? null;
if ($tablaPerfil !== null) {
    $perfil = db()->prepare("SELECT nombre FROM {$tablaPerfil} WHERE id = ?");
    $perfil->execute([$cuenta['ref_id']]);
    $fila = $perfil->fetch();
    if ($fila) {
        $nombre = $fila['nombre'];
    }
}

$_SESSION['cuenta_id']     = (int) $cuenta['id'];
$_SESSION['rol']           = (string) $cuenta['rol'];
$_SESSION['ref_id']        = (int) $cuenta['ref_id'];
$_SESSION['nombre']        = $nombre;
$_SESSION['ultimo_acceso'] = time();
$_SESSION['csrf']          = bin2hex(random_bytes(32));

registrar_evento('login_ok', 'cuenta=' . $cuenta['id'], $email);

echo json_encode(['ok' => true, 'redirect' => in_array($cuenta['rol'], ['administrador', 'recepcionista'], true) ? 'panel.html' : 'index.html'], JSON_UNESCAPED_UNICODE);
