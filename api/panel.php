<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/agenda.php';

requerir_rol_json(['administrador', 'recepcionista']);

$hoy    = new DateTimeImmutable('today');
$manana = $hoy->modify('+1 day')->format('Y-m-d');
$en8    = $hoy->modify('+8 days')->format('Y-m-d');
$hoyTxt = $hoy->format('Y-m-d');

$pendientes = (int) db()->query("SELECT COUNT(*) FROM citas WHERE estado = 'pendiente'")->fetchColumn();

$stmt = db()->prepare('SELECT estado, COUNT(*) FROM citas WHERE fecha_hora_solicitada >= ? AND fecha_hora_solicitada < ? GROUP BY estado');
$stmt->execute([$hoyTxt, $manana]);
$hoyPorEstado = array_fill_keys(array_keys(ESTADOS_CITA), 0);
foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$estado, $total]) {
    $hoyPorEstado[$estado] = (int) $total;
}

$stmt = db()->prepare("SELECT COUNT(*) FROM citas WHERE estado = 'confirmada' AND fecha_hora_solicitada >= ? AND fecha_hora_solicitada < ?");
$stmt->execute([$hoyTxt, $en8]);
$proximos7 = (int) $stmt->fetchColumn();

$stmt = db()->prepare(SQL_CITAS_CON_ETIQUETAS . ' WHERE c.fecha_hora_solicitada >= ? AND c.fecha_hora_solicitada < ? ORDER BY c.fecha_hora_solicitada ASC');
$stmt->execute([$hoyTxt, $manana]);

echo json_encode([
    'hoy'                  => $hoyTxt,
    'pendientes'           => $pendientes,
    'hoyPorEstado'         => $hoyPorEstado,
    'proximos7Confirmadas' => $proximos7,
    'citasHoy'             => array_map(static fn (array $c): array => ['codigo' => codigo_cita((int) $c['id'])] + cita_agenda_json($c), $stmt->fetchAll()),
], JSON_UNESCAPED_UNICODE);
