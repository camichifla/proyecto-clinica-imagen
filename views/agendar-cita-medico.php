<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Agendar Cita para Paciente - Clinica Imagen</title>
  <link rel="stylesheet" href="./assets/css/styles.css">
  <link rel="stylesheet" href="./assets/css/agendar-cita-medico.css">
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
    <section class="seccion-auth seccion-ancha">
      <h1>Agendar Cita para Paciente</h1>

      <?php if (isset($_GET['ok']) && $_GET['ok'] === 'pendiente'): ?>
        <p class="aviso-exito">Cita creada. Se vinculara automaticamente cuando ese email se registre.</p>
      <?php elseif (isset($_GET['ok'])): ?>
        <p class="aviso-exito">La cita fue creada correctamente.</p>
      <?php endif; ?>

      <?php if (isset($errores['general'])): ?>
        <p class="campo-error"><?= htmlspecialchars($errores['general']) ?></p>
      <?php endif; ?>

      <form method="post" action="agendar-cita-medico.php" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

        <div class="campo">
          <label id="modo-paciente-label">Paciente</label>
          <div class="radio-row" role="group" aria-labelledby="modo-paciente-label">
            <label><input type="radio" name="modo_paciente" value="existente" id="modo-paciente-existente" <?= $valores['modo_paciente'] === 'existente' ? 'checked' : '' ?>> Paciente existente</label>
            <label><input type="radio" name="modo_paciente" value="nuevo" id="modo-paciente-nuevo" <?= $valores['modo_paciente'] === 'nuevo' ? 'checked' : '' ?>> Paciente nuevo</label>
          </div>
        </div>

        <?php
        $pacienteConectadoActual = null;
        foreach ($pacientesConectados as $paciente) {
            if ((string) $paciente['id'] === $valores['paciente_id']) {
                $pacienteConectadoActual = $paciente;
                break;
            }
        }
        ?>
        <div id="panel-paciente-existente" class="campo" <?= $valores['modo_paciente'] === 'existente' ? '' : 'hidden' ?>>
          <label for="paciente_id_buscar">Paciente</label>
          <input type="text" id="paciente_id_buscar" data-lista="paciente_id_lista" data-buscador-combo="paciente_id"
                 autocomplete="off" placeholder="Buscar por apellido, nombre o CI"
                 value="<?= $pacienteConectadoActual ? htmlspecialchars($pacienteConectadoActual['apellido'] . ', ' . $pacienteConectadoActual['nombre'] . ' (CI ' . $pacienteConectadoActual['ci'] . ')') : '' ?>"
                 <?= isset($errores['paciente_id']) ? 'aria-invalid="true"' : '' ?>>
          <datalist id="paciente_id_lista">
            <?php foreach ($pacientesConectados as $paciente): ?>
              <option data-id="<?= (int) $paciente['id'] ?>" value="<?= htmlspecialchars($paciente['apellido'] . ', ' . $paciente['nombre'] . ' (CI ' . $paciente['ci'] . ')') ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <input type="hidden" id="paciente_id" name="paciente_id" value="<?= htmlspecialchars($valores['paciente_id']) ?>">
          <?php if (isset($errores['paciente_id'])): ?><p class="campo-error"><?= htmlspecialchars($errores['paciente_id']) ?></p><?php endif; ?>
          <?php if (!$pacientesConectados): ?><p class="campo-hint">Todavia no tenes pacientes conectados. Usa "Paciente nuevo".</p><?php endif; ?>
        </div>

        <div id="panel-paciente-nuevo" class="campo" <?= $valores['modo_paciente'] === 'nuevo' ? '' : 'hidden' ?>>
          <div class="campo-fila">
            <div class="campo">
              <label for="paciente_nombre_nuevo">Nombre</label>
              <input type="text" id="paciente_nombre_nuevo" name="paciente_nombre_nuevo" value="<?= htmlspecialchars($valores['paciente_nombre_nuevo']) ?>"
                     <?= isset($errores['paciente_nombre_nuevo']) ? 'aria-invalid="true"' : '' ?>>
              <?php if (isset($errores['paciente_nombre_nuevo'])): ?><p class="campo-error"><?= htmlspecialchars($errores['paciente_nombre_nuevo']) ?></p><?php endif; ?>
            </div>
            <div class="campo">
              <label for="paciente_apellido_nuevo">Apellido</label>
              <input type="text" id="paciente_apellido_nuevo" name="paciente_apellido_nuevo" value="<?= htmlspecialchars($valores['paciente_apellido_nuevo']) ?>"
                     <?= isset($errores['paciente_apellido_nuevo']) ? 'aria-invalid="true"' : '' ?>>
              <?php if (isset($errores['paciente_apellido_nuevo'])): ?><p class="campo-error"><?= htmlspecialchars($errores['paciente_apellido_nuevo']) ?></p><?php endif; ?>
            </div>
          </div>
          <div class="campo">
            <label for="paciente_email_nuevo">Email</label>
            <input type="email" id="paciente_email_nuevo" name="paciente_email_nuevo" value="<?= htmlspecialchars($valores['paciente_email_nuevo']) ?>"
                   <?= isset($errores['paciente_email_nuevo']) ? 'aria-invalid="true"' : '' ?>>
            <?php if (isset($errores['paciente_email_nuevo'])): ?><p class="campo-error"><?= htmlspecialchars($errores['paciente_email_nuevo']) ?></p><?php endif; ?>
            <p class="campo-hint">Si el paciente todavia no tiene cuenta, la cita queda pendiente y se vincula sola cuando se registre con este email.</p>
          </div>
        </div>

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
                <?= htmlspecialchars($profesional['apellido'] . ', ' . $profesional['nombre']) ?> — <?= htmlspecialchars(implode(', ', array_map(fn($e) => ESTUDIOS[$e] ?? $e, $especializacionesDelProfesional))) ?>
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

        <div id="orden-campos">
          <input type="hidden" id="tipo_orden" name="tipo_orden" value="<?= htmlspecialchars((string) $tipoOrdenActual) ?>">

          <!-- ==================== ORDEN DE ESTUDIO ==================== -->
          <div id="orden-panel-estudio" class="orden-panel" <?= $tipoOrdenActual === 'estudio' ? '' : 'hidden' ?>>

            <div class="orden-card orden-aviso">
              <h3>AVISO IMPORTANTE</h3>
              <p>Todos los estudios indicados en esta orden requieren coordinacion previa.</p>
              <p>Es necesario venir a la clinica sin nada de metal del cuello hacia arriba.</p>
              <p>Los presupuestos dados por telefono se confirman con la presentacion de este formulario.</p>
            </div>

            <details class="orden-card">
              <summary>Radiografías Intrabucales</summary>
              <label>Indicar region de interes</label>
              <?php orden_dientes_grid('radio_intra', DIENTES_COMPLETO); ?>
              <?php orden_checkbox_grupo('radio_intra_tipo', ORDEN_CAMPOS_ESTUDIO['radio_intra_tipo']['opciones'], true); ?>
            </details>

            <details class="orden-card">
              <summary>Otros estudios extraorales</summary>
              <?php orden_checkbox_grupo('otros_estudios_extraorales', ORDEN_CAMPOS_ESTUDIO['otros_estudios_extraorales']['opciones']); ?>
            </details>

            <details class="orden-card">
              <summary>Documentación para ortodoncia</summary>

              <h4>Radiografías extraorales</h4>
              <div class="orden-grid-2">
                <fieldset class="orden-fieldset orden-textogrupo">
                  <?php orden_checkbox_item('radio_extraoral', 'Panorámica (OPT)', 'orden-check-texto'); ?>
                  <label>Indicacion <?php orden_texto('opt_indicacion'); ?></label>
                </fieldset>
                <fieldset class="orden-fieldset orden-grupo">
                  <?php orden_checkbox_item('radio_extraoral', 'Telerradiografía Perfil', 'orden-padre'); ?>
                  <?php foreach (ORDEN_CAMPOS_ESTUDIO['telerradio_perfil_tipo']['opciones'] as $opcion): ?>
                    <?php orden_checkbox_item('telerradio_perfil_tipo', $opcion, 'orden-hijo'); ?>
                  <?php endforeach; ?>
                </fieldset>
                <fieldset class="orden-fieldset orden-grupo">
                  <?php orden_checkbox_item('radio_extraoral', 'Telerradiografía Frontal', 'orden-padre'); ?>
                  <?php orden_checkbox_solo('telerradio_frontal_analisis', 'Con analisis', 'orden-hijo'); ?>
                </fieldset>
              </div>

              <h4>Estudios cefalométricos computarizados</h4>
              <?php orden_checkbox_grupo('estudio_cefalo_compu', ORDEN_CAMPOS_ESTUDIO['estudio_cefalo_compu']['opciones']); ?>
              <label>Otros <?php orden_texto('estudio_cefalo_compu_otros'); ?></label>

              <h4>Fotografías</h4>
              <div class="check-grid"><?php orden_checkbox_item('fotografias', 'Todas las fotografías'); ?></div>
              <div class="orden-grid-2">
                <fieldset class="orden-fieldset">
                  <legend>Fotografias faciales</legend>
                  <?php foreach (['Rostro frente', 'Perfil', 'Rostro sonrisa ', '3/4 perfil'] as $opcion): ?>
                    <?php orden_checkbox_item('fotografias', $opcion); ?>
                  <?php endforeach; ?>
                </fieldset>
                <fieldset class="orden-fieldset">
                  <legend>Fotografias bucales</legend>
                  <?php foreach (['2 oclusales ', '2 llaves de oclusión ', '2 overjet/overbite ', '1 anterior en inoclusión', '1 oclusión frente'] as $opcion): ?>
                    <?php orden_checkbox_item('fotografias', $opcion); ?>
                  <?php endforeach; ?>
                </fieldset>
              </div>
              <div class="check-grid"><?php orden_checkbox_item('fotografias', 'con contacto oclusales'); ?></div>
              <fieldset class="orden-fieldset orden-textogrupo">
                <?php orden_checkbox_item('fotografias', 'foto en dinámica', 'orden-check-texto'); ?>
                <label>Indique su interes <?php orden_texto('fotografia_interes'); ?></label>
              </fieldset>

              <h4>Modelos digitales (con visualizador 3D)</h4>
              <div class="orden-grid-2">
                <fieldset class="orden-fieldset">
                  <legend>Modelo digital</legend>
                  <?php foreach (['Ortodoncia', 'Ortopedia', 'Diagnóstico bolton', 'Diagnóstico Moyers', 'Diagnóstico Medidas dentarias'] as $opcion): ?>
                    <?php orden_checkbox_item('modelos_digitales', $opcion); ?>
                  <?php endforeach; ?>
                </fieldset>
                <fieldset class="orden-fieldset">
                  <legend>Modelo impreso</legend>
                  <?php foreach (['Impresión de modelos zocalados', 'Impresión de modelos de trabajo', 'Mordida constructiva', 'Duplicado en yeso', 'Modelos articulados con bisagra posterior'] as $opcion): ?>
                    <?php orden_checkbox_item('modelos_digitales', $opcion); ?>
                  <?php endforeach; ?>
                </fieldset>
              </div>

              <h4>Ortodoncia invisible (alineadores)</h4>
              <label>Marca o sistema <?php orden_texto('ortodoncia_marca'); ?></label>
              <?php orden_checkbox_grupo('ortodoncia_tipo', ORDEN_CAMPOS_ESTUDIO['ortodoncia_tipo']['opciones'], true); ?>
              <label>Informacion clinica <?php orden_textarea('ortodoncia_info_clinica'); ?></label>
            </details>

            <details class="orden-card">
              <summary>Tomografía Computarizada Volumétrica CONE BEAM</summary>
              <label>Indicar region de interes</label>
              <div class="check-grid inline">
                <?php orden_checkbox_item('tomo_cone_beam', 'Maxilar completo'); ?>
                <?php orden_checkbox_item('tomo_cone_beam', 'Mandíbula completa'); ?>
              </div>
              <?php orden_dientes_grid('tomo_cone_beam', DIENTES_COMPLETO, true); ?>
              <?php orden_checkbox_grupo('tomo_cone_beam_tipo', ORDEN_CAMPOS_ESTUDIO['tomo_cone_beam_tipo']['opciones'], true); ?>
              <label>Elementos sueltos <?php orden_texto('tomo_elementos_sueltos'); ?></label>

              <h4>Indique estudio para</h4>
              <?php orden_checkbox_grupo('tomo_tipo_estudio', ORDEN_CAMPOS_ESTUDIO['tomo_tipo_estudio']['opciones'], true); ?>
              <label>Interes del estudio <?php orden_textarea('interes_estudio_tomo'); ?></label>
            </details>

            <details class="orden-card">
              <summary>CIRUGÍA GUIADA PARA IMPLANTES</summary>
              <?php orden_checkbox_grupo('guia_implantes_servicio', ORDEN_CAMPOS_ESTUDIO['guia_implantes_servicio']['opciones'], true); ?>
              <div class="orden-grid-2">
                <fieldset class="orden-fieldset orden-textogrupo">
                  <?php orden_checkbox_item('guia_implante_tipo', 'Guía de precisión', 'orden-check-texto'); ?>
                  <label>Marca de implante <?php orden_texto('implante_marca'); ?></label>
                </fieldset>
                <fieldset class="orden-fieldset">
                  <?php orden_checkbox_item('guia_implante_tipo', 'Guía de fresa iniciadora'); ?>
                </fieldset>
              </div>
              <div class="orden-grid-2">
                <label>Indique la ubicacion del implante a colocar <?php orden_texto('implante_ubicacion'); ?></label>
                <label>Fecha probable de cirugia <?php orden_texto('implante_fecha_cirugia', 'date'); ?></label>
              </div>
            </details>

            <details class="orden-card">
              <summary>REALIDAD VIRTUAL M3DMIX</summary>
              <?php orden_checkbox_grupo('realidad_virtual_m3dmix', ORDEN_CAMPOS_ESTUDIO['realidad_virtual_m3dmix']['opciones']); ?>
            </details>

            <details class="orden-card">
              <summary>Ecografías</summary>
              <?php orden_checkbox_grupo('ecografias', ORDEN_CAMPOS_ESTUDIO['ecografias']['opciones'], true); ?>
              <label>Indicar interes del estudio <?php orden_texto('eco_interes'); ?></label>
            </details>

            <details class="orden-card">
              <summary>Otros servicios</summary>

              <fieldset class="orden-fieldset orden-textogrupo">
                <?php orden_checkbox_item('otros_servicios', 'Sólo escaneo', 'orden-check-texto'); ?>
                <label>Indicar interes del estudio <?php orden_texto('solo_escaneo_interes'); ?></label>
              </fieldset>

              <div class="check-grid">
                <?php orden_checkbox_item('otros_servicios', 'Placa neuromiorelajante/DOE'); ?>
                <?php orden_checkbox_item('otros_servicios', 'DAM'); ?>
                <?php orden_checkbox_item('otros_servicios', 'Diseño sonrisa e impresión en resina mockup'); ?>
              </div>

              <fieldset class="orden-fieldset orden-grupo">
                <?php orden_checkbox_item('otros_servicios', 'Escaneo final de ortodoncia + placas de contención.', 'orden-padre'); ?>
                <?php orden_checkbox_grupo('contencion_arcada', ORDEN_CAMPOS_ESTUDIO['contencion_arcada']['opciones'], true); ?>
                <?php orden_checkbox_grupo('contencion_grosor', ORDEN_CAMPOS_ESTUDIO['contencion_grosor']['opciones'], true); ?>
                <?php orden_checkbox_grupo('contencion_forma', ORDEN_CAMPOS_ESTUDIO['contencion_forma']['opciones'], true); ?>
              </fieldset>

              <fieldset class="orden-fieldset orden-grupo">
                <?php orden_checkbox_item('otros_servicios', 'Placas de blanqueamiento', 'orden-padre'); ?>
                <?php orden_checkbox_grupo('blanqueamiento_arcada', ORDEN_CAMPOS_ESTUDIO['blanqueamiento_arcada']['opciones'], true); ?>
              </fieldset>

              <div class="check-grid">
                <?php orden_checkbox_item('otros_servicios', 'Perioguide para cirugía gingival'); ?>
                <?php orden_checkbox_item('otros_servicios', 'Planeamiento cirugía ortognática'); ?>
                <?php orden_checkbox_item('otros_servicios', 'Endoguide'); ?>
              </div>

              <fieldset class="orden-fieldset orden-textogrupo">
                <?php orden_checkbox_item('otros_servicios', 'Protector bucal', 'orden-check-texto'); ?>
                <label>Color <?php orden_texto('protector_bucal_color'); ?></label>
              </fieldset>
            </details>

          </div>
        </div>

        <button type="submit" class="btn-primary">Solicitar cita</button>
      </form>
    </section>
  </main>

  <script>
    // Sucursal-scoped data: name, direccion, allowed estudios ([] = sin
    // restriccion), weekly shifts keyed by ISO weekday, and assigned
    // profesionales. Client filtering is UX only — the server re-validates
    // every constraint independently, exactly as in agendar-cita.php.
    var SUCURSALES = <?= json_encode($datosSucursales, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    var HORA_ELEGIDA = <?= json_encode($valores['hora']) ?>; // re-render after a validation error
  </script>
  <script src="./assets/js/agendado-cascada.js"></script>
  <script src="./assets/js/selector-combo.js"></script>
  <script src="./assets/js/buscador-combo.js"></script>
  <script>
    // Paciente existente vs. nuevo: mismo patron visual/JS que el toggle
    // estudio -> tipo_orden de mas abajo (mostrar/ocultar paneles segun
    // una seleccion), aplicado aqui al modo de eleccion del paciente.
    (function () {
      var radios         = document.querySelectorAll('input[name="modo_paciente"]');
      var panelExistente = document.getElementById('panel-paciente-existente');
      var panelNuevo     = document.getElementById('panel-paciente-nuevo');
      if (!radios.length || !panelExistente || !panelNuevo) {
        return;
      }

      function aplicarModoPaciente() {
        var seleccionado = document.querySelector('input[name="modo_paciente"]:checked');
        var esNuevo = !!seleccionado && seleccionado.value === 'nuevo';
        panelNuevo.hidden     = !esNuevo;
        panelExistente.hidden = esNuevo;
      }

      radios.forEach(function (radio) {
        radio.addEventListener('change', aplicarModoPaciente);
      });
      aplicarModoPaciente();
    })();
  </script>
  <script>
    // Orden clinica: SIEMPRE obligatoria aqui, nunca opcional. Ya no hay
    // 'alineadores' como estudio seleccionable en esta pantalla (esa
    // planilla se maneja aparte, ver alineadores/), asi que el unico tipo
    // de orden posible es 'estudio' — mirror del calculo server-side en
    // tipo_orden_requerido().
    (function () {
      var estudioSelect   = document.getElementById('estudio');
      var tipoOrdenOculto = document.getElementById('tipo_orden');
      var panelEstudio     = document.getElementById('orden-panel-estudio');
      if (!estudioSelect || !tipoOrdenOculto || !panelEstudio) {
        return;
      }

      function aplicarTipoOrden() {
        var hayEstudio        = estudioSelect.value !== '';
        tipoOrdenOculto.value = hayEstudio ? 'estudio' : '';
        panelEstudio.hidden   = !hayEstudio;
      }

      estudioSelect.addEventListener('change', aplicarTipoOrden);
      aplicarTipoOrden();
    })();
  </script>
  <script>
    // Dos comodidades de tildado que la ficha original de
    // clinicaimagen.uy/ordenes/orden.html tambien hace en el cliente:
    //
    //   .orden-grupo      un checkbox "padre" que se auto-tilda cuando se
    //                     marca cualquiera de los checkboxes de adentro
    //                     (pedir "Labios en reposo" implica pedir la
    //                     telerradiografia de perfil).
    //   .orden-textogrupo un checkbox que se auto-tilda cuando su campo de
    //                     texto asociado tiene contenido (escribir la marca
    //                     del implante implica pedir la guia de precision).
    //
    // Puro UX: el servidor no depende de nada de esto — recolectar_campos_orden()
    // guarda exactamente lo que llego tildado, padre incluido o no.
    (function () {
      document.querySelectorAll('.orden-grupo').forEach(function (grupo) {
        var padre = grupo.querySelector('.orden-padre');
        var hijos = Array.prototype.filter.call(
          grupo.querySelectorAll('input[type="checkbox"]'),
          function (input) { return input !== padre; }
        );
        if (!padre || !hijos.length) {
          return;
        }
        hijos.forEach(function (hijo) {
          hijo.addEventListener('change', function () {
            if (hijo.checked) {
              padre.checked = true;
            }
          });
        });
      });

      document.querySelectorAll('.orden-textogrupo').forEach(function (grupo) {
        var check = grupo.querySelector('.orden-check-texto');
        var texto = grupo.querySelector('input[type="text"]');
        if (!check || !texto) {
          return;
        }
        texto.addEventListener('input', function () {
          if (texto.value.trim() !== '') {
            check.checked = true;
          }
        });
      });
    })();
  </script>
</body>

</html>
