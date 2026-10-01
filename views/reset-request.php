<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recuperar contrasena - Clinica Imagen</title>
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
      <h1>Recuperar contrasena</h1>

      <?php if ($enviado): ?>
        <p class="aviso-exito">Si el correo existe en el sistema, te enviamos un enlace para restablecer tu contrasena.</p>
        <p><a href="login.php">Volver a iniciar sesion</a></p>
      <?php else: ?>
        <form method="post" action="reset-request.php" novalidate>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <div class="campo">
            <label for="email">Correo</label>
            <input type="email" id="email" name="email" required>
          </div>
          <button type="submit" class="btn-primary">Enviar enlace</button>
        </form>
      <?php endif; ?>
    </section>
  </main>
</body>

</html>
