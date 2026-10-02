<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

csrf_check($_POST['csrf'] ?? null);

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
