<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/resultados.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/pacientes_medico.php';
require_once __DIR__ . '/../includes/pacientes_profesional.php';

$usuario = requerir_rol_json(['administrador', 'medico', 'profesional']);

function no_encontrado_json(): void
{
    http_response_code(404);
    echo json_encode(['error' => 'Paciente no encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pacienteId = filter_input(INPUT_GET, 'paciente_id', FILTER_VALIDATE_INT);
if (!$pacienteId) {
    no_encontrado_json();
}

switch ($usuario['rol']) {
    case 'administrador':
        $stmt = db()->prepare('SELECT id, nombre, apellido, ci FROM pacientes WHERE id = ?');
        $stmt->execute([$pacienteId]);
        $paciente = $stmt->fetch();
        break;
    case 'medico':

        $paciente = null;
        if (paciente_conectado_a_medico($usuario['cuenta_id'], $usuario['ref_id'], $pacienteId)) {
            $stmt = db()->prepare('SELECT id, nombre, apellido, ci FROM pacientes WHERE id = ?');
            $stmt->execute([$pacienteId]);
            $paciente = $stmt->fetch();
        }
        break;
    case 'profesional':
        $paciente = paciente_de_profesional($usuario['ref_id'], $pacienteId);
        break;
}

if (!$paciente) {
    no_encontrado_json();
}

[$resultados, $imagenesPorResultado] = obtener_resultados_de_paciente(db(), (int) $paciente['id']);

$respuesta = [
    'paciente'   => [
        'id'       => (int) $paciente['id'],
        'nombre'   => $paciente['nombre'],
        'apellido' => $paciente['apellido'],
        'ci'       => $paciente['ci'],
    ],
    'resultados' => resultados_json($resultados, $imagenesPorResultado),
];

if ($usuario['rol'] === 'profesional') {
    $stmtCitas = db()->prepare(
        'SELECT c.*, s.nombre AS sucursal_nombre
         FROM citas c
         LEFT JOIN sucursales s ON s.id = c.sucursal_id
         WHERE c.paciente_id = ? AND c.profesional_id = ?
         ORDER BY c.fecha_hora_solicitada DESC'
    );
    $stmtCitas->execute([(int) $paciente['id'], $usuario['ref_id']]);

    $respuesta['citas'] = array_map(static function (array $cita): array {
        return [
            'fecha_hora_solicitada' => $cita['fecha_hora_solicitada'],
            'sucursal_nombre'       => $cita['sucursal_nombre'],
            'estudio_label'         => ESTUDIOS[$cita['estudio']] ?? $cita['estudio'],
            'estado'                => $cita['estado'],
            'estado_label'          => ESTADOS_CITA[$cita['estado']] ?? $cita['estado'],
            'fecha_hora_confirmada' => $cita['fecha_hora_confirmada'],
            'notas_admin'           => $cita['notas_admin'],
        ];
    }, $stmtCitas->fetchAll());
}

echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
