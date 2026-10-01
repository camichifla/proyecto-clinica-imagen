<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

$db = db();

$verificado    = false;
$tokenInvalido = false;
$reenviado     = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reenviar') {
    csrf_check($_POST['csrf'] ?? null);

    $email = trim((string) ($_POST['email'] ?? ''));
    if ($email !== '') {
        $stmt = $db->prepare('SELECT id FROM cuentas WHERE email = ? AND verificado = 0');
        $stmt->execute([$email]);
        $cuenta = $stmt->fetch();

        if ($cuenta) {
            $tokenRaw = emitir_token_verificacion($db, (int) $cuenta['id']);
            $link     = APP_URL . '/verify.php?token=' . $tokenRaw;
            enviar_verificacion($email, $link);
        }
        // Same response whether or not the account exists/is already
        // verified — no enumeration.
    }
    $reenviado = true;
} elseif (isset($_GET['token'])) {
    $tokenHash = hash('sha256', (string) $_GET['token']);

    $stmt = $db->prepare(
        'SELECT id, cuenta_id FROM tokens_verificacion
         WHERE token_hash = ? AND usado_en IS NULL AND expira_en > NOW()'
    );
    $stmt->execute([$tokenHash]);
    $fila = $stmt->fetch();

    if ($fila) {
        $db->beginTransaction();
        $db->prepare('UPDATE cuentas SET verificado = 1 WHERE id = ?')->execute([$fila['cuenta_id']]);
        $db->prepare('UPDATE tokens_verificacion SET usado_en = NOW() WHERE id = ?')->execute([$fila['id']]);
        $db->commit();

        header('Location: login.php?verificado=1');
        exit;
    }

    $tokenInvalido = true;
}
require __DIR__ . '/../views/verify.php';
