<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Modificar Usuario - Clinica Imagen</title>
  <link rel="stylesheet" href="./assets/css/styles.css">
</head>

<body>
  <header>
    <div class="logo">
      <a href="index.php"><img src="./assets/imagenes/logo.png" alt="Clinica Imagen"></a>
    </div>
    <div class="user">
      <details class="menu-usuario">
        <summary class="menu-usuario-toggle" aria-label="Menu de usuario">
          <img src="" alt="">
        </summary>
        <div class="menu-usuario-panel">
          <span class="user-nombre"><?= htmlspecialchars($usuario['nombre']) ?></span>
          <?php render_menu_usuario($usuario); ?>
          <form method="post" action="logout.php" class="form-logout">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <button type="submit" class="btn-logout">Cerrar sesion</button>
          </form>
        </div>
      </details>
    </div>
  </header>

  <main>
    <section class="seccion-auth">
      <h1>Modificar Usuario</h1>

      <?php if ($config !== null): ?>
        <?php if ($exito): ?>
          <p class="aviso-exito">Datos actualizados correctamente.</p>
        <?php endif; ?>

        <form method="post" action="modificar-usuario.php" novalidate>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

          <?php foreach ($config['campos'] as $campo => $tipo): ?>
            <?php if ($campo === 'nombre'): ?><div class="campo-fila"><?php endif; ?>
            <?php $r = REGLAS_PACIENTE[$campo]; ?>
            <div class="campo">
              <label for="<?= $campo ?>"><?= htmlspecialchars($r['label']) ?></label>
              <input type="<?= $tipo ?>" id="<?= $campo ?>" name="<?= $campo ?>"
                     value="<?= htmlspecialchars($valores[$campo]) ?>"
                     <?= isset($errores[$campo]) ? 'aria-invalid="true"' : '' ?>>
              <?php if (isset($errores[$campo])): ?><p class="campo-error"><?= htmlspecialchars($errores[$campo]) ?></p><?php endif; ?>
            </div>
            <?php if ($campo === 'apellido'): ?></div><?php endif; ?>
          <?php endforeach; ?>

          <button type="submit" class="btn-primary">Guardar cambios</button>
        </form>
      <?php else: ?>
        <p>Esta seccion esta en construccion.</p>
      <?php endif; ?>
    </section>
  </main>
</body>

</html>
