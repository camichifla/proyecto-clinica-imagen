<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Agendar Cita - Clinica Imagen</title>
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
    <div class="layout-agendar-citas">
    <section class="seccion-auth">
      <h1>Agendar Cita</h1>

      <?php if (isset($_GET['ok'])): ?>
        <p class="aviso-exito">Tu solicitud de cita fue enviada. Te avisaremos cuando sea confirmada.</p>
      <?php elseif (isset($_GET['cancelado'])): ?>
        <p class="aviso-exito">La cita fue cancelada.</p>
      <?php elseif (isset($_GET['reprogramado'])): ?>
        <p class="aviso-exito">La cita fue reprogramada.</p>
      <?php elseif (isset($_GET['error']) && $_GET['error'] === 'reprogramar'): ?>
        <p class="campo-error">Esa cita ya no se puede reprogramar (cambio de estado o no te pertenece).</p>
      <?php endif; ?>

      <form method="post" action="agendar-cita.php" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="accion" value="<?= htmlspecialchars($formAccion) ?>">
        <?php if ($formCitaId): ?><input type="hidden" name="cita_id" value="<?= (int) $formCitaId ?>"><?php endif; ?>

        <div class="campo">
          <label for="estudio">Estudio</label>
          <select id="estudio" name="estudio" class="selector-combo" <?= isset($errores['estudio']) ? 'aria-invalid="true"' : '' ?>>
            <option value="">Elegi un estudio</option>
            <?php foreach (ESTUDIOS_BASE as $valor => $etiqueta): ?>
              <option value="<?= htmlspecialchars($valor) ?>" <?= $valores['estudio'] === $valor ? 'selected' : '' ?>>
                <?= htmlspecialchars($etiqueta) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errores['estudio'])): ?><p class="campo-error"><?= htmlspecialchars($errores['estudio']) ?></p><?php endif; ?>
        </div>

        <div class="campo">
          <label for="sucursal">Sede</label>
          <select id="sucursal" name="sucursal_id" class="selector-combo" <?= isset($errores['sucursal_id']) ? 'aria-invalid="true"' : '' ?>>
            <option value="">Elegi una sede</option>
            <?php foreach ($sucursalesFilas as $sucursal): ?>
              <?php
                $etiquetaSucursal = ($sucursal['direccion'] ?? '') !== ''
                    ? $sucursal['nombre'] . ' — ' . $sucursal['direccion']
                    : $sucursal['nombre'];
              ?>
              <option value="<?= (int) $sucursal['id'] ?>" <?= (string) $sucursal['id'] === $valores['sucursal_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($etiquetaSucursal) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errores['sucursal_id'])): ?><p class="campo-error"><?= htmlspecialchars($errores['sucursal_id']) ?></p><?php endif; ?>
        </div>

        <div class="campo">
          <label for="profesional_id">Profesional</label>
          <select id="profesional_id" name="profesional_id" class="selector-combo" <?= isset($errores['profesional_id']) ? 'aria-invalid="true"' : '' ?>>
            <option value="">Elegi un profesional</option>
            <?php foreach ($profesionales as $profesional): ?>
              <?php $especializacionesDelProfesional = $especializacionesPorProfesional[(int) $profesional['id']] ?? []; ?>
              <option value="<?= (int) $profesional['id'] ?>"
                      data-especializaciones='<?= htmlspecialchars(json_encode($especializacionesDelProfesional)) ?>'
                      <?= (string) $profesional['id'] === $valores['profesional_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($profesional['apellido'] . ', ' . $profesional['nombre']) ?> — <?= htmlspecialchars(implode(', ', array_map(fn($e) => ESTUDIOS_BASE[$e] ?? $e, $especializacionesDelProfesional))) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errores['profesional_id'])): ?><p class="campo-error"><?= htmlspecialchars($errores['profesional_id']) ?></p><?php endif; ?>
          <?php if (!$profesionales): ?><p class="campo-error">Todavia no hay profesionales cargados.</p><?php endif; ?>
        </div>

        <div class="campo">
          <label id="fecha-label">Fecha</label>
          <div class="selector-fecha" data-min="<?= htmlspecialchars($fechaMin) ?>" data-max="<?= htmlspecialchars($fechaMax) ?>">
            <button type="button" id="fecha-boton" class="selector-fecha-boton"
                    aria-haspopup="dialog" aria-expanded="false"
                    aria-labelledby="fecha-label"
                    <?= isset($errores['fecha']) ? 'aria-invalid="true"' : '' ?>>
              <span class="selector-fecha-texto">Elegi una fecha</span>
            </button>
            <input type="hidden" id="fecha" name="fecha" value="<?= htmlspecialchars($valores['fecha']) ?>">
            <div class="calendario" id="calendario" role="dialog" aria-label="Elegir fecha" hidden>
              <div class="calendario-header">
                <button type="button" class="calendario-nav" data-dir="-1" aria-label="Mes anterior">‹</button>
                <span class="calendario-mes-actual"></span>
                <button type="button" class="calendario-nav" data-dir="1" aria-label="Mes siguiente">›</button>
              </div>
              <div class="calendario-dias-semana"></div>
              <div class="calendario-grid"></div>
            </div>
          </div>
          <?php if (isset($errores['fecha'])): ?><p class="campo-error"><?= htmlspecialchars($errores['fecha']) ?></p><?php endif; ?>
        </div>

        <div class="campo">
          <label for="hora">Hora</label>
          <select id="hora" name="hora" class="selector-combo" disabled <?= isset($errores['hora']) ? 'aria-invalid="true"' : '' ?>>
            <option value="">Elegi sede y fecha primero</option>
          </select>
          <?php if (isset($errores['hora'])): ?><p class="campo-error"><?= htmlspecialchars($errores['hora']) ?></p><?php endif; ?>
        </div>

        <button type="submit" class="btn-primary"><?= $formAccion === 'reprogramar' ? 'Guardar cambios' : 'Solicitar cita' ?></button>
      </form>
    </section>

    <section class="seccion-panel">
      <h2>Mis Citas</h2>

      <?php if (!$citas): ?>
        <p>Todavia no solicitaste ninguna cita.</p>
      <?php else: ?>
        <div class="tabla-scroll">
          <table class="tabla-datos">
            <thead>
              <tr>
                <th scope="col">Fecha y hora de la cita</th>
                <th scope="col">Sede</th>
                <th scope="col">Estudio</th>
                <th scope="col">Estado</th>
                <th scope="col"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($citas as $cita): ?>
                <tr>
                  <td data-label="Fecha y hora de la cita"><?= htmlspecialchars($cita['fecha_hora_solicitada']) ?></td>
                  <td data-label="Sede"><?= $cita['sucursal_nombre'] ? htmlspecialchars($cita['sucursal_nombre']) : '-' ?></td>
                  <td data-label="Estudio"><?= htmlspecialchars(ESTUDIOS_BASE[$cita['estudio']] ?? $cita['estudio']) ?></td>
                  <td data-label="Estado">
                    <span class="estado-badge estado-<?= htmlspecialchars($cita['estado']) ?>">
                      <?= htmlspecialchars(ESTADOS_CITA[$cita['estado']] ?? $cita['estado']) ?>
                    </span>
                  </td>
                  <td data-label="">
                    <?php if ($cita['estado'] === 'pendiente'): ?>
                      <a href="agendar-cita.php?editar=<?= (int) $cita['id'] ?>" class="btn-secundario">Reprogramar</a>
                      <form method="post" action="agendar-cita.php" class="form-cancelar-cita">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="cancelar">
                        <input type="hidden" name="cita_id" value="<?= (int) $cita['id'] ?>">
                        <label for="motivo-cancelar-<?= (int) $cita['id'] ?>">Motivo (opcional)</label>
                        <textarea id="motivo-cancelar-<?= (int) $cita['id'] ?>" name="notas_admin" maxlength="<?= NOTAS_ADMIN_MAX ?>" rows="2"></textarea>
                        <button type="submit" class="btn-secundario">Cancelar</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
    </div>
  </main>

  <script>
    // Sucursal-scoped data: name, direccion, allowed estudios ([] = sin
    // restriccion), weekly shifts keyed by ISO weekday, and assigned
    // profesionales. Client filtering is UX only — the server re-validates
    // every constraint independently (design.md Server-Side Re-Validation).
    var SUCURSALES = <?= json_encode($datosSucursales, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    var HORA_ELEGIDA = <?= json_encode($valores['hora']) ?>; // re-render after a validation error
  </script>
  <script src="./assets/js/agendado-cascada.js"></script>
  <script src="./assets/js/selector-combo.js"></script>
</body>

</html>
