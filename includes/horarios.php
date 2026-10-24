<?php
// includes/horarios.php — the 45-minute slot rule, server-side authority.
// The JS copy in agendar-cita.php (generarHoras) renders the same list for
// the UI; this one decides what a forged POST is allowed to submit.

require_once __DIR__ . '/estudios.php';

const PASO_MINUTOS = 45;

// Condicion SQL: el profesional de $columna no tiene la cuenta desactivada (toggle_activo solo toca cuentas.activo).
function sql_profesional_habilitado(string $columna): string
{
    return "NOT EXISTS (SELECT 1 FROM cuentas cu WHERE cu.rol = 'profesional' AND cu.ref_id = {$columna} AND cu.activo = 0)";
}

/** @param array $turnos Rows like ['hora_apertura' => '08:30:00', 'hora_cierre' => '12:30:00']. Returns 'HH:MM' slot starts, shifts concatenated. */
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

const MAX_TURNOS_HORARIO = 28;

// Turnos [['dia'=>int,'apertura'=>'HH:MM','cierre'=>'HH:MM'], ...] ordenados, o el mensaje de error.
// Comun a los horarios de sede y a los propios de un profesional. Cada turno genera
// sus horas desde su apertura en pasos de PASO_MINUTOS (slots_de_turnos), asi que
// cualquier HH:MM sirve; lo que no puede pasar es que dos turnos del mismo dia se pisen.
function validar_turnos(mixed $crudo): array|string
{
    $lista = is_string($crudo) ? json_decode($crudo, true) : null;
    if (!is_array($lista) || count($lista) > MAX_TURNOS_HORARIO) {
        return 'Los horarios no son validos.';
    }
    $hora   = '/^([01]\d|2[0-3]):[0-5]\d$/';
    $turnos = [];
    foreach ($lista as $t) {
        $dia      = is_array($t) && isset($t['dia']) ? filter_var($t['dia'], FILTER_VALIDATE_INT) : false;
        $apertura = is_array($t) ? (string) ($t['apertura'] ?? '') : '';
        $cierre   = is_array($t) ? (string) ($t['cierre'] ?? '') : '';
        if ($dia === false || $dia < 1 || $dia > 7) {
            return 'Elegí un día válido en cada horario.';
        }
        if (!preg_match($hora, $apertura) || !preg_match($hora, $cierre)) {
            return 'Las horas deben tener formato HH:MM.';
        }
        if ($apertura >= $cierre) {
            return 'La hora de cierre debe ser posterior a la de apertura.';
        }
        $turnos[] = ['dia' => $dia, 'apertura' => $apertura, 'cierre' => $cierre];
    }
    usort($turnos, static fn (array $a, array $b): int => [$a['dia'], $a['apertura']] <=> [$b['dia'], $b['apertura']]);
    foreach ($turnos as $i => $t) {
        if ($i > 0 && $turnos[$i - 1]['dia'] === $t['dia'] && $t['apertura'] <= $turnos[$i - 1]['cierre']) {
            return 'Dos horarios del mismo día no pueden superponerse ni tocarse.';
        }
    }
    return $turnos;
}

/**
 * Horario propio por profesional para el dia ISO $dia: id => lista de [apertura, cierre] 'HH:MM' (vacia = no trabaja ese dia).
 * Solo aparecen los que TIENEN horario propio; un profesional ausente no tiene restriccion. Requiere db.php.
 *
 * @param int[] $ids
 * @return array<int,array<int,array{0:string,1:string}>>
 */
function horarios_propios(array $ids, int $dia): array
{
    if (!$ids) {
        return [];
    }
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $stmt   = db()->prepare(
        "SELECT profesional_id, dia_semana, SUBSTR(hora_apertura, 1, 5) AS apertura, SUBSTR(hora_cierre, 1, 5) AS cierre
           FROM profesional_horarios WHERE profesional_id IN ({$marcas})"
    );
    $stmt->execute($ids);
    $propios = [];
    foreach ($stmt->fetchAll() as $f) {
        $propios[(int) $f['profesional_id']] ??= [];
        if ((int) $f['dia_semana'] === $dia) {
            $propios[(int) $f['profesional_id']][] = [$f['apertura'], $f['cierre']];
        }
    }
    return $propios;
}

// $hora 'HH:MM' cae dentro de algun turno (mismo criterio inclusivo que slots_de_turnos).
function hora_en_turnos(array $turnos, string $hora): bool
{
    foreach ($turnos as [$apertura, $cierre]) {
        if ($hora >= $apertura && $hora <= $cierre) {
            return true;
        }
    }
    return false;
}

const RANGO_MESES = 3;

function rango_reserva(): array
{
    $hoy = new DateTimeImmutable('today');
    return [$hoy->format('Y-m-d'), $hoy->modify('+' . RANGO_MESES . ' months')->format('Y-m-d')];
}

/**
 * Horas 'HH:MM' de $fecha no reservables. Con profesional: las que ya tiene ocupadas mas las de la sede fuera de su horario
 * propio. Sin profesional: las de la sede en que NINGUN profesional elegible (sede + estudio) esta libre.
 * $ignorarCitaId: una cita cuyo propio horario no cuenta como ocupado (reprogramar). Requiere db.php.
 *
 * @return string[]
 */
function horas_ocupadas(int $sucursalId, string $fecha, ?int $profesionalId, string $estudio, int $ignorarCitaId = 0): array
{
    $desde = $fecha . ' 00:00:00';
    $hasta = (new DateTimeImmutable($fecha))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    $dia   = (int) (new DateTimeImmutable($fecha))->format('N');

    $stmt = db()->prepare('SELECT hora_apertura, hora_cierre FROM sucursal_horarios WHERE sucursal_id = ? AND dia_semana = ?');
    $stmt->execute([$sucursalId, $dia]);
    $slotsSede = slots_de_turnos($stmt->fetchAll());

    if ($profesionalId !== null) {
        $stmt = db()->prepare(
            "SELECT DISTINCT TIME_FORMAT(fecha_hora_solicitada, '%H:%i')
               FROM citas
              WHERE profesional_id = ? AND slot_activo = 1 AND id <> ?
                AND fecha_hora_solicitada >= ? AND fecha_hora_solicitada < ?"
        );
        $stmt->execute([$profesionalId, $ignorarCitaId, $desde, $hasta]);
        $ocupadas = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $propios = horarios_propios([$profesionalId], $dia);
        if (isset($propios[$profesionalId])) {
            foreach ($slotsSede as $hora) {
                if (!hora_en_turnos($propios[$profesionalId], $hora)) {
                    $ocupadas[] = $hora;
                }
            }
        }
        return array_values(array_unique($ocupadas));
    }

    $stmt = db()->prepare(
        'SELECT ps.profesional_id
           FROM profesional_sucursal ps
           JOIN profesional_especializacion pe ON pe.profesional_id = ps.profesional_id AND pe.especializacion = ?
          WHERE ps.sucursal_id = ? AND ' . sql_profesional_habilitado('ps.profesional_id')
    );
    $stmt->execute([$estudio, $sucursalId]);
    $elegibles = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (!$elegibles) {
        return [];
    }

    $marcas = implode(',', array_fill(0, count($elegibles), '?'));
    $stmt   = db()->prepare(
        "SELECT TIME_FORMAT(fecha_hora_solicitada, '%H:%i') AS hora, profesional_id
           FROM citas
          WHERE slot_activo = 1 AND id <> ? AND profesional_id IN ({$marcas})
            AND fecha_hora_solicitada >= ? AND fecha_hora_solicitada < ?"
    );
    $stmt->execute([$ignorarCitaId, ...$elegibles, $desde, $hasta]);
    $ocupadoPor = [];
    foreach ($stmt->fetchAll() as $f) {
        $ocupadoPor[$f['hora']][(int) $f['profesional_id']] = true;
    }

    $propios = horarios_propios($elegibles, $dia);
    $libreEn = static function (string $hora) use ($elegibles, $ocupadoPor, $propios): bool {
        foreach ($elegibles as $id) {
            if (!isset($ocupadoPor[$hora][$id]) && (!isset($propios[$id]) || hora_en_turnos($propios[$id], $hora))) {
                return true;
            }
        }
        return false;
    };
    // Horas con cita aunque no esten en la grilla de la sede (datos historicos), mas la grilla.
    $ocupadas = [];
    foreach (array_unique([...array_keys($ocupadoPor), ...$slotsSede]) as $hora) {
        if (!$libreEn((string) $hora)) {
            $ocupadas[] = (string) $hora;
        }
    }
    return $ocupadas;
}

/**
 * Booking catalog shared by the paciente and medico pages. $estudiosOfrecidos: clave => etiqueta. Requires db.php.
 *
 * @param array<string,string> $estudiosOfrecidos
 * @return array{estudios:array,etiquetasEstudio:array,sucursalesOrden:array,sucursales:array,profesionales:array}
 */
function catalogo_reserva(array $estudiosOfrecidos): array
{
    $profesionalesFilas = db()->query('SELECT id, nombre, apellido FROM profesionales p WHERE ' . sql_profesional_habilitado('p.id') . ' ORDER BY apellido, nombre')->fetchAll();

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

    $sucursalesFilas = db()->query('SELECT id, nombre, direccion FROM sucursales WHERE activo = 1 ORDER BY nombre')->fetchAll();
    $horariosFilas   = db()->query(
        "SELECT sucursal_id, dia_semana,
                TIME_FORMAT(hora_apertura, '%H:%i') AS apertura,
                TIME_FORMAT(hora_cierre,   '%H:%i') AS cierre
           FROM sucursal_horarios
          ORDER BY sucursal_id, dia_semana, hora_apertura"
    )->fetchAll();
    $estudioFilas             = db()->query('SELECT sucursal_id, estudio FROM sucursal_estudio')->fetchAll();
    $profesionalSucursalFilas = db()->query('SELECT sucursal_id, profesional_id FROM profesional_sucursal ps WHERE ' . sql_profesional_habilitado('ps.profesional_id'))->fetchAll();

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
        if (!isset($sucursales[(string) $turno['sucursal_id']])) {
            continue; // sede inactiva
        }
        $sucursales[(string) $turno['sucursal_id']]['horarios'][(string) $turno['dia_semana']][] = [$turno['apertura'], $turno['cierre']];
    }
    foreach ($estudioFilas as $restriccion) {
        if (!isset($sucursales[(string) $restriccion['sucursal_id']])) {
            continue;
        }
        $sucursales[(string) $restriccion['sucursal_id']]['estudios'][] = $restriccion['estudio'];
    }
    foreach ($profesionalSucursalFilas as $asignacion) {
        if (!isset($sucursales[(string) $asignacion['sucursal_id']])) {
            continue;
        }
        $sucursales[(string) $asignacion['sucursal_id']]['profesionales'][] = (int) $asignacion['profesional_id'];
    }

    return [
        'estudios'        => array_map(static fn (string $clave, string $etiqueta): array => ['clave' => $clave, 'etiqueta' => $etiqueta], array_keys($estudiosOfrecidos), $estudiosOfrecidos),
        'etiquetasEstudio' => estudios_etiquetas(),
        'sucursalesOrden' => array_map(static fn (array $s): array => ['id' => (int) $s['id'], 'nombre' => $s['nombre'], 'direccion' => $s['direccion']], $sucursalesFilas),
        'sucursales'      => $sucursales,
        'profesionales'   => $profesionales,
    ];
}
