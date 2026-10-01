<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
$usuario = requerir_rol(['administrador']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/agenda_render.php';
require_once __DIR__ . '/../includes/mailer.php';

/**
 * Resuelve el email de notificacion de una cita: cuenta del paciente
 * registrado, o el email_pendiente si aun no tiene cuenta. Null si no hay
 * ninguno (cita vieja sin email_pendiente).
 */
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

const NOTAS_ADMIN_MAX = 255;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $accion = $_POST['accion'] ?? '';
    $citaId = filter_input(INPUT_POST, 'cita_id', FILTER_VALIDATE_INT);

    if ($accion === 'confirmar' && $citaId) {
        // Ownership isn't relevant here (admin-only page), but the state
        // check IS enforced inside the WHERE clause itself, not just
        // beforehand in PHP — protects against double-processing if two
        // admin tabs are open, or the row already got processed.
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
        }

        header('Location: solicitudes-cita.php?' . ($stmt->rowCount() > 0 ? 'confirmada=1' : 'error=1'));
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
        }

        header('Location: solicitudes-cita.php?' . ($stmt->rowCount() > 0 ? 'rechazada=1' : 'error=1'));
        exit;
    }

    header('Location: solicitudes-cita.php?error=1');
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
require __DIR__ . '/../views/solicitudes-cita.php';
