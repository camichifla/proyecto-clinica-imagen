<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

function respond(bool $ok, string $message = '', array $extra = []): void {
    echo json_encode(array_merge([
        'ok'      => $ok,
        'message' => $message,
    ], $extra));
    exit;
}

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
            'redirect' => 'login.html',
        ]);

    } catch (mysqli_sql_exception $e) {
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
            session_regenerate_id(true);

            $_SESSION['CI']    = $user['CI'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role']  = $user['role'];

            $stmt->close();

            $redirect = $user['role'] === 'admin' ? '../api/admin-citas.php' : '../api/paciente-dashboard.php';
            respond(true, 'Inicio de sesión correcto.', ['redirect' => $redirect]);
        }
    }

    $stmt->close();
    respond(false, 'Email o contraseña incorrectos.');
}

http_response_code(400);
respond(false, 'Solicitud inválida.');