<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validation.php';

header('Content-Type: application/json; charset=utf-8');

function token_invalido_json(): void
{
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El enlace de restablecimiento es invalido o vencio.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = db();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo !== 'GET' && $metodo !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tokenRaw = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
if ($tokenRaw === '') {
    token_invalido_json();
}

$tokenHash = hash('sha256', $tokenRaw);
$stmt = $db->prepare(
    'SELECT id, cuenta_id FROM tokens_reset
     WHERE token_hash = ? AND usado_en IS NULL AND expira_en > NOW()'
);
$stmt->execute([$tokenHash]);
$fila = $stmt->fetch();

if (!$fila) {
    token_invalido_json();
}

if ($metodo === 'GET') {
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

csrf_check($_POST['csrf'] ?? null);

$nueva = (string) ($_POST['contrasena'] ?? '');
if (!preg_match(REGLAS_PACIENTE['contrasena']['pattern'], $nueva)) {
    http_response_code(422);
    echo json_encode([
        'ok'      => false,
        'errores' => ['contrasena' => 'Formato de contrasena invalido. ' . REGLAS_PACIENTE['contrasena']['hint']],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->execute([$tokenHash]);
$fila = $stmt->fetch();
if (!$fila) {
    token_invalido_json();
}

$hash = password_hash($nueva, PASSWORD_BCRYPT);

$db->beginTransaction();
$db->prepare('UPDATE cuentas SET password_hash = ? WHERE id = ?')->execute([$hash, $fila['cuenta_id']]);
$db->prepare('UPDATE tokens_reset SET usado_en = NOW() WHERE id = ?')->execute([$fila['id']]);
$db->prepare('UPDATE tokens_reset SET usado_en = NOW() WHERE cuenta_id = ? AND usado_en IS NULL')
    ->execute([$fila['cuenta_id']]);
$db->commit();

if (isset($_SESSION['cuenta_id']) && (int) $_SESSION['cuenta_id'] === (int) $fila['cuenta_id']) {
    $_SESSION = [];
    session_destroy();
}

echo json_encode(['ok' => true, 'redirect' => 'login.html?restablecido=1'], JSON_UNESCAPED_UNICODE);
