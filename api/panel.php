<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/recepcion.php';
require_once __DIR__ . '/../includes/agenda.php';

$usuario = requerir_rol_json(['administrador', 'recepcionista']);
$sucRecep = filtro_sucursal_recepcion($usuario);
$condSuc  = $sucRecep !== null ? ' AND sucursal_id = ?' : '';
$condSucC = $sucRecep !== null ? ' AND c.sucursal_id = ?' : '';
$pSuc     = $sucRecep !== null ? [$sucRecep] : [];

$hoy    = new DateTimeImmutable('today');
$manana = $hoy->modify('+1 day')->format('Y-m-d');
$en8    = $hoy->modify('+8 days')->format('Y-m-d');
$hoyTxt = $hoy->format('Y-m-d');

$stmt = db()->prepare("SELECT COUNT(*) FROM citas WHERE estado = 'pendiente'{$condSuc}");
$stmt->execute($pSuc);
$pendientes = (int) $stmt->fetchColumn();

$stmt = db()->prepare("SELECT estado, COUNT(*) FROM citas WHERE fecha_hora_solicitada >= ? AND fecha_hora_solicitada < ?{$condSuc} GROUP BY estado");
$stmt->execute([$hoyTxt, $manana, ...$pSuc]);
$hoyPorEstado = array_fill_keys(array_keys(ESTADOS_CITA), 0);
foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$estado, $total]) {
    $hoyPorEstado[$estado] = (int) $total;
}

$stmt = db()->prepare("SELECT COUNT(*) FROM citas WHERE estado = 'confirmada' AND fecha_hora_solicitada >= ? AND fecha_hora_solicitada < ?{$condSuc}");
$stmt->execute([$hoyTxt, $en8, ...$pSuc]);
$proximos7 = (int) $stmt->fetchColumn();

$stmt = db()->prepare(SQL_CITAS_CON_ETIQUETAS . " WHERE c.fecha_hora_solicitada >= ? AND c.fecha_hora_solicitada < ?{$condSucC} ORDER BY c.fecha_hora_solicitada ASC");
$stmt->execute([$hoyTxt, $manana, ...$pSuc]);

// Read-only "Mi sede" card for a receptionist with a branch.
$miSede = null;
if ($sucRecep) {
    $stmt = db()->prepare('SELECT nombre, direccion FROM sucursales WHERE id = ?');
    $stmt->execute([$sucRecep]);
    $miSede = $stmt->fetch() ?: null;
}
if ($miSede) {
    $stmt = db()->prepare(
        "SELECT dia_semana, TIME_FORMAT(hora_apertura, '%H:%i') AS apertura, TIME_FORMAT(hora_cierre, '%H:%i') AS cierre
           FROM sucursal_horarios WHERE sucursal_id = ? ORDER BY dia_semana, hora_apertura"
    );
    $stmt->execute([$sucRecep]);
    $miSede['horarios'] = array_map(static fn (array $t): array => ['dia' => (int) $t['dia_semana'], 'apertura' => $t['apertura'], 'cierre' => $t['cierre']], $stmt->fetchAll());

    // No sucursal_estudio rows = unrestricted, so every active study is offered.
    $stmt = db()->prepare('SELECT estudio FROM sucursal_estudio WHERE sucursal_id = ?');
    $stmt->execute([$sucRecep]);
    $claves = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $activos = estudios_activos();
    $miSede['estudios'] = array_values($claves ? array_intersect_key($activos, array_flip($claves)) : $activos);

    $stmt = db()->prepare(
        'SELECT pr.nombre, pr.apellido FROM profesional_sucursal ps JOIN profesionales pr ON pr.id = ps.profesional_id
          WHERE ps.sucursal_id = ? ORDER BY pr.apellido, pr.nombre'
    );
    $stmt->execute([$sucRecep]);
    $miSede['profesionales'] = array_map(static fn (array $p): string => $p['apellido'] . ', ' . $p['nombre'], $stmt->fetchAll());
}

echo json_encode([
    'miSede'               => $miSede,
    'hoy'                  => $hoyTxt,
    'pendientes'           => $pendientes,
    'hoyPorEstado'         => $hoyPorEstado,
    'proximos7Confirmadas' => $proximos7,
    'citasHoy'             => array_map(static fn (array $c): array => ['codigo' => codigo_cita((int) $c['id'])] + cita_agenda_json($c), $stmt->fetchAll()),
], JSON_UNESCAPED_UNICODE);
