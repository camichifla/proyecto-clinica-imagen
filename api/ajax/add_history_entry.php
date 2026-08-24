<?php
// Inserta una nueva entrada en el historial del paciente.
// Espera POST con: paciente_ci, tipo (observacion|evolucion), nota, tecnico (opcional).
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
// Solo el personal con rol admin puede crear entradas de historial desde este endpoint.
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Si no es POST, devolvemos un error.
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$ci = isset($_POST['paciente_ci']) ? (int)$_POST['paciente_ci'] : 0;
$tipo = trim($_POST['tipo'] ?? 'observacion');
$nota = trim($_POST['nota'] ?? '');
$tecnico = trim($_POST['tecnico'] ?? ($_SESSION['name'] ?? 'Técnico'));

if ($ci <= 0 || $nota === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Campos requeridos faltantes']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO historial (paciente_ci, tipo, nota, tecnico) VALUES (?, ?, ?, ?)');
$stmt->execute([$ci, $tipo, $nota, $tecnico]);
if ($stmt->rowCount() === 1) {
    echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
} else {
    // Si no pudimos guardar, devolvemos un mensaje sencillo para mostrar al usuario
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar historial']);
}
