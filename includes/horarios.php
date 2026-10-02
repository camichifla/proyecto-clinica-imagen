<?php
// includes/horarios.php — the 45-minute slot rule, server-side authority.
// The JS copy in agendar-cita.php (generarHoras) renders the same list for
// the UI; this one decides what a forged POST is allowed to submit.

const PASO_MINUTOS = 45;

/**
 * @param array $turnos Rows shaped like ['hora_apertura' => '08:30:00', 'hora_cierre' => '12:30:00'], ...]
 * @return string[] Flat, ordered list of 'HH:MM' slot starts, one list per shift concatenated.
 */
function slots_de_turnos(array $turnos): array
{
    $slots = [];
    foreach ($turnos as $turno) {
        $inicio = (int) substr($turno['hora_apertura'], 0, 2) * 60 + (int) substr($turno['hora_apertura'], 3, 2);
        $fin    = (int) substr($turno['hora_cierre'],   0, 2) * 60 + (int) substr($turno['hora_cierre'],   3, 2);
        for ($m = $inicio; $m <= $fin; $m += PASO_MINUTOS) {
            $slots[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }
    }
    return $slots;
}

const RANGO_MESES = 3;

/**
 * @return array{0:string,1:string} [fechaMin, fechaMax] 'Y-m-d' for booking, today .. +RANGO_MESES months.
 */
function rango_reserva(): array
{
    $hoy = new DateTimeImmutable('today');
    return [$hoy->format('Y-m-d'), $hoy->modify('+' . RANGO_MESES . ' months')->format('Y-m-d')];
}

/**
 * Booking catalog shared by the paciente and medico booking pages:
 * sucursales (ordered list + detail map) and profesionales with their
 * especializaciones. Requires db.php.
 *
 * @return array{sucursalesOrden:array,sucursales:array,profesionales:array}
 */
function catalogo_reserva(): array
{
    $profesionalesFilas = db()->query('SELECT id, nombre, apellido FROM profesionales ORDER BY apellido, nombre')->fetchAll();

    $especializacionesPorProfesional = [];
    foreach (db()->query('SELECT profesional_id, especializacion FROM profesional_especializacion')->fetchAll() as $fila) {
        $especializacionesPorProfesional[(int) $fila['profesional_id']][] = $fila['especializacion'];
    }

    $profesionales = array_map(static function (array $p) use ($especializacionesPorProfesional): array {
        return [
            'id'                => (int) $p['id'],
            'nombre'            => $p['nombre'],
            'apellido'          => $p['apellido'],
            'especializaciones' => $especializacionesPorProfesional[(int) $p['id']] ?? [],
        ];
    }, $profesionalesFilas);

    $sucursalesFilas = db()->query('SELECT id, nombre, direccion FROM sucursales ORDER BY nombre')->fetchAll();
    $horariosFilas   = db()->query(
        "SELECT sucursal_id, dia_semana,
                TIME_FORMAT(hora_apertura, '%H:%i') AS apertura,
                TIME_FORMAT(hora_cierre,   '%H:%i') AS cierre
           FROM sucursal_horarios
          ORDER BY sucursal_id, dia_semana, hora_apertura"
    )->fetchAll();
    $estudioFilas             = db()->query('SELECT sucursal_id, estudio FROM sucursal_estudio')->fetchAll();
    $profesionalSucursalFilas = db()->query('SELECT sucursal_id, profesional_id FROM profesional_sucursal')->fetchAll();

    $sucursales = [];
    foreach ($sucursalesFilas as $sucursal) {
        $sucursales[(string) $sucursal['id']] = [
            'nombre'        => $sucursal['nombre'],
            'direccion'     => $sucursal['direccion'],
            'estudios'      => [],
            'horarios'      => [],
            'profesionales' => [],
        ];
    }
    foreach ($horariosFilas as $turno) {
        $sucursales[(string) $turno['sucursal_id']]['horarios'][(string) $turno['dia_semana']][] = [$turno['apertura'], $turno['cierre']];
    }
    foreach ($estudioFilas as $restriccion) {
        $sucursales[(string) $restriccion['sucursal_id']]['estudios'][] = $restriccion['estudio'];
    }
    foreach ($profesionalSucursalFilas as $asignacion) {
        $sucursales[(string) $asignacion['sucursal_id']]['profesionales'][] = (int) $asignacion['profesional_id'];
    }

    return [
        'sucursalesOrden' => array_map(static fn (array $s): array => ['id' => (int) $s['id'], 'nombre' => $s['nombre'], 'direccion' => $s['direccion']], $sucursalesFilas),
        'sucursales'      => $sucursales,
        'profesionales'   => $profesionales,
    ];
}
