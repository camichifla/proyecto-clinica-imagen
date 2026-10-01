<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Iniciar sesion - Clinica Imagen</title>
  <link rel="stylesheet" href="./assets/css/styles.css">
</head>

<body>
  <header>
    <div class="logo">
      <a href="index.php"><img src="./assets/imagenes/logo.png" alt="Clinica Imagen"></a>
    </div>
  </header>

  <main>
    <section class="seccion-auth">
      <h1>Iniciar sesion</h1>

      <?php if (isset($_GET['expirado'])): ?>
        <p class="campo-error">Tu sesion expiro por inactividad. Inicia sesion nuevamente.</p>
      <?php endif; ?>
      <?php if (isset($_GET['verificado'])): ?>
        <p class="aviso-exito">Cuenta verificada. Ya podes iniciar sesion.</p>
      <?php endif; ?>
      <?php if ($error !== null): ?>
        <p class="campo-error"><?= htmlspecialchars($error) ?></p>
        <?php if (str_contains($error, 'verificar')): ?>
          <p><a href="verify.php">Reenviar enlace de verificacion</a></p>
        <?php endif; ?>
      <?php endif; ?>

      <form method="post" action="login.php" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <div class="campo">
          <label for="email">Correo</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
        </div>
        <div class="campo">
          <label for="password">Contrasena</label>
          <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn-primary">Ingresar</button>
      </form>

      <p><a href="reset-request.php">¿Olvidaste tu contrasena?</a></p>
      <p>¿No tenes cuenta? <a href="register.php">Registrate</a></p>
    </section>
  </main>
</body>

</html>
