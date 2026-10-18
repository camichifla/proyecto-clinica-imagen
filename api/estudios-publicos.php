<?php
// Public, read-only; no login, no session. Only non-sensitive fields.
require_once __DIR__ . '/../includes/estudios.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$activos = array_filter(estudios_catalogo(), static fn (array $e): bool => (int) $e['activo'] === 1);
echo json_encode(['estudios' => array_values(array_map(static fn (array $e): array => [
    'etiqueta'      => $e['etiqueta'],
    'descripcion'   => $e['descripcion'] ?? '',
    'autoagendable' => (int) $e['autoagendable'] === 1,
], $activos))], JSON_UNESCAPED_UNICODE);
