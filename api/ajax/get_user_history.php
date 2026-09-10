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

$patientStmt = $conn->prepare('SELECT CI, name, surname, email, phone FROM users WHERE CI = ? LIMIT 1');
$patientStmt->execute([$ci]);
$patient = $patientStmt->fetch();

$rows = [];
$pending = [];
$studies = [];

// El paciente debe poder mostrarse aunque una tabla secundaria todavía no exista.
try {
    $stmt = $conn->prepare('SELECT id, paciente_ci, tipo, nota, tecnico, creado_en FROM historial WHERE paciente_ci = ? ORDER BY creado_en DESC');
    $stmt->execute([$ci]);
    $rows = $stmt->fetchAll();
} catch (PDOException $error) {
    error_log('No se pudo consultar historial: ' . $error->getMessage());
}

try {
    $pendingStmt = $conn->prepare("SELECT a.id, a.estudio, a.medico, a.sucursal, a.fecha_hora, a.estado,
                                          cc.motivo_cancelacion, cc.cancelada_por
                                   FROM agenda a
                                   LEFT JOIN citas_canceladas cc ON cc.cita_id = a.id
                                   WHERE a.paciente_ci = ? ORDER BY a.fecha_hora ASC");
    $pendingStmt->execute([$ci]);
    $pending = $pendingStmt->fetchAll();
} catch (PDOException $error) {
    error_log('No se pudo consultar solicitudes del paciente: ' . $error->getMessage());
}

try {
    $studiesStmt = $conn->prepare('SELECT id, estudio, tecnico, fecha_estudio, archivo_visor, archivo_pdf FROM estudios WHERE paciente_ci = ? ORDER BY fecha_estudio DESC, id DESC');
    $studiesStmt->execute([$ci]);
    $studies = $studiesStmt->fetchAll();
    foreach ($studies as &$study) {
        $study['visor_url'] = $study['archivo_visor'] ? '/clinica-imagen/api/ajax/view_study_file.php?id=' . (int) $study['id'] . '&tipo=visor' : null;
        $study['pdf_url'] = $study['archivo_pdf'] ? '/clinica-imagen/api/ajax/view_study_file.php?id=' . (int) $study['id'] . '&tipo=pdf' : null;
        unset($study['archivo_visor'], $study['archivo_pdf']);
    }
    unset($study);
} catch (PDOException $error) {
    error_log('No se pudieron consultar estudios: ' . $error->getMessage());
}

echo json_encode([
    'success' => true,
    'patient' => $patient ?: null,
    'pending' => $pending,
    'data' => $rows,
    'estudios' => $studies,
]);
