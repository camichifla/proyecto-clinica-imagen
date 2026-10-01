<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Solicitudes de Cita - Clinica Imagen</title>
  <link rel="stylesheet" href="./assets/css/styles.css">
  <link rel="stylesheet" href="./assets/css/solicitudes-cita.css">
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
      <h1>Solicitudes de Cita</h1>

      <?php if (isset($_GET['confirmada'])): ?>
        <p class="aviso-exito">La cita fue confirmada.</p>
      <?php elseif (isset($_GET['rechazada'])): ?>
        <p class="aviso-exito">La cita fue rechazada.</p>
      <?php elseif (isset($_GET['error'])): ?>
        <p class="aviso-error">No se pudo procesar la solicitud (puede que ya haya sido resuelta).</p>
      <?php endif; ?>

      <?php if (!$citas): ?>
        <p>No hay solicitudes de cita pendientes.</p>
      <?php else: ?>
        <div class="tabla-scroll">
          <table class="tabla-datos">
            <thead>
              <tr>
                <th scope="col">Fecha y hora solicitada</th>
                <th scope="col">Paciente</th>
                <th scope="col">Estudio</th>
                <th scope="col">Sede</th>
                <th scope="col">Profesional</th>
                <th scope="col">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($citas as $cita): ?>
                <tr>
                  <td data-label="Fecha y hora solicitada"><?= htmlspecialchars($cita['fecha_hora_solicitada']) ?></td>
                  <td data-label="Paciente"><?= htmlspecialchars(cita_nombre_paciente($cita)) ?></td>
                  <td data-label="Estudio"><?= htmlspecialchars(ESTUDIOS[$cita['estudio']] ?? $cita['estudio']) ?></td>
                  <td data-label="Sede"><?= $cita['sucursal_nombre'] ? htmlspecialchars($cita['sucursal_nombre']) : '-' ?></td>
                  <td data-label="Profesional"><?= htmlspecialchars($cita['profesional_apellido'] . ', ' . $cita['profesional_nombre']) ?></td>
                  <td data-label="Acciones">
                    <div class="acciones-solicitud">
                      <form method="post" action="solicitudes-cita.php" class="form-confirmar-cita">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="confirmar">
                        <input type="hidden" name="cita_id" value="<?= (int) $cita['id'] ?>">
                        <button type="submit" class="btn-primary btn-confirmar">Confirmar</button>
                      </form>
                      <form method="post" action="solicitudes-cita.php" class="form-rechazar-cita">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="rechazar">
                        <input type="hidden" name="cita_id" value="<?= (int) $cita['id'] ?>">
                        <label for="notas-<?= (int) $cita['id'] ?>">Motivo (opcional)</label>
                        <textarea id="notas-<?= (int) $cita['id'] ?>" name="notas_admin" maxlength="<?= NOTAS_ADMIN_MAX ?>" rows="2"></textarea>
                        <button type="submit" class="btn-secundario btn-rechazar">Rechazar</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </main>
</body>

</html>
