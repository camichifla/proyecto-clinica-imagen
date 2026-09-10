<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Usuario no autenticado');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$type = $_GET['tipo'] ?? '';
$column = $type === 'pdf' ? 'archivo_pdf' : ($type === 'visor' ? 'archivo_visor' : null);
if (!$id || !$column) {
    http_response_code(400);
    exit('Archivo inválido');
}

$stmt = $conn->prepare("SELECT $column AS archivo FROM estudios WHERE id = ? AND paciente_ci = ?");
$patientCi = getUserId();
if (getUserType() === 'admin') {
    $stmt = $conn->prepare("SELECT $column AS archivo FROM estudios WHERE id = ?");
    $stmt->execute([$id]);
} else {
    $stmt->execute([$id, $patientCi]);
}
$study = $stmt->fetch();
if (!$study || !$study['archivo']) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

$basePath = realpath(__DIR__ . '/../../storage/estudios');
$filePath = $basePath ? realpath($basePath . DIRECTORY_SEPARATOR . basename($study['archivo'])) : false;
if (!$filePath || !is_file($filePath) || !str_starts_with($filePath, $basePath . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

$mime = mime_content_type($filePath) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: ' . ($type === 'pdf' ? 'attachment' : 'inline') . '; filename="' . basename($filePath) . '"');
readfile($filePath);
