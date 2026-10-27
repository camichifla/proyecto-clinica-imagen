<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/recepcion.php';
require_once __DIR__ . '/../includes/agenda.php';
require_once __DIR__ . '/../includes/mailer.php';

$usuario = requerir_rol_json(['administrador', 'recepcionista']);
require_once __DIR__ . '/../includes/recepcion.php';
$sucRecep = filtro_sucursal_recepcion($usuario);

const MENSAJE_SOLICITUD_NO_PROCESADA = 'No se pudo procesar la solicitud (puede que ya haya sido resuelta).';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $accion = $_POST['accion'] ?? '';
    $citaId = filter_input(INPUT_POST, 'cita_id', FILTER_VALIDATE_INT);

    if (in_array($accion, ['confirmar', 'rechazar'], true) && $citaId) {
        $confirmar = $accion === 'confirmar';
        $notas     = $confirmar ? '' : mb_substr(trim((string) ($_POST['notas_admin'] ?? '')), 0, NOTAS_ADMIN_MAX);

        $datosCita = db()->prepare(
            'SELECT id, paciente_id, email_pendiente, fecha_hora_solicitada, sucursal_id FROM citas WHERE id = ?'
        );
        $datosCita->execute([$citaId]);
        $cita = $datosCita->fetch();
        if ($cita && $sucRecep !== null && (int) $cita['sucursal_id'] !== $sucRecep) {
            $cita = false; // not this receptionist's branch
        }
        if (!$cita) {
            // Checked before any UPDATE: another branch's (or a missing) cita is never touched.
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => MENSAJE_SOLICITUD_NO_PROCESADA], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($confirmar) {
            $stmt = db()->prepare(
                "UPDATE citas
                    SET estado = 'confirmada', fecha_hora_confirmada = fecha_hora_solicitada
                  WHERE id = ? AND estado = 'pendiente'"
            );
            $stmt->execute([$citaId]);
        } else {
            $stmt = db()->prepare(
                "UPDATE citas
                    SET estado = 'rechazada', notas_admin = ?
                  WHERE id = ? AND estado = 'pendiente'"
            );
            $stmt->execute([$notas !== '' ? $notas : null, $citaId]);
        }

        if ($stmt->rowCount() > 0) {
            registrar_evento($confirmar ? 'cita_confirmar' : 'cita_rechazar', 'cita=' . $citaId);
            $email = email_notificacion_cita($cita['paciente_id'], $cita['email_pendiente']);
            if ($email !== null) {
                enviar_notificacion_cita($email, $confirmar ? 'confirmada' : 'rechazada', $cita['fecha_hora_solicitada'], $notas);
            }
            avisar_medico_cita((int) $cita['id'], $confirmar ? 'confirmada' : 'rechazada', $cita['fecha_hora_solicitada'], $notas);
            echo json_encode(['ok' => true, 'mensaje' => 'La cita fue ' . ($confirmar ? 'confirmada.' : 'rechazada.')], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => MENSAJE_SOLICITUD_NO_PROCESADA], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => MENSAJE_SOLICITUD_NO_PROCESADA], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmtCitas = db()->prepare(
    "SELECT c.id, c.fecha_hora_solicitada, c.estudio, c.paciente_id, c.email_pendiente,
            c.nombre_pendiente, c.apellido_pendiente,
            pa.nombre AS paciente_nombre, pa.apellido AS paciente_apellido,
            pr.nombre AS profesional_nombre, pr.apellido AS profesional_apellido,
            s.nombre AS sucursal_nombre
       FROM citas c
       LEFT JOIN pacientes pa ON pa.id = c.paciente_id
       JOIN profesionales pr ON pr.id = c.profesional_id
       LEFT JOIN sucursales s ON s.id = c.sucursal_id
      WHERE c.estado = 'pendiente'" . ($sucRecep !== null ? ' AND c.sucursal_id = ?' : '') . "
      ORDER BY c.fecha_hora_solicitada ASC"
);
$stmtCitas->execute($sucRecep !== null ? [$sucRecep] : []);
$citas = $stmtCitas->fetchAll();

echo json_encode([
    'notasAdminMax' => NOTAS_ADMIN_MAX,
    'citas'         => array_map(static function (array $cita): array {
        return [
            'id'               => (int) $cita['id'],
            'fechaHoraSolicitada' => $cita['fecha_hora_solicitada'],
            'pacienteLabel'    => cita_nombre_paciente($cita),
            'estudioLabel'     => estudios_etiquetas()[$cita['estudio']] ?? $cita['estudio'],
            'sucursalNombre'   => $cita['sucursal_nombre'],
            'profesionalLabel' => $cita['profesional_apellido'] . ', ' . $cita['profesional_nombre'],
        ];
    }, $citas),
], JSON_UNESCAPED_UNICODE);
