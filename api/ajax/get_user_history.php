<?php
// Devuelve el historial de un paciente (observaciones y evoluciones)
// Se espera recibir el parámetro GET `ci` con la cédula del paciente.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
requireRole('admin');

$ci = isset($_GET['ci']) ? (int)$_GET['ci'] : 0;
if ($ci <= 0) {
    // Si no se envía un CI válido, avisamos al cliente
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CI de paciente requerido']);
    exit;
}

// Preparamos la consulta para evitar inyecciones y traer las entradas
$stmt = $conn->prepare('SELECT id, paciente_ci, tipo, nota, tecnico, creado_en FROM historial WHERE paciente_ci = ? ORDER BY creado_en DESC');
$stmt->execute([$ci]);
$rows = $stmt->fetchAll();

// Devolvemos el historial como JSON
echo json_encode(['success' => true, 'data' => $rows]);
