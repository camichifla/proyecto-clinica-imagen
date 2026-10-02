<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/agenda_render.php';
require_once __DIR__ . '/../includes/mailer.php';

$usuario = requerir_rol_json(['administrador']);

const NOTAS_ADMIN_MAX = 255;

function email_notificacion_cita(?int $pacienteId, ?string $emailPendiente): ?string
{
    if ($pacienteId !== null) {
        $stmt = db()->prepare("SELECT email FROM cuentas WHERE rol = 'paciente' AND ref_id = ?");
        $stmt->execute([$pacienteId]);
        $email = $stmt->fetchColumn();
        return $email !== false ? $email : null;
    }

    return $emailPendiente !== null && $emailPendiente !== '' ? $emailPendiente : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $accion = $_POST['accion'] ?? '';
    $citaId = filter_input(INPUT_POST, 'cita_id', FILTER_VALIDATE_INT);

    if ($accion === 'confirmar' && $citaId) {

        $datosCita = db()->prepare(
            'SELECT paciente_id, email_pendiente, fecha_hora_solicitada FROM citas WHERE id = ?'
        );
        $datosCita->execute([$citaId]);
        $cita = $datosCita->fetch();

        $stmt = db()->prepare(
            "UPDATE citas
                SET estado = 'confirmada', fecha_hora_confirmada = fecha_hora_solicitada
              WHERE id = ? AND estado = 'pendiente'"
        );
        $stmt->execute([$citaId]);

        if ($stmt->rowCount() > 0 && $cita) {
            $email = email_notificacion_cita($cita['paciente_id'], $cita['email_pendiente']);
            if ($email !== null) {
                enviar_notificacion_cita($email, 'confirmada', $cita['fecha_hora_solicitada']);
            }
            echo json_encode(['ok' => true, 'mensaje' => 'La cita fue confirmada.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'No se pudo procesar la solicitud (puede que ya haya sido resuelta).'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($accion === 'rechazar' && $citaId) {
        $notas = trim((string) ($_POST['notas_admin'] ?? ''));
        if (mb_strlen($notas) > NOTAS_ADMIN_MAX) {
            $notas = mb_substr($notas, 0, NOTAS_ADMIN_MAX);
        }

        $datosCita = db()->prepare(
            'SELECT paciente_id, email_pendiente, fecha_hora_solicitada FROM citas WHERE id = ?'
        );
        $datosCita->execute([$citaId]);
        $cita = $datosCita->fetch();

        $stmt = db()->prepare(
            "UPDATE citas
                SET estado = 'rechazada', notas_admin = ?
              WHERE id = ? AND estado = 'pendiente'"
        );
        $stmt->execute([$notas !== '' ? $notas : null, $citaId]);

        if ($stmt->rowCount() > 0 && $cita) {
            $email = email_notificacion_cita($cita['paciente_id'], $cita['email_pendiente']);
            if ($email !== null) {
                enviar_notificacion_cita($email, 'rechazada', $cita['fecha_hora_solicitada'], $notas);
            }
            echo json_encode(['ok' => true, 'mensaje' => 'La cita fue rechazada.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'No se pudo procesar la solicitud (puede que ya haya sido resuelta).'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No se pudo procesar la solicitud (puede que ya haya sido resuelta).'], JSON_UNESCAPED_UNICODE);
    exit;
}

$citas = db()->query(
    "SELECT c.id, c.fecha_hora_solicitada, c.estudio, c.paciente_id, c.email_pendiente,
            c.nombre_pendiente, c.apellido_pendiente,
            pa.nombre AS paciente_nombre, pa.apellido AS paciente_apellido,
            pr.nombre AS profesional_nombre, pr.apellido AS profesional_apellido,
            s.nombre AS sucursal_nombre
       FROM citas c
       LEFT JOIN pacientes pa ON pa.id = c.paciente_id
       JOIN profesionales pr ON pr.id = c.profesional_id
       LEFT JOIN sucursales s ON s.id = c.sucursal_id
      WHERE c.estado = 'pendiente'
      ORDER BY c.fecha_hora_solicitada ASC"
)->fetchAll();

echo json_encode([
    'notasAdminMax' => NOTAS_ADMIN_MAX,
    'citas'         => array_map(static function (array $cita): array {
        return [
            'id'               => (int) $cita['id'],
            'fechaHoraSolicitada' => $cita['fecha_hora_solicitada'],
            'pacienteLabel'    => cita_nombre_paciente($cita),
            'estudioLabel'     => ESTUDIOS[$cita['estudio']] ?? $cita['estudio'],
            'sucursalNombre'   => $cita['sucursal_nombre'],
            'profesionalLabel' => $cita['profesional_apellido'] . ', ' . $cita['profesional_nombre'],
        ];
    }, $citas),
], JSON_UNESCAPED_UNICODE);
