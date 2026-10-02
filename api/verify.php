<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reenviar') {
    csrf_check($_POST['csrf'] ?? null);

    $email = trim((string) ($_POST['email'] ?? ''));
    if ($email !== '') {
        $stmt = $db->prepare('SELECT id FROM cuentas WHERE email = ? AND verificado = 0');
        $stmt->execute([$email]);
        $cuenta = $stmt->fetch();

        if ($cuenta) {
            $tokenRaw = emitir_token_verificacion($db, (int) $cuenta['id']);
            $link     = APP_URL . '/verify.html?token=' . $tokenRaw;
            enviar_verificacion($email, $link);
        }

    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
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

        echo json_encode(['ok' => true, 'redirect' => 'login.html?verificado=1'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El enlace de verificacion es invalido o vencio.'], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Metodo no permitido.'], JSON_UNESCAPED_UNICODE);
