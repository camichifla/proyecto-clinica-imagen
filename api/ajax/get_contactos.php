<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
requireRole('admin');

$stmt = $conn->query("SELECT id, nombre, email, telefono, asunto, mensaje, fecha_creacion, estado, ip_address FROM contactos ORDER BY fecha_creacion DESC");
$rows = $stmt->fetchAll();

$countNew = 0;
foreach ($rows as $r) if (isset($r['estado']) && $r['estado'] === 'nuevo') $countNew++;

echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows), 'new' => $countNew]);
