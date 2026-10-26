<?php
// includes/mailer.php — the only file in the project that knows how mail
// is delivered. Sends via PHPMailer/SMTP when
// config.php has complete SMTP settings; otherwise appends the link to
// storage/mail.log so the flow completes end-to-end without a mail server.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/estudios.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function smtp_configurado(): bool
{
    return SMTP_HOST !== '' && SMTP_USER !== '' && SMTP_PASS !== '';
}

/** Returns true in both modes: the dev fallback always "succeeds" so registration/reset complete without a mail server. */
function enviar_correo(string $destinatario, string $asunto, string $cuerpo): bool
{
    if (!smtp_configurado()) {
        if (APP_HTTPS) {
            error_log('enviar_correo: SMTP not configured; refusing to write plaintext links to mail.log in production.');
            return false;
        }
        $linea = sprintf(
            "[%s] to=%s subject=%s\n%s\n---\n",
            date('Y-m-d H:i:s'),
            $destinatario,
            $asunto,
            $cuerpo
        );
        $ruta = __DIR__ . '/../storage/mail.log';
        $ok = @file_put_contents($ruta, $linea, FILE_APPEND | LOCK_EX);
        @chmod($ruta, 0600);
        return $ok !== false;
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($destinatario);
        $mail->isHTML(false);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo;

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('enviar_correo: ' . $mail->ErrorInfo);
        return false;
    }
}

function enviar_verificacion(string $email, string $link): bool
{
    return enviar_correo(
        $email,
        'Verificá tu cuenta - Clínica Imagen',
        "Gracias por registrarte. Confirmá tu cuenta visitando el siguiente enlace:\n\n{$link}\n\nEl enlace vence en 24 horas."
    );
}

function enviar_reset(string $email, string $link): bool
{
    return enviar_correo(
        $email,
        'Restablecer contraseña - Clínica Imagen',
        "Solicitaste restablecer tu contraseña. Visitá el siguiente enlace para elegir una nueva:\n\n{$link}\n\nEl enlace vence en 1 hora. Si no fuiste vos, ignorá este mensaje."
    );
}

/** Patient registered by staff: the reset link sets their first password. */
function enviar_bienvenida_paciente(string $email, string $link): bool
{
    return enviar_correo(
        $email,
        'Tu cuenta en Clínica Imagen',
        "La clínica creó tu cuenta de paciente. Visitá el siguiente enlace para elegir tu contraseña:\n\n{$link}\n\nEl enlace vence en 24 horas. Si vence, pedí uno nuevo desde \"Olvidé mi contraseña\"."
    );
}

/**
 * Email of the recipient of a cita's notifications: the patient's account,
 * or email_pendiente if they don't have an account yet. Null if there is none.
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

/** Email of the medico account that booked the cita (ordenes.creado_por), or null if no medico did. */
function email_medico_cita(int $citaId): ?string
{
    $stmt = db()->prepare(
        "SELECT cu.email FROM ordenes o JOIN cuentas cu ON cu.id = o.creado_por
          WHERE o.cita_id = ? AND cu.rol = 'medico' LIMIT 1"
    );
    $stmt->execute([$citaId]);
    $email = $stmt->fetchColumn();
    return $email !== false ? $email : null;
}

/** "2026-03-09 14:30:00" -> "09/03/2026 14:30"; anything else is returned as is. */
function fecha_hora_legible(string $fechaHora): string
{
    $f = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $fechaHora);
    return $f !== false ? $f->format('d/m/Y H:i') : $fechaHora;
}

function enviar_notificacion_cita(string $email, string $estado, string $fechaHora, string $notas = ''): bool
{
    $fechaHora = fecha_hora_legible($fechaHora);
    if ($estado === 'confirmada') {
        return enviar_correo(
            $email,
            'Tu cita fue confirmada - Clínica Imagen',
            "Tu cita fue confirmada para el {$fechaHora}.\n\nSi no podés asistir, contactanos para reprogramarla."
        );
    }

    if ($estado === 'cancelada') {
        $cuerpo = "Tu cita del {$fechaHora} fue cancelada.";
        if ($notas !== '') {
            $cuerpo .= "\n\nMotivo: {$notas}";
        }
        return enviar_correo($email, 'Tu cita fue cancelada - Clínica Imagen', $cuerpo);
    }

    if ($estado === 'reprogramada') {
        return enviar_correo(
            $email,
            'Tu cita fue reprogramada - Clínica Imagen',
            "Tu cita fue reprogramada para el {$fechaHora}."
        );
    }

    $cuerpo = "Tu cita solicitada para el {$fechaHora} fue rechazada.";
    if ($notas !== '') {
        $cuerpo .= "\n\nMotivo: {$notas}";
    }

    return enviar_correo($email, 'Tu cita fue rechazada - Clínica Imagen', $cuerpo);
}

/**
 * Acknowledges a just-booked (pending) cita to the patient. Best effort: a mail failure never fails the booking.
 */
function avisar_solicitud_recibida(int $citaId): void
{
    try {
        $stmt = db()->prepare(
            'SELECT c.paciente_id, c.email_pendiente, c.fecha_hora_solicitada, s.nombre AS sede
               FROM citas c LEFT JOIN sucursales s ON s.id = c.sucursal_id WHERE c.id = ?'
        );
        $stmt->execute([$citaId]);
        $c = $stmt->fetch();
        $email = $c ? email_notificacion_cita($c['paciente_id'] !== null ? (int) $c['paciente_id'] : null, $c['email_pendiente']) : null;
        if ($email === null) {
            return;
        }
        enviar_correo(
            $email,
            'Recibimos tu solicitud de cita - Clínica Imagen',
            'Recibimos tu solicitud de cita ' . codigo_cita($citaId) . ' para el ' . fecha_hora_legible($c['fecha_hora_solicitada'])
                . ' en ' . ($c['sede'] ?? 'la sede elegida') . ".\n\nLa clínica la va a confirmar y te avisamos por correo."
        );
    } catch (Throwable $e) {
        error_log('avisar_solicitud_recibida: ' . $e->getMessage());
    }
}

/**
 * Tells the medico who booked a cita how it ended up (for a reprogramada, $fechaHora is the new date).
 * Does nothing if no medico booked it or the medico's address is the patient's own (they get the patient email).
 */
function avisar_medico_cita(int $citaId, string $estado, string $fechaHora, string $notas = ''): void
{
    $emailMedico = email_medico_cita($citaId);
    if ($emailMedico === null) {
        return;
    }

    $stmt = db()->prepare(
        'SELECT c.paciente_id, c.email_pendiente, p.nombre, p.apellido, c.nombre_pendiente, c.apellido_pendiente
           FROM citas c LEFT JOIN pacientes p ON p.id = c.paciente_id WHERE c.id = ?'
    );
    $stmt->execute([$citaId]);
    $c = $stmt->fetch();
    if (!$c) {
        return;
    }

    $emailPaciente = email_notificacion_cita($c['paciente_id'] !== null ? (int) $c['paciente_id'] : null, $c['email_pendiente']);
    if ($emailPaciente !== null && strcasecmp($emailPaciente, $emailMedico) === 0) {
        return;
    }

    $paciente = trim(($c['nombre'] ?? $c['nombre_pendiente'] ?? '') . ' ' . ($c['apellido'] ?? $c['apellido_pendiente'] ?? ''));
    $paciente = $paciente !== '' ? $paciente : 'tu paciente';
    $fecha    = fecha_hora_legible($fechaHora);
    $cuerpo   = $estado === 'reprogramada'
        ? "La cita que agendaste para {$paciente} fue reprogramada para el {$fecha}."
        : "La cita que agendaste para {$paciente} el {$fecha} fue {$estado}.";
    if ($notas !== '' && in_array($estado, ['cancelada', 'rechazada'], true)) {
        $cuerpo .= "\n\nMotivo: {$notas}";
    }

    enviar_correo($emailMedico, "La cita de {$paciente} fue {$estado} - Clínica Imagen", $cuerpo);
}

/** No clinical content, just the link. */
function enviar_aviso_resultado(string $email, string $link): bool
{
    return enviar_correo(
        $email,
        'Tenés un nuevo resultado - Clínica Imagen',
        "Hay un nuevo resultado disponible en tu portal. Ingresá para verlo:\n\n{$link}"
    );
}

/** Issues a fresh verification token: stores only its sha256 hash and invalidates any prior outstanding one. Shared by registration and verify-resend. */
function emitir_token_verificacion(PDO $db, int $cuentaId): string
{
    $raw  = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);

    $db->prepare('UPDATE tokens_verificacion SET usado_en = NOW() WHERE cuenta_id = ? AND usado_en IS NULL')
        ->execute([$cuentaId]);

    $db->prepare('INSERT INTO tokens_verificacion (cuenta_id, token_hash, expira_en) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))')
        ->execute([$cuentaId, $hash]);

    return $raw;
}

/** Same as emitir_token_verificacion, but separate table/lifetime/scope; invalidates prior reset tokens. */
function emitir_token_reset(PDO $db, int $cuentaId, int $horas = 1): string
{
    $raw  = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);

    $db->prepare('UPDATE tokens_reset SET usado_en = NOW() WHERE cuenta_id = ? AND usado_en IS NULL')
        ->execute([$cuentaId]);

    $db->prepare('INSERT INTO tokens_reset (cuenta_id, token_hash, expira_en) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))')
        ->execute([$cuentaId, $hash, $horas]);

    return $raw;
}
