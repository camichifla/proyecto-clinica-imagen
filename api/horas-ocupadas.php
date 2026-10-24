<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/horarios.php';
require_once __DIR__ . '/../includes/estudios.php';

requerir_rol_json(['paciente', 'medico', 'administrador', 'recepcionista']);

$sucursalId = filter_input(INPUT_GET, 'sucursal_id', FILTER_VALIDATE_INT);
$fecha      = (string) ($_GET['fecha'] ?? '');
$estudio    = (string) ($_GET['estudio'] ?? '');
$profesionalId = filter_input(INPUT_GET, 'profesional_id', FILTER_VALIDATE_INT) ?: null;

$fechaOk = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
if (!$sucursalId || !$fechaOk || $fechaOk->format('Y-m-d') !== $fecha || !isset(estudios_etiquetas()[$estudio])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ocupadas' => horas_ocupadas($sucursalId, $fecha, $profesionalId, $estudio)], JSON_UNESCAPED_UNICODE);
