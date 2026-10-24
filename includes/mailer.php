<?php
// includes/mailer.php — the only file in the project that knows how mail
// is delivered. Sends via PHPMailer/SMTP when
// config.php has complete SMTP settings; otherwise appends the link to
// storage/mail.log so the flow completes end-to-end without a mail server.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

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
        return false;
    }
}

function enviar_verificacion(string $email, string $link): bool
{
    return enviar_correo(
        $email,
        'Verifica tu cuenta - Clinica Imagen',
        "Gracias por registrarte. Confirma tu cuenta visitando el siguiente enlace:\n\n{$link}\n\nEl enlace vence en 24 horas."
    );
}

function enviar_reset(string $email, string $link): bool
{
    return enviar_correo(
        $email,
        'Restablecer contrasena - Clinica Imagen',
        "Solicitaste restablecer tu contrasena. Visita el siguiente enlace para elegir una nueva:\n\n{$link}\n\nEl enlace vence en 1 hora. Si no fuiste vos, ignora este mensaje."
    );
}

/**
 * Email del destinatario de las notificaciones de una cita: la cuenta del
 * paciente, o el email_pendiente si todavia no tiene cuenta. Null si no hay.
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

function enviar_notificacion_cita(string $email, string $estado, string $fechaHora, string $notas = ''): bool
{
    if ($estado === 'confirmada') {
        return enviar_correo(
            $email,
            'Tu cita fue confirmada - Clinica Imagen',
            "Tu cita fue confirmada para el {$fechaHora}.\n\nSi no podes asistir, contactanos para reprogramarla."
        );
    }

    if ($estado === 'cancelada') {
        $cuerpo = "Tu cita del {$fechaHora} fue cancelada.";
        if ($notas !== '') {
            $cuerpo .= "\n\nMotivo: {$notas}";
        }
        return enviar_correo($email, 'Tu cita fue cancelada - Clinica Imagen', $cuerpo);
    }

    if ($estado === 'reprogramada') {
        return enviar_correo(
            $email,
            'Tu cita fue reprogramada - Clinica Imagen',
            "Tu cita fue reprogramada para el {$fechaHora}."
        );
    }

    $cuerpo = "Tu cita solicitada para el {$fechaHora} fue rechazada.";
    if ($notas !== '') {
        $cuerpo .= "\n\nMotivo: {$notas}";
    }

    return enviar_correo($email, 'Tu cita fue rechazada - Clinica Imagen', $cuerpo);
}

/** No clinical content, just the link. */
function enviar_aviso_resultado(string $email, string $link): bool
{
    return enviar_correo(
        $email,
        'Tenes un nuevo resultado - Clinica Imagen',
        "Hay un nuevo resultado disponible en tu portal. Ingresa para verlo:\n\n{$link}"
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
function emitir_token_reset(PDO $db, int $cuentaId): string
{
    $raw  = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);

    $db->prepare('UPDATE tokens_reset SET usado_en = NOW() WHERE cuenta_id = ? AND usado_en IS NULL')
        ->execute([$cuentaId]);

    $db->prepare('INSERT INTO tokens_reset (cuenta_id, token_hash, expira_en) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))')
        ->execute([$cuentaId, $hash]);

    return $raw;
}
