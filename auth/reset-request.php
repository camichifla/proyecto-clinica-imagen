<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

$enviado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);

    $email = trim((string) ($_POST['email'] ?? ''));
    if ($email !== '') {
        $db   = db();
        $stmt = $db->prepare('SELECT id FROM cuentas WHERE email = ?');
        $stmt->execute([$email]);
        $cuenta = $stmt->fetch();

        if ($cuenta) {
            // emitir_token_reset() invalidates the account's outstanding
            // token before issuing the new one (single reusable seam).
            $tokenRaw = emitir_token_reset($db, (int) $cuenta['id']);
            $link     = APP_URL . '/reset-confirm.php?token=' . $tokenRaw;
            enviar_reset($email, $link);
        }
        // No-op, no mail sent, when the email is unknown.
    }

    $enviado = true; // byte-identical confirmation regardless of outcome
}
require __DIR__ . '/../views/reset-request.php';
