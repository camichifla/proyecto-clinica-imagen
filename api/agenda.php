<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/recepcion.php';
require_once __DIR__ . '/../includes/agenda.php';

$usuario = requerir_rol_json(['administrador', 'recepcionista', 'profesional']);

$vista = ($_GET['vista'] ?? 'mes') === 'semana' ? 'semana' : 'mes';

$fechaRefTexto = (string) ($_GET['fecha'] ?? '');
$fechaRef = DateTime::createFromFormat('Y-m-d', $fechaRefTexto) ?: null;
if (!$fechaRef || $fechaRef->format('Y-m-d') !== $fechaRefTexto) {
    $fechaRef = new DateTime('today');
}
$fechaRef->setTime(0, 0, 0);

$rango = calcular_rango_agenda($vista, $fechaRef);

$sucursales    = [];
$sucursalId    = null;
$profesionales = [];
$profesionalId = null;
$condicion  = '';
$parametros = [];

if (in_array($usuario['rol'], ['administrador', 'recepcionista'], true)) {

    $sucursales = db()->query('SELECT id, nombre FROM sucursales ORDER BY nombre')->fetchAll();
    $sucursalId = filter_input(INPUT_GET, 'sucursal_id', FILTER_VALIDATE_INT) ?: null;
    if ($sucursalId !== null && !in_array($sucursalId, array_column($sucursales, 'id'), true)) {
        $sucursalId = null;
    }
    // A receptionist is locked to their own branch (ignores the filter); 0 = none assigned yet, shows nothing.
    $sucRecep = filtro_sucursal_recepcion($usuario);
    if ($sucRecep !== null) {
        $sucursalId  = $sucRecep;
        $sucursales  = array_values(array_filter($sucursales, static fn (array $s): bool => (int) $s['id'] === $sucRecep));
    }
    if ($sucursalId !== null) {
        $condicion    = 'c.sucursal_id = ? AND ';
        $parametros[] = $sucursalId;
    }

    // Professionals to filter by: a receptionist's list is limited to those working at their branch.
    $sqlProf = 'SELECT p.id, p.nombre, p.apellido FROM profesionales p'
        . ($sucRecep !== null ? ' JOIN profesional_sucursal ps ON ps.profesional_id = p.id AND ps.sucursal_id = ?' : '')
        . ' ORDER BY p.apellido, p.nombre';
    $stmt = db()->prepare($sqlProf);
    $stmt->execute($sucRecep !== null ? [$sucRecep] : []);
    $profesionales = $stmt->fetchAll();
    $profesionalId = filter_input(INPUT_GET, 'profesional_id', FILTER_VALIDATE_INT) ?: null;
    if ($profesionalId !== null && !in_array($profesionalId, array_map('intval', array_column($profesionales, 'id')), true)) {
        $profesionalId = null;
    }
    if ($profesionalId !== null) {
        $condicion    .= 'c.profesional_id = ? AND ';
        $parametros[] = $profesionalId;
    }
} else {

    $condicion    = 'c.profesional_id = ? AND ';
    $parametros[] = $usuario['ref_id'];
}

// Confirmed and pending citas (the badge tells them apart).
$stmt = db()->prepare(
    SQL_CITAS_CON_ETIQUETAS . "
     WHERE c.estado IN ('confirmada', 'pendiente') AND {$condicion}DATE(c.fecha_hora_solicitada) BETWEEN ? AND ?
     ORDER BY c.fecha_hora_solicitada ASC"
);
$stmt->execute([...$parametros, $rango['rangoInicio']->format('Y-m-d'), $rango['rangoFin']->format('Y-m-d')]);
$citasPorDia = agrupar_citas_por_dia($stmt->fetchAll());

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
    'profesionales'  => array_map(static fn (array $p): array => ['id' => (int) $p['id'], 'nombre' => $p['apellido'] . ', ' . $p['nombre']], $profesionales),
    'profesionalId'  => $profesionalId,
    'dias'           => $dias,
], JSON_UNESCAPED_UNICODE);
