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

/** @return array{rangoInicio: DateTime, rangoFin: DateTime, inicioMes: ?DateTime, fechaAnterior: string, fechaSiguiente: string} */
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

    if ($vista === 'dia') {
        return [
            'rangoInicio'    => clone $fechaRef,
            'rangoFin'       => clone $fechaRef,
            'inicioMes'      => null,
            'fechaAnterior'  => (clone $fechaRef)->modify('-1 day')->format('Y-m-d'),
            'fechaSiguiente' => (clone $fechaRef)->modify('+1 day')->format('Y-m-d'),
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

/** @return array<string,array<int,array<string,mixed>>> keyed by 'Y-m-d' */
function agrupar_citas_por_dia(array $citas): array
{
    $citasPorDia = [];
    foreach ($citas as $cita) {
        $citasPorDia[substr($cita['fecha_hora_solicitada'], 0, 10)][] = $cita;
    }
    return $citasPorDia;
}

/**
 * Patient label. A cita booked by a medico for an email with no cuentas row has paciente_id NULL and
 * email_pendiente set: never concatenate the null name fields.
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

// SELECT shared by agenda, panel and turnos: the cita + what cita_agenda_json() needs
// (patient, professional, branch). Each caller adds its WHERE.
const SQL_CITAS_CON_ETIQUETAS =
    'SELECT c.*,
            p.nombre  AS paciente_nombre,  p.apellido  AS paciente_apellido,
            pr.nombre AS profesional_nombre, pr.apellido AS profesional_apellido,
            s.nombre  AS sucursal_nombre
       FROM citas c
       LEFT JOIN pacientes p ON p.id = c.paciente_id
       JOIN profesionales pr ON pr.id = c.profesional_id
       LEFT JOIN sucursales s ON s.id = c.sucursal_id';

function cita_agenda_json(array $cita): array
{
    return [
        'id'               => (int) $cita['id'],
        'hora'             => substr($cita['fecha_hora_solicitada'], 11, 5),
        'estado'           => $cita['estado'],
        'estadoLabel'      => ESTADOS_CITA[$cita['estado']] ?? $cita['estado'],
        'estudioLabel'     => estudios_etiquetas()[$cita['estudio']] ?? $cita['estudio'],
        'pacienteCorto'    => $cita['paciente_id'] === null ? 'Pendiente' : $cita['paciente_apellido'],
        'pacienteLabel'    => cita_nombre_paciente($cita),
        'profesionalLabel' => $cita['profesional_apellido'] . ', ' . $cita['profesional_nombre'],
        'sucursalNombre'   => $cita['sucursal_nombre'],
    ];
}
