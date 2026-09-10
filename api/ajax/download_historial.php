<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
requireRole('admin');

$ci = isset($_GET['ci']) ? (int)$_GET['ci'] : 0;
$stmt = $conn->prepare('SELECT name, surname FROM users WHERE CI = ? LIMIT 1');
$stmt->execute([$ci]);
$patient = $stmt->fetch();

$historyStmt = $conn->prepare('SELECT tipo, nota, tecnico, creado_en FROM historial WHERE paciente_ci = ? ORDER BY creado_en ASC');
$historyStmt->execute([$ci]);
$history = $historyStmt->fetchAll();

if (!$patient || !$history) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No hay datos registrados.';
    exit;
}

function pdfText(string $value): string {
    $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
}

$lines = [
    'Historial clinico',
    'Paciente: ' . $patient['name'] . ' ' . $patient['surname'],
    'Cedula: ' . $ci,
    '',
];
foreach ($history as $entry) {
    $lines[] = $entry['creado_en'] . ' - ' . ucfirst($entry['tipo']);
    $lines[] = 'Profesional: ' . $entry['tecnico'];
    foreach (str_split(str_replace(["\r", "\n"], ' ', $entry['nota']), 90) as $line) {
        $lines[] = $line;
    }
    $lines[] = '';
}

$content = "BT\n/F1 11 Tf\n50 780 Td\n";
foreach ($lines as $index => $line) {
    if ($index > 0) $content .= "0 -16 Td\n";
    $content .= '(' . pdfText($line) . ") Tj\n";
}
$content .= "ET\n";

$objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
    '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream",
];

$pdf = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $number => $object) {
    $offsets[$number + 1] = strlen($pdf);
    $pdf .= ($number + 1) . " 0 obj\n" . $object . "\nendobj\n";
}
$xref = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
for ($number = 1; $number <= count($objects); $number++) {
    $pdf .= sprintf('%010d 00000 n \n', $offsets[$number]);
}
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="historial-' . $ci . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;