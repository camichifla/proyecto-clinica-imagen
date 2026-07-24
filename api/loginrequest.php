<?php
session_start();
require_once 'config.php';

if (isset($_POST['register'])) {
    $name     = trim($_POST['name']);
    $surname  = trim($_POST['surname']);
    $CI       = trim($_POST['CI']);
    $address  = trim($_POST['address']);
    $phone    = trim($_POST['phone']);
    $email    = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Verificar si el email ya está registrado.
    $stmt = $conn->prepare("SELECT CI FROM users WHERE CI = ? OR email = ?");
    $stmt->bind_param("is", $CI, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        $_SESSION['register_error'] = 'El email ya está registrado.';
        $_SESSION['active_form']    = 'register';
        header("Location: /clinica-imagen/public/login.php");
    exit;
    }

    $stmt->close();

    // Insertar el nuevo usuario con rol 'patient' por defecto.
    $stmt = $conn->prepare(
        "INSERT INTO users (name, surname, CI, address, phone, email, password, role)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'patient')"
    );
    $stmt->bind_param("ssissss", $name, $surname, $CI, $address, $phone, $email, $password);
    $stmt->execute();
    $stmt->close();

    header("Location: /clinica-imagen/public/login.php");
    exit();
}

if (isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT CI, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Regenerar el ID de sesión al autenticar para prevenir
            // ataques de fijación de sesión (session fixation).
            session_regenerate_id(true);

            $_SESSION['CI'] = $user['CI'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            $stmt->close();

            // Redirigir según el rol del usuario.
            if ($user['role'] === 'admin') {
                header("Location: /clinica-imagen/public/admin-citas.php");
            } else {
                header("Location: /clinica-imagen/public/paciente-dashboard.php");
            }
            exit();
        }
    }

    $stmt->close();

    // Credenciales incorrectas
    $_SESSION['login_error'] = 'Email o contraseña incorrectos.';
    $_SESSION['active_form'] = 'login';
    header("Location: /clinica-imagen/public/login.php");
    exit();
}