<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cargar Resultados - Clinica Imagen</title>
  <link rel="stylesheet" href="./assets/css/styles.css">
  <link rel="stylesheet" href="./assets/css/enviar-resultados.css">
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
    <section class="seccion-auth seccion-enviar-resultados">
      <h1>Cargar Observaciones y Resultados</h1>

      <?php if (isset($_GET['ok'])): ?>
        <p class="aviso-exito">Resultado #<?= (int) $_GET['ok'] ?> guardado correctamente.</p>
      <?php endif; ?>

      <?php if (isset($errores['general'])): ?>
        <p class="aviso-error"><?= htmlspecialchars($errores['general']) ?></p>
      <?php endif; ?>

      <?php
      $pacienteActual = null;
      foreach ($pacientes as $paciente) {
          if ((string) $paciente['id'] === $valores['paciente_id']) {
              $pacienteActual = $paciente;
              break;
          }
      }
      ?>
      <form method="post" action="cargar-resultados.php" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

        <div class="campo">
          <label for="paciente_id_buscar">Paciente</label>
          <input type="text" id="paciente_id_buscar" data-lista="paciente_id_lista" data-buscador-combo="paciente_id"
                 autocomplete="off" placeholder="Buscar por apellido, nombre o CI"
                 value="<?= $pacienteActual ? htmlspecialchars($pacienteActual['apellido'] . ', ' . $pacienteActual['nombre'] . ' (CI ' . $pacienteActual['ci'] . ')') : '' ?>"
                 <?= isset($errores['paciente_id']) ? 'aria-invalid="true"' : '' ?>>
          <datalist id="paciente_id_lista">
            <?php foreach ($pacientes as $paciente): ?>
              <option data-id="<?= (int) $paciente['id'] ?>" value="<?= htmlspecialchars($paciente['apellido'] . ', ' . $paciente['nombre'] . ' (CI ' . $paciente['ci'] . ')') ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <input type="hidden" id="paciente_id" name="paciente_id" value="<?= htmlspecialchars($valores['paciente_id']) ?>">
          <?php if (isset($errores['paciente_id'])): ?><p class="campo-error"><?= htmlspecialchars($errores['paciente_id']) ?></p><?php endif; ?>
          <?php if (!$pacientes): ?><p class="campo-error">Todavia no tenes pacientes con citas asignadas.</p><?php endif; ?>
        </div>

        <div class="campo">
          <label for="cita_id">Cita asociada (opcional)</label>
          <select id="cita_id" name="cita_id" class="selector-combo" disabled <?= isset($errores['cita_id']) ? 'aria-invalid="true"' : '' ?>>
            <option value="">Elegi un paciente primero</option>
          </select>
          <?php if (isset($errores['cita_id'])): ?><p class="campo-error"><?= htmlspecialchars($errores['cita_id']) ?></p><?php endif; ?>
        </div>

        <div class="campo">
          <label for="nombre_estudio">Nombre del estudio</label>
          <input type="text" id="nombre_estudio" name="nombre_estudio" maxlength="120"
                 value="<?= htmlspecialchars($valores['nombre_estudio']) ?>"
                 <?= isset($errores['nombre_estudio']) ? 'aria-invalid="true"' : '' ?>>
          <?php if (isset($errores['nombre_estudio'])): ?><p class="campo-error"><?= htmlspecialchars($errores['nombre_estudio']) ?></p><?php endif; ?>
        </div>

        <div class="campo">
          <label for="fecha_estudio">Fecha del estudio</label>
          <input type="date" id="fecha_estudio" name="fecha_estudio" max="<?= htmlspecialchars($fechaMaxEstudio) ?>"
                 value="<?= htmlspecialchars($valores['fecha_estudio']) ?>"
                 <?= isset($errores['fecha_estudio']) ? 'aria-invalid="true"' : '' ?>>
          <?php if (isset($errores['fecha_estudio'])): ?><p class="campo-error"><?= htmlspecialchars($errores['fecha_estudio']) ?></p><?php endif; ?>
        </div>

        <div class="campo">
          <label for="observaciones">Observaciones (opcional)</label>
          <textarea id="observaciones" name="observaciones" rows="4"><?= htmlspecialchars($valores['observaciones']) ?></textarea>
        </div>

        <div class="campo">
          <label for="imagenes">Imagenes</label>
          <input type="file" id="imagenes" name="imagenes[]" accept="image/png,image/jpeg,image/webp" multiple
                 <?= isset($errores['imagenes']) ? 'aria-invalid="true"' : '' ?>>
          <?php if (isset($errores['imagenes'])): ?><p class="campo-error"><?= htmlspecialchars($errores['imagenes']) ?></p><?php endif; ?>
        </div>

        <button type="submit" class="btn-primary">Guardar resultado</button>
      </form>
    </section>
  </main>

  <script>
    // Citas confirmadas propias de este profesional agrupadas por
    // paciente_id — filtrado UX-only; el servidor re-valida paciente +
    // profesional + estado 'confirmada' independientemente de lo que
    // envie el cliente.
    var CITAS_POR_PACIENTE = <?= json_encode($citasPorPaciente, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    var CITA_ELEGIDA = <?= json_encode($valores['cita_id']) ?>;
    var MENSAJE_SIN_CITAS = 'Este paciente no tiene citas confirmadas con vos';
  </script>
  <script src="assets/js/buscador-combo.js"></script>
  <script src="assets/js/citas-por-paciente.js"></script>
  <script src="assets/js/selector-combo.js"></script>
</body>

</html>
