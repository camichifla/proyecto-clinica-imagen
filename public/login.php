<?php
session_start();

$errors = [
    'login'    => $_SESSION['login_error']    ?? '',
    'register' => $_SESSION['register_error'] ?? '',
];

// Determina qué panel mostrar (login por defecto).
$activeForm = $_SESSION['active_form'] ?? 'login';


session_unset();

function showError(string $error): string {
    if (empty($error)) {
        return '';
    }
    return "<p class='error-message'>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</p>";
}

function isActiveForm(string $formName, string $activeForm): string {
    return $formName === $activeForm ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión — Clínica Imagen</title>
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/styles.css">
</head>

<body class="login">

    <div class="login">

        <div class="form-box <?= isActiveForm('login', $activeForm) ?>" id="login-form">
            <form action="loginrequest.php" method="post">
                <h2 class="login">Iniciar Sesión</h2>

                <?= showError($errors['login']) ?>

                <input class="login" type="email" name="email" placeholder="Email" required autocomplete="email">
                <input class="login" type="password" name="password" placeholder="Contraseña" required
                    autocomplete="current-password">

                <button type="submit" name="login">Iniciar Sesión</button>

                <p>¿No tenés cuenta? <a href="#" onclick="showForm('register-form')">Registrarse</a></p>
            </form>
        </div>

        <div class="form-box <?= isActiveForm('register', $activeForm) ?>" id="register-form">
            <form action="loginrequest.php" method="post">
                <h2 class="login">Registrarse</h2>

                <?= showError($errors['register']) ?>

                <input class="login" type="text" name="name" placeholder="Nombre" required autocomplete="given-name">
                <input class="login" type="text" name="surname" placeholder="Apellido" required
                    autocomplete="family-name">
                <input class="login" type="text" pattern="[0-9]{8}" name="CI" placeholder="Cédula de Identidad" required
                    autocomplete="off">
                <input class="login" type="text" name="address" placeholder="Dirección" required
                    autocomplete="street-address">
                <input class="login" type="tel" name="phone" placeholder="Número Telefónico" required
                    autocomplete="tel">
                <input class="login" type="email" name="email" placeholder="Email" required autocomplete="email">
                <input class="login" type="password" name="password" placeholder="Contraseña" required
                    autocomplete="new-password">

                <button type="submit" name="register">Registrarse</button>

                <p>¿Ya tenés cuenta? <a href="#" onclick="showForm('login-form')">Iniciar Sesión</a></p>
            </form>
        </div>

    </div>

    <script src="scripts/login.js"></script>
</body>

</html>