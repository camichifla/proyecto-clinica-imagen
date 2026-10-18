<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/resultados_upload.php';
require_once __DIR__ . '/../includes/pacientes_profesional.php';

// administrador: any patient. profesional: only their own patients (citas
// assigned to them), and the result is tied to that professional.
$usuario = requerir_rol_json(['administrador', 'profesional']);
$esAdmin = $usuario['rol'] === 'administrador';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);

    if ($esAdmin) {
        $pacienteValido = function (int $id): bool {
            $stmt = db()->prepare('SELECT id FROM pacientes WHERE id = ?');
            $stmt->execute([$id]);
            return (bool) $stmt->fetch();
        };
        procesar_post_resultado($usuario, $pacienteValido, null, 'cargar-resultados.html');
    } else {
        procesar_post_resultado($usuario, fn(int $id) => paciente_de_profesional($usuario['ref_id'], $id), $usuario['ref_id'], 'cargar-resultados.html');
    }
    exit;
}

if ($esAdmin) {
    $pacientes = db()->query('SELECT id, nombre, apellido, ci FROM pacientes ORDER BY apellido, nombre')->fetchAll();
    $citasFilas = db()->query(
        "SELECT id, paciente_id, fecha_hora_solicitada, estudio
         FROM citas
         WHERE estado = 'confirmada'
         ORDER BY fecha_hora_solicitada DESC"
    )->fetchAll();
} else {
    $pacientes = pacientes_de_profesional($usuario['ref_id']);
    $stmtCitas = db()->prepare(
        "SELECT id, paciente_id, fecha_hora_solicitada, estudio
         FROM citas
         WHERE profesional_id = ? AND estado = 'confirmada'
         ORDER BY fecha_hora_solicitada DESC"
    );
    $stmtCitas->execute([$usuario['ref_id']]);
    $citasFilas = $stmtCitas->fetchAll();
}

echo json_encode([
    'pacientes'          => $pacientes,
    'citas_por_paciente' => citas_por_paciente($citasFilas),
], JSON_UNESCAPED_UNICODE);
