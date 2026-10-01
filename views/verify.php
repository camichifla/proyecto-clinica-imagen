<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verificar cuenta - Clinica Imagen</title>
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
      <h1>Verificacion de cuenta</h1>

      <?php if ($reenviado): ?>
        <p class="aviso-exito">Si la cuenta existe y aun no fue verificada, te enviamos un nuevo enlace (revisa tu correo o <code>storage/mail.log</code>).</p>
        <p><a href="login.php">Volver a iniciar sesion</a></p>
      <?php elseif ($tokenInvalido): ?>
        <p class="campo-error">El enlace de verificacion es invalido o vencio.</p>

        <p>Solicita un nuevo enlace:</p>
        <form method="post" action="verify.php" novalidate>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="accion" value="reenviar">
          <div class="campo">
            <label for="email">Correo</label>
            <input type="email" id="email" name="email" required>
          </div>
          <button type="submit" class="btn-primary">Reenviar enlace</button>
        </form>
      <?php else: ?>
        <p>Usa el enlace enviado a tu correo para verificar tu cuenta.</p>
      <?php endif; ?>
    </section>
  </main>
</body>

</html>
