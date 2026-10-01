<?php
// includes/resultados_render.php — shared query + markup for a single
// patient's resultados list with images, reused by every page that can view
// results: the patient's own ver-resultados.php, and the staff pages
// resultados.php (medico), buscar-resultados.php (administrador), and the
// resultados section of historial-pacientes.php (profesional). Keeps the
// classes (tarjeta-resultado*, already styled globally via ver-resultados.css)
// and the N+1-avoided query pattern in one place instead of duplicated four
// times.

/**
 * Fetches a patient's resultados plus their images, batched into two
 * queries regardless of row count.
 *
 * @return array{0: array<int,array<string,mixed>>, 1: array<int,array<int,array<string,mixed>>>}
 */
function obtener_resultados_de_paciente(PDO $db, int $pacienteId): array
{
    $stmt = $db->prepare('SELECT * FROM resultados WHERE paciente_id = ? ORDER BY fecha_estudio DESC');
    $stmt->execute([$pacienteId]);
    $resultados = $stmt->fetchAll();

    $imagenesPorResultado = [];
    if ($resultados) {
        $ids = array_column($resultados, 'id');
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmtImg = $db->prepare(
            "SELECT id, resultado_id, nombre_original FROM resultado_imagenes WHERE resultado_id IN ({$marcadores}) ORDER BY creado_en ASC"
        );
        $stmtImg->execute($ids);
        foreach ($stmtImg->fetchAll() as $imagen) {
            $imagenesPorResultado[(int) $imagen['resultado_id']][] = $imagen;
        }
    }

    return [$resultados, $imagenesPorResultado];
}

/**
 * @param array<int,array<string,mixed>> $resultados
 * @param array<int,array<int,array<string,mixed>>> $imagenesPorResultado keyed by resultado id
 */
function render_lista_resultados(array $resultados, array $imagenesPorResultado, string $mensajeVacio = 'No hay resultados cargados.'): void
{
    if (!$resultados) {
        echo '<p>' . htmlspecialchars($mensajeVacio) . '</p>';
        return;
    }
    ?>
    <div class="lista-resultados">
      <?php foreach ($resultados as $resultado): ?>
        <article class="tarjeta-resultado">
          <div class="tarjeta-resultado-header">
            <h2><?= htmlspecialchars($resultado['nombre_estudio']) ?></h2>
            <span class="tarjeta-resultado-fecha"><?= htmlspecialchars($resultado['fecha_estudio']) ?></span>
          </div>

          <?php if ($resultado['observaciones']): ?>
            <p class="tarjeta-resultado-observaciones"><?= nl2br(htmlspecialchars($resultado['observaciones'])) ?></p>
          <?php endif; ?>

          <?php $imagenes = $imagenesPorResultado[(int) $resultado['id']] ?? []; ?>
          <?php if ($imagenes): ?>
            <div class="tarjeta-resultado-imagenes">
              <?php foreach ($imagenes as $imagen): ?>
                <img src="ver-resultado-imagen.php?id=<?= (int) $imagen['id'] ?>"
                     alt="<?= htmlspecialchars($imagen['nombre_original']) ?>"
                     loading="lazy">
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>

    <dialog id="visor-imagen" class="visor-imagen">
      <button type="button" class="visor-imagen-cerrar" aria-label="Cerrar">&times;</button>
      <img src="" alt="">
    </dialog>
    <script src="assets/js/visor-imagenes.js"></script>
    <?php
}

/**
 * Form de busqueda de paciente (buscador + boton "Buscar"), reusado por
 * resultados.php (medico), buscar-resultados.php (administrador) e
 * historial-pacientes.php (profesional) — mismo markup byte-identico en
 * los 3, salvo el action del form y el mensaje de "sin pacientes".
 *
 * @param array<int,array<string,mixed>> $pacientes
 * @param array<string,mixed>|null $paciente paciente actualmente elegido/encontrado, si hay
 */
function render_form_buscar_paciente(array $pacientes, ?array $paciente, string $formAction, string $mensajeSinPacientes = 'Todavia no hay pacientes registrados.'): void
{
    ?>
    <form method="get" action="<?= htmlspecialchars($formAction) ?>" class="form-buscar-paciente">
      <div class="campo">
        <label for="paciente_id_buscar">Paciente</label>
        <input type="text" id="paciente_id_buscar" data-lista="paciente_id_lista" data-buscador-combo="paciente_id"
               autocomplete="off" placeholder="Buscar por apellido, nombre o CI"
               value="<?= $paciente ? htmlspecialchars($paciente['apellido'] . ', ' . $paciente['nombre'] . ' (CI ' . $paciente['ci'] . ')') : '' ?>">
        <datalist id="paciente_id_lista">
          <?php foreach ($pacientes as $p): ?>
            <option data-id="<?= (int) $p['id'] ?>" value="<?= htmlspecialchars($p['apellido'] . ', ' . $p['nombre'] . ' (CI ' . $p['ci'] . ')') ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <input type="hidden" id="paciente_id" name="paciente_id" value="<?= $paciente ? (int) $paciente['id'] : '' ?>">
        <?php if (!$pacientes): ?><p class="campo-error"><?= htmlspecialchars($mensajeSinPacientes) ?></p><?php endif; ?>
      </div>

      <button type="submit" class="btn-primary" id="btn-buscar" <?= $paciente ? '' : 'disabled' ?>>Buscar</button>
    </form>
    <?php
}

/**
 * Markup de "paciente no encontrado", byte-idéntico en los mismos 3 archivos
 * que render_form_buscar_paciente().
 */
function render_paciente_no_encontrado(): void
{
    ?>
    <section class="seccion-panel">
      <p class="aviso-error">Paciente no encontrado.</p>
    </section>
    <?php
}
