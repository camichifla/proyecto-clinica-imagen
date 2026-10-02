<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

require_post_json();

$email = trim((string) ($_POST['email'] ?? ''));
if ($email !== '') {
    $db   = db();
    $stmt = $db->prepare('SELECT id FROM cuentas WHERE email = ?');
    $stmt->execute([$email]);
    $cuenta = $stmt->fetch();

    if ($cuenta) {

        $tokenRaw = emitir_token_reset($db, (int) $cuenta['id']);
        $link     = APP_URL . '/reset-confirm.html?token=' . $tokenRaw;
        enviar_reset($email, $link);
    }

}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
