<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$citaId = isset($input['cita_id']) ? (int)$input['cita_id'] : 0;
$estado = $input['estado'] ?? '';
$motivo = trim((string)($input['motivo_cancelacion'] ?? ''));
$sucursal = trim((string)($input['sucursal'] ?? ''));
$fechaHora = trim((string)($input['fecha_hora'] ?? ''));

if ($citaId <= 0 || !in_array($estado, ['confirmada', 'cancelada'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cita o estado inválido']);
    exit;
}

if ($estado === 'cancelada' && $motivo === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Debes indicar el motivo de la cancelación.']);
    exit;
}

if (mb_strlen($motivo) > 500) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El motivo no puede superar los 500 caracteres.']);
    exit;
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("SELECT id, paciente_ci, estudio, medico, sucursal, fecha_hora, estado
                            FROM agenda
                            WHERE id = ? AND estado = 'pendiente'
                            FOR UPDATE");
    $stmt->execute([$citaId]);
    $cita = $stmt->fetch();

    if (!$cita) {
        $conn->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'La solicitud ya no está pendiente.']);
        exit;
    }

    if ($estado === 'cancelada') {
        $insert = $conn->prepare("INSERT INTO citas_canceladas
            (cita_id, paciente_ci, estudio, medico, sucursal, fecha_hora, estado_anterior, motivo_cancelacion, cancelada_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->execute([
            $cita['id'],
            $cita['paciente_ci'],
            $cita['estudio'],
            $cita['medico'],
            $cita['sucursal'],
            $cita['fecha_hora'],
            $cita['estado'],
            $motivo,
            'administrativo',
        ]);
    }

    if ($estado === 'confirmada' && ($sucursal !== '' || $fechaHora !== '')) {
        if ($sucursal === '' || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $fechaHora)) {
            $conn->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Selecciona sucursal, fecha y hora.']);
            exit;
        }
        $parsedDate = DateTime::createFromFormat('Y-m-d\\TH:i', $fechaHora);
        if (!$parsedDate) {
            $conn->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La fecha y hora no son válidas.']);
            exit;
        }
        $minutesFromOpening = ((int)$parsedDate->format('H') * 60 + (int)$parsedDate->format('i')) - (8 * 60);
        if ($minutesFromOpening < 0 || $minutesFromOpening % 45 !== 0) {
            $conn->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La hora debe pertenecer a un intervalo de 45 minutos.']);
            exit;
        }
        $occupied = $conn->prepare("SELECT COUNT(*) FROM agenda WHERE sucursal = ? AND fecha_hora = ? AND estado <> 'cancelada' AND id <> ?");
        $occupied->execute([$sucursal, $parsedDate->format('Y-m-d H:i:s'), $citaId]);
        if ((int)$occupied->fetchColumn() > 0) {
            $conn->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Ese horario ya está ocupado.']);
            exit;
        }
        $update = $conn->prepare('UPDATE agenda SET estado = ?, sucursal = ?, fecha_hora = ? WHERE id = ? AND estado = \'pendiente\'');
        $update->execute([$estado, $sucursal, $parsedDate->format('Y-m-d H:i:s'), $citaId]);
    } else {
        $update = $conn->prepare('UPDATE agenda SET estado = ? WHERE id = ? AND estado = \'pendiente\'');
        $update->execute([$estado, $citaId]);
    }

    $updatedStmt = $conn->prepare('SELECT estado FROM agenda WHERE id = ?');
    $updatedStmt->execute([$citaId]);
    $updatedState = $updatedStmt->fetchColumn();

    if ($updatedState !== $estado) {
        $conn->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'No se pudo actualizar la solicitud.']);
        exit;
    }

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => $estado === 'confirmada'
            ? 'Cita confirmada correctamente.'
            : 'Cita cancelada y archivada correctamente.',
    ]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Error actualizando estado de cita desde admin: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno al actualizar la cita.']);
}