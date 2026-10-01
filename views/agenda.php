<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Agenda - Clinica Imagen</title>
  <link rel="stylesheet" href="./assets/css/styles.css">
  <link rel="stylesheet" href="./assets/css/agenda.css">
  <script src="./assets/js/agenda-ajax.js" defer></script>
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
    <section class="seccion-panel">
      <h1>Agenda</h1>

      <form method="get" action="agenda.php" class="form-buscar-paciente">
        <input type="hidden" name="vista" value="<?= htmlspecialchars($vista) ?>">
        <input type="hidden" name="fecha" value="<?= htmlspecialchars($fechaRef->format('Y-m-d')) ?>">
        <div class="campo">
          <label for="sucursal_id">Sede</label>
          <select id="sucursal_id" name="sucursal_id">
            <option value="">Todas</option>
            <?php foreach ($sucursales as $s): ?>
              <option value="<?= (int) $s['id'] ?>" <?= $sucursalId === (int) $s['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="btn-primary">Filtrar</button>
      </form>
    </section>

    <section class="seccion-panel">
      <div id="agenda-calendario">
        <?php render_calendario_agenda($vista, $fechaRef, $rango, $citasPorDia, 'url_agenda'); ?>
      </div>
    </section>
  </main>
</body>

</html>
