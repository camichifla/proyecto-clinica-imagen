<?php
session_start();
require_once 'config.php';

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
}

function respond(bool $ok, string $message = '', array $extra = [], bool $isAjax = true): void {
    if ($isAjax) {
        echo json_encode(array_merge([
            'ok'      => $ok,
            'message' => $message,
        ], $extra));
        exit;
    }

    if ($ok) {
        header('Location: ' . ($extra['redirect'] ?? '/clinica-imagen/api/paciente-dashboard.php'));
    } else {
        header('Location: /clinica-imagen/public/login.html?error=' . urlencode($message));
    }
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
        $stmt = $conn->prepare(
            "SELECT CI FROM (
                SELECT CI, email FROM users
                UNION ALL
                SELECT CI, email FROM Administradores
                UNION ALL
                SELECT CI, email FROM Profesionales
            ) AS all_users
            WHERE CI = ? OR email = ?
            LIMIT 1"
        );
        $stmt->execute([$CI, $email]);

        if ($stmt->fetchColumn() !== false) {
            respond(false, 'La cédula o el email ya están registrados.');
        }

        $stmt = $conn->prepare(
            "INSERT INTO users (name, surname, CI, address, phone, email, password, role)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'patient')"
        );
        $stmt->execute([$name, $surname, $CI, $address, $phone, $email, $password]);

        respond(true, 'Cuenta creada correctamente. Ya podés iniciar sesión.', [
            'redirect' => 'login.html',
        ]);

    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) {
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

    $stmt = $conn->prepare(
        "SELECT CI, name, surname, email, password, role FROM (
            SELECT CI, name, surname, email, password, 'admin' AS role, 1 AS priority
            FROM Administradores
            WHERE email = ?
            UNION ALL
            SELECT CI, name, surname, email, password, 'professional' AS role, 2 AS priority
            FROM Profesionales
            WHERE email = ?
            UNION ALL
            SELECT CI, name, surname, email, password, role, 3 AS priority
            FROM users
            WHERE email = ?
        ) AS login_users
        ORDER BY priority
        LIMIT 1"
    );
    $stmt->execute([$email, $email, $email]);
    $user = $stmt->fetch();

    if ($user) {

        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['CI']    = $user['CI'];
            $_SESSION['name']  = $user['name'];
            $_SESSION['surname'] = $user['surname'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role']  = $user['role'];

            $redirect = $user['role'] === 'professional'
                ? '/clinica-imagen/api/profesional-dashboard.php'
                : ($user['role'] === 'admin'
                    ? '/clinica-imagen/api/admin-citas.php'
                    : '/clinica-imagen/api/paciente-dashboard.php');
            respond(true, 'Inicio de sesión correcto.', ['redirect' => $redirect], $isAjax);
        }
    }

    respond(false, 'Email o contraseña incorrectos.', [], $isAjax);
}

if ($isAjax) {
    http_response_code(400);
}
respond(false, 'Solicitud inválida.', [], $isAjax);
