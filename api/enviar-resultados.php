<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/resultados_upload.php';

$usuario = requerir_rol_json(['administrador']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);

    $pacienteExiste = function (int $id): bool {
        $stmt = db()->prepare('SELECT id FROM pacientes WHERE id = ?');
        $stmt->execute([$id]);
        return (bool) $stmt->fetch();
    };
    procesar_post_resultado($usuario, $pacienteExiste, null, 'enviar-resultados.html');
    exit;
}

$pacientes = db()->query('SELECT id, nombre, apellido, ci FROM pacientes ORDER BY apellido, nombre')->fetchAll();

$citasFilas = db()->query(
    "SELECT id, paciente_id, fecha_hora_solicitada, estudio
     FROM citas
     WHERE estado = 'confirmada'
     ORDER BY fecha_hora_solicitada DESC"
)->fetchAll();

$citasPorPaciente = citas_por_paciente($citasFilas);

echo json_encode([
    'pacientes'          => $pacientes,
    'citas_por_paciente' => $citasPorPaciente,
], JSON_UNESCAPED_UNICODE);
