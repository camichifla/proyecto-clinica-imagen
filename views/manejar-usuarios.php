<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manejar Usuarios - Clinica Imagen</title>
  <link rel="stylesheet" href="./assets/css/styles.css">
  <link rel="stylesheet" href="./assets/css/manejar-usuarios.css">
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
      <h1>Manejar Usuarios</h1>

      <?php if ($mensajeToggle): ?>
        <p class="aviso-exito"><?= htmlspecialchars($mensajeToggle) ?></p>
      <?php elseif ($errorToggle): ?>
        <p class="aviso-error"><?= htmlspecialchars($errorToggle) ?></p>
      <?php endif; ?>

      <?php if (!$cuentas): ?>
        <p>Todavia no hay cuentas registradas.</p>
      <?php else: ?>
        <div class="tabla-scroll">
          <table class="tabla-datos">
            <thead>
              <tr>
                <th scope="col">Nombre</th>
                <th scope="col">Email</th>
                <th scope="col">Rol</th>
                <th scope="col">Verificado</th>
                <th scope="col">Activo</th>
                <th scope="col"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cuentas as $fila): ?>
                <?php
                  $esUsuarioActual   = (int) $fila['cuenta_id'] === $usuario['cuenta_id'];
                  $activa            = (int) $fila['activo'] === 1;
                  $esUltimoAdminActivo = $fila['rol'] === 'administrador' && $activa && $adminActivosCount <= 1;
                ?>
                <tr>
                  <td data-label="Nombre"><?= htmlspecialchars(trim($fila['nombre'] . ' ' . $fila['apellido'])) ?></td>
                  <td data-label="Email"><?= htmlspecialchars($fila['email']) ?></td>
                  <td data-label="Rol"><?= htmlspecialchars(ROLES_TODOS[$fila['rol']] ?? $fila['rol']) ?></td>
                  <td data-label="Verificado">
                    <span class="estado-badge <?= (int) $fila['verificado'] === 1 ? 'estado-si' : 'estado-no' ?>">
                      <?= (int) $fila['verificado'] === 1 ? 'Si' : 'No' ?>
                    </span>
                  </td>
                  <td data-label="Activo">
                    <span class="estado-badge <?= $activa ? 'estado-si' : 'estado-no' ?>">
                      <?= $activa ? 'Si' : 'No' ?>
                    </span>
                  </td>
                  <td data-label="">
                    <?php if ($esUsuarioActual): ?>
                      <span class="etiqueta-tu-cuenta">Tu cuenta</span>
                    <?php else: ?>
                      <form method="post" action="manejar-usuarios.php" class="form-toggle-activo">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="toggle_activo">
                        <input type="hidden" name="cuenta_id" value="<?= (int) $fila['cuenta_id'] ?>">
                        <button type="submit" class="btn-secundario"
                                <?= $esUltimoAdminActivo ? 'disabled title="No se puede desactivar el ultimo administrador activo."' : '' ?>>
                          <?= $activa ? 'Desactivar' : 'Reactivar' ?>
                        </button>
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

    <section class="seccion-panel">
      <h2>Asignar Paciente a Medico</h2>

      <?php if ($mensajeAsignacion): ?>
        <p class="aviso-exito"><?= htmlspecialchars($mensajeAsignacion) ?></p>
      <?php elseif ($errorAsignacion): ?>
        <p class="aviso-error"><?= htmlspecialchars($errorAsignacion) ?></p>
      <?php endif; ?>

      <form method="post" action="manejar-usuarios.php" class="campo-fila">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="accion" value="asignar_paciente">

        <div class="campo">
          <label for="asignar-medico-id-buscar">Medico</label>
          <input type="text" id="asignar-medico-id-buscar" data-lista="asignar-medico-id-lista" data-buscador-combo="asignar-medico-id"
                 autocomplete="off" placeholder="Buscar por apellido o nombre">
          <datalist id="asignar-medico-id-lista">
            <?php foreach ($medicosParaAsignar as $medico): ?>
              <option data-id="<?= (int) $medico['id'] ?>" value="<?= htmlspecialchars($medico['apellido'] . ', ' . $medico['nombre']) ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <input type="hidden" id="asignar-medico-id" name="medico_id">
          <?php if (!$medicosParaAsignar): ?><p class="campo-error">Todavia no hay medicos cargados.</p><?php endif; ?>
        </div>

        <div class="campo">
          <label for="asignar-paciente-id-buscar">Paciente</label>
          <input type="text" id="asignar-paciente-id-buscar" data-lista="asignar-paciente-id-lista" data-buscador-combo="asignar-paciente-id"
                 autocomplete="off" placeholder="Buscar por apellido, nombre o CI">
          <datalist id="asignar-paciente-id-lista">
            <?php foreach ($pacientesParaAsignar as $paciente): ?>
              <option data-id="<?= (int) $paciente['id'] ?>" value="<?= htmlspecialchars($paciente['apellido'] . ', ' . $paciente['nombre'] . ' (CI ' . $paciente['ci'] . ')') ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <input type="hidden" id="asignar-paciente-id" name="paciente_id">
          <?php if (!$pacientesParaAsignar): ?><p class="campo-error">Todavia no hay pacientes registrados.</p><?php endif; ?>
        </div>

        <button type="submit" class="btn-primary">Asignar</button>
      </form>

      <?php if (!$asignaciones): ?>
        <p>Todavia no hay asignaciones.</p>
      <?php else: ?>
        <div class="tabla-scroll">
          <table class="tabla-datos">
            <thead>
              <tr>
                <th scope="col">Medico</th>
                <th scope="col">Paciente</th>
                <th scope="col"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($asignaciones as $asignacion): ?>
                <tr>
                  <td data-label="Medico"><?= htmlspecialchars($asignacion['medico_apellido'] . ', ' . $asignacion['medico_nombre']) ?></td>
                  <td data-label="Paciente"><?= htmlspecialchars($asignacion['paciente_apellido'] . ', ' . $asignacion['paciente_nombre']) ?></td>
                  <td data-label="">
                    <form method="post" action="manejar-usuarios.php" class="form-toggle-activo">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <input type="hidden" name="accion" value="quitar_asignacion">
                      <input type="hidden" name="medico_id" value="<?= (int) $asignacion['medico_id'] ?>">
                      <input type="hidden" name="paciente_id" value="<?= (int) $asignacion['paciente_id'] ?>">
                      <button type="submit" class="btn-secundario">Quitar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="seccion-panel">
      <h2>Crear cuenta de personal</h2>

      <?php if ($creacionExitosa): ?>
        <p class="aviso-exito">Cuenta creada correctamente.</p>
      <?php endif; ?>

      <?php if (!empty($erroresCreacion['general'])): ?>
        <p class="aviso-error"><?= htmlspecialchars($erroresCreacion['general']) ?></p>
      <?php endif; ?>

      <form method="post" action="manejar-usuarios.php" id="form-crear-staff" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="accion" value="crear_staff">

        <div class="campo">
          <label for="rol">Rol</label>
          <select id="rol" name="rol" class="selector-combo" <?= isset($erroresCreacion['rol']) ? 'aria-invalid="true"' : '' ?>>
            <option value="">Elegi un rol</option>
            <?php foreach (ROLES_STAFF as $valor => $etiqueta): ?>
              <option value="<?= htmlspecialchars($valor) ?>" <?= $valoresCreacion['rol'] === $valor ? 'selected' : '' ?>>
                <?= htmlspecialchars($etiqueta) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($erroresCreacion['rol'])): ?><p class="campo-error"><?= htmlspecialchars($erroresCreacion['rol']) ?></p><?php endif; ?>
        </div>

        <div class="campo-fila">
          <div class="campo">
            <label for="nombre"><?= htmlspecialchars(REGLAS_STAFF['nombre']['label']) ?></label>
            <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($valoresCreacion['nombre']) ?>"
                   <?= isset($erroresCreacion['nombre']) ? 'aria-invalid="true"' : '' ?>>
            <?php if (isset($erroresCreacion['nombre'])): ?><p class="campo-error"><?= htmlspecialchars($erroresCreacion['nombre']) ?></p><?php endif; ?>
          </div>

          <div class="campo">
            <label for="apellido"><?= htmlspecialchars(REGLAS_STAFF['apellido']['label']) ?></label>
            <input type="text" id="apellido" name="apellido" value="<?= htmlspecialchars($valoresCreacion['apellido']) ?>"
                   <?= isset($erroresCreacion['apellido']) ? 'aria-invalid="true"' : '' ?>>
            <?php if (isset($erroresCreacion['apellido'])): ?><p class="campo-error"><?= htmlspecialchars($erroresCreacion['apellido']) ?></p><?php endif; ?>
          </div>
        </div>

        <div class="campo" id="campo-especializacion">
          <label id="especializacion-label">Especializacion</label>
          <div class="check-grid" role="group" aria-labelledby="especializacion-label"
               <?= isset($erroresCreacion['especializacion']) ? 'aria-invalid="true"' : '' ?>>
            <?php foreach (ESPECIALIZACIONES as $valor => $etiqueta): ?>
              <label>
                <input type="checkbox" name="especializacion[]" value="<?= htmlspecialchars($valor) ?>"
                       <?= in_array($valor, $valoresCreacion['especializacion'], true) ? 'checked' : '' ?>>
                <?= htmlspecialchars($etiqueta) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if (isset($erroresCreacion['especializacion'])): ?><p class="campo-error"><?= htmlspecialchars($erroresCreacion['especializacion']) ?></p><?php endif; ?>
        </div>

        <div class="campo">
          <label for="email"><?= htmlspecialchars(REGLAS_STAFF['email']['label']) ?></label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($valoresCreacion['email']) ?>"
                 <?= isset($erroresCreacion['email']) ? 'aria-invalid="true"' : '' ?>>
          <?php if (isset($erroresCreacion['email'])): ?><p class="campo-error"><?= htmlspecialchars($erroresCreacion['email']) ?></p><?php endif; ?>
        </div>

        <div class="campo">
          <label for="contrasena"><?= htmlspecialchars(REGLAS_STAFF['contrasena']['label']) ?></label>
          <input type="password" id="contrasena" name="contrasena" value=""
                 <?= isset($erroresCreacion['contrasena']) ? 'aria-invalid="true"' : '' ?>>
          <?php if (isset($erroresCreacion['contrasena'])): ?><p class="campo-error"><?= htmlspecialchars($erroresCreacion['contrasena']) ?></p><?php endif; ?>
        </div>

        <button type="submit" class="btn-primary">Crear cuenta</button>
      </form>
    </section>
  </main>

  <script src="assets/js/buscador-combo.js"></script>
  <script src="assets/js/selector-combo.js"></script>
  <script>
    // Shows/hides especializacion by rol. UX convenience only: the server
    // re-validates that especializacion is present iff rol === 'profesional'
    // regardless of what the client sends.
    (function () {
      var rolSelect = document.getElementById('rol');
      var campoEspecializacion = document.getElementById('campo-especializacion');
      var especializacionCheckboxes = campoEspecializacion
        ? campoEspecializacion.querySelectorAll('input[name="especializacion[]"]')
        : [];
      if (!rolSelect || !campoEspecializacion || !especializacionCheckboxes.length) {
        return;
      }

      function aplicar() {
        var esProfesional = rolSelect.value === 'profesional';
        campoEspecializacion.hidden = !esProfesional;
        if (!esProfesional) {
          Array.prototype.forEach.call(especializacionCheckboxes, function (casilla) {
            casilla.checked = false;
          });
        }
      }

      rolSelect.addEventListener('change', aplicar);
      aplicar();
    })();
  </script>
</body>

</html>
