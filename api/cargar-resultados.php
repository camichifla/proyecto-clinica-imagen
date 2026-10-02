<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/resultados_upload.php';
require_once __DIR__ . '/../includes/pacientes_profesional.php';

$usuario = requerir_rol_json(['profesional']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);

    procesar_post_resultado($usuario, fn(int $id) => paciente_de_profesional($usuario['ref_id'], $id), $usuario['ref_id'], 'cargar-resultados.html');
    exit;
}

$pacientes = pacientes_de_profesional($usuario['ref_id']);

$stmtCitas = db()->prepare(
    "SELECT id, paciente_id, fecha_hora_solicitada, estudio
     FROM citas
     WHERE profesional_id = ? AND estado = 'confirmada'
     ORDER BY fecha_hora_solicitada DESC"
);
$stmtCitas->execute([$usuario['ref_id']]);

$citasPorPaciente = citas_por_paciente($stmtCitas->fetchAll());

echo json_encode([
    'pacientes'          => $pacientes,
    'citas_por_paciente' => $citasPorPaciente,
], JSON_UNESCAPED_UNICODE);
