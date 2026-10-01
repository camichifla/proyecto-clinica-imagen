<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Buscar Resultados - Clinica Imagen</title>
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
    <section class="seccion-panel">
      <h1>Buscar Resultados</h1>

      <?php render_form_buscar_paciente($pacientes, $paciente, 'buscar-resultados.php'); ?>
    </section>

    <script src="assets/js/buscador-combo.js"></script>
    <script src="assets/js/selector-paciente-buscar.js"></script>

    <?php if ($pacienteNoEncontrado): ?>
      <?php render_paciente_no_encontrado(); ?>
    <?php elseif ($paciente): ?>
      <section class="seccion-panel">
        <h2><?= htmlspecialchars($paciente['apellido'] . ', ' . $paciente['nombre']) ?></h2>
        <?php render_lista_resultados($resultados, $imagenesPorResultado, 'Este paciente todavia no tiene resultados cargados.'); ?>
      </section>
    <?php endif; ?>
  </main>
</body>

</html>
