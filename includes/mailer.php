<?php
// includes/mailer.php — the only file in the project that knows how mail
// is delivered (design Decision 2). Sends via PHPMailer/SMTP when
// config.php has complete SMTP settings; otherwise appends the link to
// storage/mail.log so the flow completes end-to-end without a mail server.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * True when config.php defines a usable SMTP host + credentials.
 */
function smtp_configurado(): bool
{
    return SMTP_HOST !== '' && SMTP_USER !== '' && SMTP_PASS !== '';
}

/**
 * Sends (or dev-logs) an arbitrary email. Returns true on success in
 * both modes: the dev fallback always "succeeds" so the calling flow
 * (registration, reset) completes without a mail server.
 */
function enviar_correo(string $destinatario, string $asunto, string $cuerpo): bool
{
    if (!smtp_configurado()) {
        $linea = sprintf(
            "[%s] to=%s subject=%s\n%s\n---\n",
            date('Y-m-d H:i:s'),
            $destinatario,
            $asunto,
            $cuerpo
        );
        $ok = @file_put_contents(__DIR__ . '/../storage/mail.log', $linea, FILE_APPEND | LOCK_EX);
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

/**
 * Sends the account-verification email/link.
 */
function enviar_verificacion(string $email, string $link): bool
{
    return enviar_correo(
        $email,
        'Verifica tu cuenta - Clinica Imagen',
        "Gracias por registrarte. Confirma tu cuenta visitando el siguiente enlace:\n\n{$link}\n\nEl enlace vence en 24 horas."
    );
}

/**
 * Sends the password-reset email/link.
 */
function enviar_reset(string $email, string $link): bool
{
    return enviar_correo(
        $email,
        'Restablecer contrasena - Clinica Imagen',
        "Solicitaste restablecer tu contrasena. Visita el siguiente enlace para elegir una nueva:\n\n{$link}\n\nEl enlace vence en 1 hora. Si no fuiste vos, ignora este mensaje."
    );
}

/**
 * Sends the cita confirmation/rejection notification to the patient.
 */
function enviar_notificacion_cita(string $email, string $estado, string $fechaHora, string $notas = ''): bool
{
    if ($estado === 'confirmada') {
        return enviar_correo(
            $email,
            'Tu cita fue confirmada - Clinica Imagen',
            "Tu cita fue confirmada para el {$fechaHora}.\n\nSi no podes asistir, contactanos para reprogramarla."
        );
    }

    $cuerpo = "Tu cita solicitada para el {$fechaHora} fue rechazada.";
    if ($notas !== '') {
        $cuerpo .= "\n\nMotivo: {$notas}";
    }

    return enviar_correo($email, 'Tu cita fue rechazada - Clinica Imagen', $cuerpo);
}

/**
 * Issues a fresh verification token for $cuentaId: generates the raw
 * token, stores only its sha256 hash, and invalidates any prior
 * outstanding (unused, unexpired) verification token for that account.
 * This is the single reusable token-issue seam shared by registration
 * and verify-resend.
 */
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

/**
 * Issues a fresh password-reset token for $cuentaId, invalidating any
 * prior outstanding reset token. Same shape as emitir_token_verificacion,
 * separate table/lifetime/scope. Shared by reset-request and its resend
 * (a resubmission of the same form).
 */
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
