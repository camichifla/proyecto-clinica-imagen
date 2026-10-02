<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/agenda.php';

$usuario = requerir_rol_json(['administrador', 'profesional']);

$vista = ($_GET['vista'] ?? 'mes') === 'semana' ? 'semana' : 'mes';

$fechaRefTexto = (string) ($_GET['fecha'] ?? '');
$fechaRef = DateTime::createFromFormat('Y-m-d', $fechaRefTexto) ?: null;
if (!$fechaRef || $fechaRef->format('Y-m-d') !== $fechaRefTexto) {
    $fechaRef = new DateTime('today');
}
$fechaRef->setTime(0, 0, 0);

$rango = calcular_rango_agenda($vista, $fechaRef);

$sucursales = [];
$sucursalId = null;
$condicion  = '';
$parametros = [];

if ($usuario['rol'] === 'administrador') {

    $sucursales = db()->query('SELECT id, nombre FROM sucursales ORDER BY nombre')->fetchAll();
    $sucursalId = filter_input(INPUT_GET, 'sucursal_id', FILTER_VALIDATE_INT) ?: null;
    if ($sucursalId !== null && !in_array($sucursalId, array_column($sucursales, 'id'), true)) {
        $sucursalId = null;
    }
    if ($sucursalId !== null) {
        $condicion    = 'c.sucursal_id = ? AND ';
        $parametros[] = $sucursalId;
    }
} else {

    $condicion    = 'c.profesional_id = ? AND ';
    $parametros[] = $usuario['ref_id'];
}

$stmt = db()->prepare(
    "SELECT c.*,
            p.nombre  AS paciente_nombre,  p.apellido  AS paciente_apellido,
            pr.nombre AS profesional_nombre, pr.apellido AS profesional_apellido,
            s.nombre  AS sucursal_nombre
     FROM citas c
     LEFT JOIN pacientes p ON p.id = c.paciente_id
     JOIN profesionales pr ON pr.id = c.profesional_id
     LEFT JOIN sucursales s ON s.id = c.sucursal_id
     WHERE {$condicion}DATE(c.fecha_hora_solicitada) BETWEEN ? AND ?
     ORDER BY c.fecha_hora_solicitada ASC"
);
$stmt->execute([...$parametros, $rango['rangoInicio']->format('Y-m-d'), $rango['rangoFin']->format('Y-m-d')]);
$citasPorDia = agrupar_citas_por_dia($stmt->fetchAll());

function cita_agenda_json(array $cita): array
{
    return [
        'id'               => (int) $cita['id'],
        'hora'             => substr($cita['fecha_hora_solicitada'], 11, 5),
        'estado'           => $cita['estado'],
        'estadoLabel'      => ESTADOS_CITA[$cita['estado']] ?? $cita['estado'],
        'estudioLabel'     => ESTUDIOS[$cita['estudio']] ?? $cita['estudio'],
        'pacienteCorto'    => $cita['paciente_id'] === null ? 'Pendiente' : $cita['paciente_apellido'],
        'pacienteLabel'    => cita_nombre_paciente($cita),
        'profesionalLabel' => $cita['profesional_apellido'] . ', ' . $cita['profesional_nombre'],
        'sucursalNombre'   => $cita['sucursal_nombre'],
    ];
}

$hoy  = (new DateTime('today'))->format('Y-m-d');
$dias = [];
$diaIter = clone $rango['rangoInicio'];
while ($diaIter <= $rango['rangoFin']) {
    $clave = $diaIter->format('Y-m-d');
    $dias[] = [
        'fecha'       => $clave,
        'dia'         => (int) $diaIter->format('d'),
        'diaSemanaIso' => (int) $diaIter->format('N'),
        'esMesActual' => $diaIter->format('m') === $fechaRef->format('m'),
        'esHoy'       => $clave === $hoy,
        'citas'       => array_map('cita_agenda_json', $citasPorDia[$clave] ?? []),
    ];
    $diaIter->modify('+1 day');
}

echo json_encode([
    'vista'          => $vista,
    'fecha'          => $fechaRef->format('Y-m-d'),
    'tituloRango'    => $vista === 'mes'
        ? nombre_mes($rango['inicioMes'])
        : $rango['rangoInicio']->format('d/m') . ' - ' . $rango['rangoFin']->format('d/m/Y'),
    'fechaAnterior'  => $rango['fechaAnterior'],
    'fechaSiguiente' => $rango['fechaSiguiente'],
    'hoy'            => $hoy,
    'sucursales'     => array_map(static fn (array $s): array => ['id' => (int) $s['id'], 'nombre' => $s['nombre']], $sucursales),
    'sucursalId'     => $sucursalId,
    'dias'           => $dias,
], JSON_UNESCAPED_UNICODE);
