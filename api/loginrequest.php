<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Responde en JSON y corta la ejecución.
 * $ok       -> true/false
 * $message  -> texto para mostrar al usuario
 * $extra    -> datos adicionales (ej: redirect, campo con error)
 */
function respond(bool $ok, string $message = '', array $extra = []): void {
    echo json_encode(array_merge([
        'ok'      => $ok,
        'message' => $message,
    ], $extra));
    exit;
}

// ── Verificación CSRF ──────────────────────────────────────────
$csrfToken = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    respond(false, 'Token de seguridad inválido. Recargá la página e intentá de nuevo.');
}

// mysqli en modo excepción (para capturar condiciones de carrera en claves duplicadas)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ── Registro ───────────────────────────────────────────────────
if (isset($_POST['register'])) {
    $name     = trim($_POST['name']     ?? '');
    $surname  = trim($_POST['surname']  ?? '');
    $CI       = trim($_POST['CI']       ?? '');
    $address  = trim($_POST['address']  ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $email    = trim($_POST['email']    ?? '');
    $rawPass  = $_POST['password']      ?? '';

    if ($name === '' || $surname === '' || $CI === '' || $address === '' ||
        $phone === '' || $email === '' || $rawPass === '') {
        respond(false, 'Completá todos los campos.');
    }

    if (!ctype_digit($CI)) {
        respond(false, 'La cédula debe contener solo números.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(false, 'El email no es válido.');
    }

    $password = password_hash($rawPass, PASSWORD_DEFAULT);

    try {
        // Verificar si el email o la CI ya están registrados.
        $stmt = $conn->prepare("SELECT CI FROM users WHERE CI = ? OR email = ?");
        $stmt->bind_param("is", $CI, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->close();
            respond(false, 'La cédula o el email ya están registrados.');
        }
        $stmt->close();

        $stmt = $conn->prepare(
            "INSERT INTO users (name, surname, CI, address, phone, email, password, role)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'patient')"
        );
        $stmt->bind_param("ssissss", $name, $surname, $CI, $address, $phone, $email, $password);
        $stmt->execute();
        $stmt->close();

        respond(true, 'Cuenta creada correctamente. Ya podés iniciar sesión.', [
            'redirect' => 'login.php',
        ]);

    } catch (mysqli_sql_exception $e) {
        // Código 1062 = entrada duplicada (carrera entre dos registros simultáneos).
        if ($e->getCode() === 1062) {
            respond(false, 'La cédula o el email ya están registrados.');
        }
        respond(false, 'Ocurrió un error al registrar la cuenta. Intentá de nuevo.');
    }
}

// ── Login ────────────────────────────────────────────────────
if (isset($_POST['login'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password']   ?? '';

    if ($email === '' || $password === '') {
        respond(false, 'Ingresá email y contraseña.');
    }

    $stmt = $conn->prepare("SELECT CI, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Regenerar el ID de sesión al autenticar (previene session fixation).
            session_regenerate_id(true);

            $_SESSION['CI']    = $user['CI'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role']  = $user['role'];

            $stmt->close();

            $redirect = $user['role'] === 'admin' ? 'admin-citas.php' : 'paciente-dashboard.php';
            respond(true, 'Inicio de sesión correcto.', ['redirect' => $redirect]);
        }
    }

    $stmt->close();
    respond(false, 'Email o contraseña incorrectos.');
}

// Ninguna acción reconocida.
http_response_code(400);
respond(false, 'Solicitud inválida.');
