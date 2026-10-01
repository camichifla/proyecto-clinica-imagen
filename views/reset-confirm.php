<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Restablecer contrasena - Clinica Imagen</title>
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
      <h1>Restablecer contrasena</h1>

      <?php if ($tokenInvalido): ?>
        <p class="campo-error">El enlace de restablecimiento es invalido o vencio.</p>
        <p><a href="reset-request.php">Solicitar un nuevo enlace</a></p>
      <?php else: ?>
        <?php if ($error !== null): ?>
          <p class="campo-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="post" action="reset-confirm.php" novalidate>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="token" value="<?= htmlspecialchars($tokenRaw) ?>">
          <div class="campo">
            <label for="contrasena">Nueva contrasena</label>
            <input type="password" id="contrasena" name="contrasena"
                   required>
          </div>
          <button type="submit" class="btn-primary">Actualizar contrasena</button>
        </form>
      <?php endif; ?>
    </section>
  </main>
</body>

</html>
