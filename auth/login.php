<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

// Fixed hash with no matching password, used to equalize password_verify()
// timing when no account row is found, so a missing account cannot be
// distinguished from a wrong password by response time.
const DUMMY_HASH = '$2y$10$frrN.cZWEHqFbIH6xRp4R.WkE5mQDxy4sE35jd6QagOhrVYweahrS';

// Post-login destination, keyed by role. A future role needs only a new
// entry here — no branching logic changes.
const RUTAS_POST_LOGIN = [
    'paciente'       => 'index.php',
    'medico'         => 'index.php',
    'profesional'    => 'index.php',
    'administrador'  => 'index.php',
];

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);

    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT id, password_hash, rol, ref_id, verificado, activo FROM cuentas WHERE email = ?');
    $stmt->execute([$email]);
    $cuenta = $stmt->fetch();

    if (!$cuenta) {
        password_verify($password, DUMMY_HASH); // equalize timing
        $error = 'Correo o contrasena invalidos.';
    } elseif (!password_verify($password, $cuenta['password_hash'])) {
        $error = 'Correo o contrasena invalidos.';
    } elseif ((int) $cuenta['verificado'] === 0) {
        $error = 'Debes verificar tu correo antes de iniciar sesion.';
    } elseif ((int) $cuenta['activo'] === 0) {
        $error = 'Tu cuenta fue desactivada. Contacta a la clinica.';
    } else {
        session_regenerate_id(true);

        if (password_needs_rehash($cuenta['password_hash'], PASSWORD_BCRYPT)) {
            $nuevoHash = password_hash($password, PASSWORD_BCRYPT);
            db()->prepare('UPDATE cuentas SET password_hash = ? WHERE id = ?')->execute([$nuevoHash, $cuenta['id']]);
        }

        $nombre = $email; // fallback if the role table row is somehow missing
        $tablaPerfil = [
            'paciente'      => 'pacientes',
            'medico'        => 'medicos',
            'profesional'   => 'profesionales',
            'administrador' => 'administradores',
        ][$cuenta['rol']] ?? null;
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

        $destino = RUTAS_POST_LOGIN[$cuenta['rol']] ?? 'index.php';
        header('Location: ' . $destino);
        exit;
    }
}
require __DIR__ . '/../views/login.php';
