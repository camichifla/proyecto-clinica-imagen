<?php
// includes/agenda_render.php — shared month/week calendar renderer for
// citas, reused by agenda.php (administrador, every cita, sede-filterable)
// and ver-agenda.php (profesional, own citas only, no filter). Callers own
// their own data query/filters and URL-building (base filename + extra
// query params differ per page); this file only turns a date range +
// citas-grouped-by-day into markup, so the calendar logic exists once.

const NOMBRES_DIA_SEMANA = ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'];
const NOMBRES_DIA_SEMANA_COMPLETO = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];
const NOMBRES_MES = [
    1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];
const MAX_CITAS_VISIBLES_MES = 3;

/**
 * Builds a nav-link query string for an agenda-style page, preserving
 * whatever extra params the caller passes (e.g. agenda.php's sucursal_id)
 * on top of vista/fecha. Shared by agenda.php and ver-agenda.php's own
 * url_agenda() wrappers so the query-building logic exists once.
 */
function url_agenda_generico(string $archivo, string $vista, string $fecha, array $extra = []): string
{
    $params = array_filter(
        array_merge(['vista' => $vista, 'fecha' => $fecha], $extra),
        static fn ($v) => $v !== '' && $v !== null
    );
    return $archivo . '?' . http_build_query($params);
}

require_once __DIR__ . '/estudios.php';

// No system locale is configured for this project (the existing dd/mm/yyyy
// date handling elsewhere is hand-rolled for the same reason), so month
// names come from NOMBRES_MES instead of strftime()/IntlDateFormatter.
function nombre_mes(DateTime $fecha): string
{
    return NOMBRES_MES[(int) $fecha->format('n')] . ' ' . $fecha->format('Y');
}

/**
 * Computes the visible grid range (full ISO weeks for month view, the
 * single ISO week for week view) plus the prev/next reference dates.
 *
 * @return array{rangoInicio: DateTime, rangoFin: DateTime, inicioMes: ?DateTime, fechaAnterior: string, fechaSiguiente: string}
 */
function calcular_rango_agenda(string $vista, DateTime $fechaRef): array
{
    if ($vista === 'mes') {
        $inicioMes = (clone $fechaRef)->modify('first day of this month');
        $finMes    = (clone $fechaRef)->modify('last day of this month');

        $rangoInicio = clone $inicioMes;
        $rangoInicio->modify('-' . ((int) $rangoInicio->format('N') - 1) . ' days');

        $rangoFin = clone $finMes;
        $rangoFin->modify('+' . (7 - (int) $rangoFin->format('N')) . ' days');

        return [
            'rangoInicio'    => $rangoInicio,
            'rangoFin'       => $rangoFin,
            'inicioMes'      => $inicioMes,
            'fechaAnterior'  => (clone $inicioMes)->modify('-1 month')->format('Y-m-d'),
            'fechaSiguiente' => (clone $inicioMes)->modify('+1 month')->format('Y-m-d'),
        ];
    }

    $rangoInicio = clone $fechaRef;
    $rangoInicio->modify('-' . ((int) $rangoInicio->format('N') - 1) . ' days');
    $rangoFin = (clone $rangoInicio)->modify('+6 days');

    return [
        'rangoInicio'    => $rangoInicio,
        'rangoFin'       => $rangoFin,
        'inicioMes'      => null,
        'fechaAnterior'  => (clone $fechaRef)->modify('-7 days')->format('Y-m-d'),
        'fechaSiguiente' => (clone $fechaRef)->modify('+7 days')->format('Y-m-d'),
    ];
}

/**
 * @param array<int,array<string,mixed>> $citas
 * @return array<string,array<int,array<string,mixed>>> keyed by 'Y-m-d'
 */
function agrupar_citas_por_dia(array $citas): array
{
    $citasPorDia = [];
    foreach ($citas as $cita) {
        $citasPorDia[substr($cita['fecha_hora_solicitada'], 0, 10)][] = $cita;
    }
    return $citasPorDia;
}

/**
 * Display label for the cita's patient. A cita booked by a medico for an
 * email with no matching cuentas row lands here with paciente_id NULL and
 * email_pendiente set (see agendar-cita-medico.php) — never concatenate
 * the null paciente_nombre/paciente_apellido in that case (PHP 8 deprecation
 * notice, and there's nothing to show anyway).
 */
function cita_nombre_paciente(array $cita): string
{
    if ($cita['paciente_id'] === null) {
        $nombre   = trim((string) ($cita['nombre_pendiente'] ?? ''));
        $apellido = trim((string) ($cita['apellido_pendiente'] ?? ''));
        $email    = (string) ($cita['email_pendiente'] ?? '');
        if ($nombre !== '' || $apellido !== '') {
            return 'Pendiente: ' . trim($nombre . ' ' . $apellido) . ' (' . $email . ')';
        }
        return 'Pendiente: ' . $email;
    }
    return $cita['paciente_apellido'] . ', ' . $cita['paciente_nombre'];
}

/**
 * Renders the Mes/Semana toggle, prev/next/Hoy nav, and the grid itself.
 * $urlBuilder(string $vista, string $fecha): string lets each caller own
 * its own base filename and any extra filter params (e.g. agenda.php's
 * sucursal_id) without this file needing to know about them.
 *
 * @param array{rangoInicio: DateTime, rangoFin: DateTime, inicioMes: ?DateTime, fechaAnterior: string, fechaSiguiente: string} $rango
 * @param array<string,array<int,array<string,mixed>>> $citasPorDia
 */
function render_calendario_agenda(string $vista, DateTime $fechaRef, array $rango, array $citasPorDia, callable $urlBuilder): void
{
    $hoy = (new DateTime('today'))->format('Y-m-d');
    ?>
    <div class="agenda-controles">
      <div class="agenda-toggle" role="group" aria-label="Vista de agenda">
        <a href="<?= htmlspecialchars($urlBuilder('mes', $fechaRef->format('Y-m-d'))) ?>"
           class="agenda-toggle-opcion <?= $vista === 'mes' ? 'agenda-toggle-activa' : '' ?>">Mes</a>
        <a href="<?= htmlspecialchars($urlBuilder('semana', $fechaRef->format('Y-m-d'))) ?>"
           class="agenda-toggle-opcion <?= $vista === 'semana' ? 'agenda-toggle-activa' : '' ?>">Semana</a>
      </div>

      <div class="agenda-nav">
        <a href="<?= htmlspecialchars($urlBuilder($vista, $rango['fechaAnterior'])) ?>" class="calendario-nav" aria-label="Anterior">‹</a>
        <span class="agenda-nav-titulo">
          <?= $vista === 'mes'
              ? htmlspecialchars(nombre_mes($rango['inicioMes']))
              : htmlspecialchars($rango['rangoInicio']->format('d/m') . ' - ' . $rango['rangoFin']->format('d/m/Y')) ?>
        </span>
        <a href="<?= htmlspecialchars($urlBuilder($vista, $rango['fechaSiguiente'])) ?>" class="calendario-nav" aria-label="Siguiente">›</a>
        <a href="<?= htmlspecialchars($urlBuilder($vista, $hoy)) ?>" class="btn-secundario agenda-hoy">Hoy</a>
      </div>
    </div>

    <?php if ($vista === 'mes'): ?>
      <div class="agenda-mes-dias-semana">
        <?php foreach (NOMBRES_DIA_SEMANA as $nombreDia): ?>
          <span><?= $nombreDia ?></span>
        <?php endforeach; ?>
      </div>
      <div class="agenda-mes-grid">
        <?php
        $diaIter = clone $rango['rangoInicio'];
        while ($diaIter <= $rango['rangoFin']):
            $claveDia = $diaIter->format('Y-m-d');
            $citasDia = $citasPorDia[$claveDia] ?? [];
            $esMesActual = $diaIter->format('m') === $fechaRef->format('m');
            $esHoy = $claveDia === $hoy;
        ?>
          <div class="agenda-dia-mes <?= !$esMesActual ? 'agenda-dia-fuera-mes' : '' ?> <?= $esHoy ? 'agenda-dia-hoy' : '' ?>">
            <a class="agenda-dia-numero" href="<?= htmlspecialchars($urlBuilder('semana', $claveDia)) ?>"><?= (int) $diaIter->format('d') ?></a>
            <?php foreach (array_slice($citasDia, 0, MAX_CITAS_VISIBLES_MES) as $cita): ?>
              <span class="agenda-cita-pill estado-<?= htmlspecialchars($cita['estado']) ?>">
                <?= htmlspecialchars(substr($cita['fecha_hora_solicitada'], 11, 5)) ?>
                <?= htmlspecialchars($cita['paciente_id'] === null ? 'Pendiente' : $cita['paciente_apellido']) ?>
              </span>
            <?php endforeach; ?>
            <?php if (count($citasDia) > MAX_CITAS_VISIBLES_MES): ?>
              <a class="agenda-cita-mas" href="<?= htmlspecialchars($urlBuilder('semana', $claveDia)) ?>">
                +<?= count($citasDia) - MAX_CITAS_VISIBLES_MES ?> mas
              </a>
            <?php endif; ?>
          </div>
        <?php
            $diaIter->modify('+1 day');
        endwhile;
        ?>
      </div>
    <?php else: ?>
      <div class="agenda-semana-grid">
        <?php
        $diaIter = clone $rango['rangoInicio'];
        while ($diaIter <= $rango['rangoFin']):
            $claveDia = $diaIter->format('Y-m-d');
            $citasDia = $citasPorDia[$claveDia] ?? [];
            $esHoy = $claveDia === $hoy;
        ?>
          <div class="agenda-dia-semana <?= $esHoy ? 'agenda-dia-hoy' : '' ?>">
            <div class="agenda-dia-semana-header">
              <?= NOMBRES_DIA_SEMANA_COMPLETO[(int) $diaIter->format('N') - 1] ?>
              <strong><?= (int) $diaIter->format('d') ?></strong>
            </div>
            <?php if (!$citasDia): ?>
              <p class="agenda-dia-semana-vacio">Sin citas</p>
            <?php else: ?>
              <?php foreach ($citasDia as $cita): ?>
                <button type="button" class="agenda-cita-card estado-<?= htmlspecialchars($cita['estado']) ?>" aria-expanded="false">
                  <span class="agenda-cita-resumen">
                    <strong><?= htmlspecialchars(substr($cita['fecha_hora_solicitada'], 11, 5)) ?></strong>
                    <span><?= htmlspecialchars(ESTUDIOS[$cita['estudio']] ?? $cita['estudio']) ?></span>
                  </span>
                  <span class="agenda-cita-detalle">
                    <span><?= htmlspecialchars(cita_nombre_paciente($cita)) ?></span>
                    <span><?= htmlspecialchars($cita['profesional_apellido'] . ', ' . $cita['profesional_nombre']) ?></span>
                    <span><?= $cita['sucursal_nombre'] ? htmlspecialchars($cita['sucursal_nombre']) : '-' ?></span>
                    <span class="estado-badge estado-<?= htmlspecialchars($cita['estado']) ?>">
                      <?= htmlspecialchars(ESTADOS_CITA[$cita['estado']] ?? $cita['estado']) ?>
                    </span>
                  </span>
                </button>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php
            $diaIter->modify('+1 day');
        endwhile;
        ?>
      </div>
    <?php endif; ?>
    <?php
}
