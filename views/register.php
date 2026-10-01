<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registro - Clinica Imagen</title>
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
      <h1>Crear cuenta de paciente</h1>

      <?php if ($exito): ?>
        <p class="aviso-exito">Revisa tu correo (o <code>storage/mail.log</code> en modo de desarrollo) para verificar tu cuenta antes de iniciar sesion.</p>
        <p><a href="login.php" class="btn-primary">Ir a iniciar sesion</a></p>
      <?php else: ?>
        <?php if (!empty($errores['general'])): ?>
          <p class="campo-error"><?= htmlspecialchars($errores['general']) ?></p>
        <?php endif; ?>

        <form method="post" action="register.php" novalidate>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

          <div class="campo-fila">
            <?php foreach (['nombre' => 'text', 'apellido' => 'text'] as $campo => $tipo): ?>
              <?php $r = REGLAS_PACIENTE[$campo]; ?>
              <div class="campo">
                <label for="<?= $campo ?>"><?= htmlspecialchars($r['label']) ?></label>
                <input type="<?= $tipo ?>" id="<?= $campo ?>" name="<?= $campo ?>"
                       value="<?= htmlspecialchars($valores[$campo]) ?>"
                       <?= isset($errores[$campo]) ? 'aria-invalid="true"' : '' ?>>
                <?php if (isset($errores[$campo])): ?><p class="campo-error"><?= htmlspecialchars($errores[$campo]) ?></p><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <?php foreach (['ci' => 'text', 'direccion' => 'text', 'numero' => 'tel', 'email' => 'email'] as $campo => $tipo): ?>
            <?php $r = REGLAS_PACIENTE[$campo]; ?>
            <div class="campo">
              <label for="<?= $campo ?>"><?= htmlspecialchars($r['label']) ?></label>
              <input type="<?= $tipo ?>" id="<?= $campo ?>" name="<?= $campo ?>"
                     value="<?= htmlspecialchars($valores[$campo]) ?>"
                     <?= isset($errores[$campo]) ? 'aria-invalid="true"' : '' ?>>
              <?php if (isset($errores[$campo])): ?><p class="campo-error"><?= htmlspecialchars($errores[$campo]) ?></p><?php endif; ?>
            </div>
          <?php endforeach; ?>

          <div class="campo">
            <label for="contrasena"><?= htmlspecialchars(REGLAS_PACIENTE['contrasena']['label']) ?></label>
            <input type="password" id="contrasena" name="contrasena" value=""
                   <?= isset($errores['contrasena']) ? 'aria-invalid="true"' : '' ?>>
            <?php if (isset($errores['contrasena'])): ?><p class="campo-error"><?= htmlspecialchars($errores['contrasena']) ?></p><?php endif; ?>
          </div>

          <button type="submit" class="btn-primary">Registrarme</button>
        </form>

        <p>¿Ya tenes cuenta? <a href="login.php">Iniciar sesion</a></p>
      <?php endif; ?>
    </section>
  </main>
</body>

</html>
