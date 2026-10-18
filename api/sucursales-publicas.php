<?php
// Public, read-only: no login, no session; only non-sensitive fields.
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$horarios = [];
foreach (db()->query(
    "SELECT sucursal_id, dia_semana, TIME_FORMAT(hora_apertura, '%H:%i') AS apertura, TIME_FORMAT(hora_cierre, '%H:%i') AS cierre
       FROM sucursal_horarios ORDER BY sucursal_id, dia_semana, hora_apertura"
)->fetchAll() as $t) {
    $horarios[(int) $t['sucursal_id']][] = [(int) $t['dia_semana'], $t['apertura'], $t['cierre']];
}

$filas = db()->query('SELECT id, nombre, direccion, equipamiento, latitud, longitud FROM sucursales WHERE activo = 1 ORDER BY nombre')->fetchAll();
echo json_encode(['sucursales' => array_map(static fn (array $s): array => [
    'id'           => (int) $s['id'],
    'nombre'       => $s['nombre'],
    'direccion'    => $s['direccion'],
    'equipamiento' => $s['equipamiento'],
    'latitud'      => $s['latitud'] === null ? null : (float) $s['latitud'],
    'longitud'     => $s['longitud'] === null ? null : (float) $s['longitud'],
    'horarios'     => $horarios[(int) $s['id']] ?? [],
], $filas)], JSON_UNESCAPED_UNICODE);
