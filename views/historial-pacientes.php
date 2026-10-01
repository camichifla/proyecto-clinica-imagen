<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Historial de Pacientes - Clinica Imagen</title>
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
      <h1>Historial de Pacientes</h1>

      <?php render_form_buscar_paciente($pacientes, $paciente, 'historial-pacientes.php', 'Todavia no tenes pacientes con citas asignadas.'); ?>
    </section>

    <script src="assets/js/buscador-combo.js"></script>
    <script src="assets/js/selector-paciente-buscar.js"></script>

    <?php if ($pacienteNoEncontrado): ?>
      <?php render_paciente_no_encontrado(); ?>
    <?php elseif ($paciente): ?>
      <section class="seccion-panel">
        <h2><?= htmlspecialchars($paciente['apellido'] . ', ' . $paciente['nombre']) ?></h2>

        <h3>Historial de citas</h3>
        <?php if (!$citas): ?>
          <p>No hay citas registradas con este paciente.</p>
        <?php else: ?>
          <div class="tabla-scroll">
            <table class="tabla-datos">
              <thead>
                <tr>
                  <th scope="col">Fecha y hora solicitada</th>
                  <th scope="col">Sede</th>
                  <th scope="col">Estudio</th>
                  <th scope="col">Estado</th>
                  <th scope="col">Confirmacion</th>
                  <th scope="col">Notas</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($citas as $cita): ?>
                  <tr>
                    <td data-label="Fecha y hora solicitada"><?= htmlspecialchars($cita['fecha_hora_solicitada']) ?></td>
                    <td data-label="Sede"><?= $cita['sucursal_nombre'] ? htmlspecialchars($cita['sucursal_nombre']) : '-' ?></td>
                    <td data-label="Estudio"><?= htmlspecialchars(ESTUDIOS[$cita['estudio']] ?? $cita['estudio']) ?></td>
                    <td data-label="Estado">
                      <span class="estado-badge estado-<?= htmlspecialchars($cita['estado']) ?>">
                        <?= htmlspecialchars(ESTADOS_CITA[$cita['estado']] ?? $cita['estado']) ?>
                      </span>
                    </td>
                    <td data-label="Confirmacion"><?= $cita['fecha_hora_confirmada'] ? htmlspecialchars($cita['fecha_hora_confirmada']) : '-' ?></td>
                    <td data-label="Notas"><?= $cita['notas_admin'] ? htmlspecialchars($cita['notas_admin']) : '-' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <h3>Resultados</h3>
        <?php render_lista_resultados($resultados, $imagenesPorResultado, 'Este paciente todavia no tiene resultados cargados.'); ?>
      </section>
    <?php endif; ?>
  </main>
</body>

</html>
