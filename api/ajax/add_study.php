<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
requireRole('admin');

$patientCi = filter_input(INPUT_POST, 'paciente_ci', FILTER_VALIDATE_INT);
$studyName = trim($_POST['estudio'] ?? '');
$technician = trim($_POST['tecnico'] ?? '');
$studyDate = trim($_POST['fecha_estudio'] ?? '');

if (!$patientCi || $studyName === '' || $technician === '' || $studyDate === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Completa paciente, estudio, técnico y fecha.']);
    exit;
}

$date = DateTime::createFromFormat('Y-m-d\\TH:i', $studyDate);
if (!$date) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La fecha del estudio no es válida.']);
    exit;
}

$uploadDir = __DIR__ . '/../../storage/estudios';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo preparar el almacenamiento.']);
    exit;
}

function saveStudyUpload(string $field, string $prefix, array $expectedMimes, string $uploadDir): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        throw new RuntimeException('No se pudo cargar uno de los archivos.');
    }
    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    if (!in_array($mime, $expectedMimes, true)) throw new RuntimeException('El archivo de ' . $prefix . ' no tiene un formato válido.');
    $extension = in_array('application/pdf', $expectedMimes, true) ? 'pdf' : 'img';
    $filename = $prefix . '-' . bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $uploadDir . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('No se pudo guardar el archivo de ' . $prefix . '.');
    }
    return $filename;
}

try {
    $patientStmt = $conn->prepare('SELECT CI FROM users WHERE CI = ? LIMIT 1');
    $patientStmt->execute([$patientCi]);
    if (!$patientStmt->fetch()) throw new RuntimeException('El paciente no existe.');

    $viewerFile = saveStudyUpload('archivo_visor', 'visor', ['image/jpeg', 'image/png', 'image/webp'], $uploadDir);
    $pdfFile = saveStudyUpload('archivo_pdf', 'informe', ['application/pdf'], $uploadDir);

    $stmt = $conn->prepare('INSERT INTO estudios (paciente_ci, estudio, tecnico, fecha_estudio, archivo_visor, archivo_pdf) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$patientCi, $studyName, $technician, $date->format('Y-m-d H:i:s'), $viewerFile, $pdfFile]);
    echo json_encode(['success' => true]);
} catch (Throwable $error) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
}
