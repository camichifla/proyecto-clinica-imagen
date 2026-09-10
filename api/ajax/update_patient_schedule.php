<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

if (!isLoggedIn() || getUserType() !== 'patient') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$citaId = isset($input['cita_id']) ? (int) $input['cita_id'] : 0;
$sucursal = trim((string)($input['sucursal'] ?? ''));
$fechaHora = trim((string)($input['fecha_hora'] ?? ''));
$date = DateTime::createFromFormat('Y-m-d\\TH:i', $fechaHora);
$minutesFromOpening = $date ? ((int)$date->format('H') * 60 + (int)$date->format('i')) - (8 * 60) : -1;

if ($citaId <= 0 || $sucursal === '' || !$date || $minutesFromOpening < 0 || $minutesFromOpening % 45 !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Selecciona una sucursal y un turno válido de 45 minutos.']);
    exit;
}

if ($date <= new DateTime('now')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El turno debe ser posterior al momento actual.']);
    exit;
}

try {
    $conn->beginTransaction();
    $patientCi = getUserId();
    $lock = $conn->prepare("SELECT id FROM agenda WHERE id = ? AND paciente_ci = ? AND estado = 'confirmada' FOR UPDATE");
    $lock->execute([$citaId, $patientCi]);
    if (!$lock->fetch()) {
        $conn->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'La solicitud no está confirmada o no pertenece al paciente.']);
        exit;
    }

    $occupied = $conn->prepare("SELECT COUNT(*) FROM agenda WHERE sucursal = ? AND fecha_hora = ? AND estado <> 'cancelada' AND id <> ?");
    $occupied->execute([$sucursal, $date->format('Y-m-d H:i:s'), $citaId]);
    if ((int)$occupied->fetchColumn() > 0) {
        $conn->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Ese horario ya está ocupado.']);
        exit;
    }

    $update = $conn->prepare('UPDATE agenda SET sucursal = ?, fecha_hora = ? WHERE id = ? AND paciente_ci = ? AND estado = \'confirmada\'');
    $update->execute([$sucursal, $date->format('Y-m-d H:i:s'), $citaId, $patientCi]);
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Sucursal y turno guardados correctamente.']);
} catch (PDOException $error) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Error actualizando turno del paciente: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo guardar el turno.']);
}
