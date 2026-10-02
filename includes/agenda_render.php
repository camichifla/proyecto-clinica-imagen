<?php
const NOMBRES_MES = [
    1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

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
