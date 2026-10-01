<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validation.php';

$db = db();

$tokenRaw      = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$tokenInvalido = $tokenRaw === ''; // missing token is also invalid, not a blank form
$actualizado   = false;
$error         = null;

if ($tokenRaw !== '') {
    $tokenHash = hash('sha256', $tokenRaw);
    $stmt = $db->prepare(
        'SELECT id, cuenta_id FROM tokens_reset
         WHERE token_hash = ? AND usado_en IS NULL AND expira_en > NOW()'
    );
    $stmt->execute([$tokenHash]);
    $fila = $stmt->fetch();

    if (!$fila) {
        $tokenInvalido = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check($_POST['csrf'] ?? null);

        $nueva = (string) ($_POST['contrasena'] ?? '');
        if (!preg_match(REGLAS_PACIENTE['contrasena']['pattern'], $nueva)) {
            $error = 'Formato de contrasena invalido. ' . REGLAS_PACIENTE['contrasena']['hint'];
        } else {
            // Re-validate the token right before writing, in case it was
            // consumed or expired between the GET render and this POST.
            $stmt = $db->prepare(
                'SELECT id, cuenta_id FROM tokens_reset
                 WHERE token_hash = ? AND usado_en IS NULL AND expira_en > NOW()'
            );
            $stmt->execute([$tokenHash]);
            $fila = $stmt->fetch();

            if (!$fila) {
                $tokenInvalido = true;
            } else {
                $hash = password_hash($nueva, PASSWORD_BCRYPT);

                $db->beginTransaction();
                $db->prepare('UPDATE cuentas SET password_hash = ? WHERE id = ?')->execute([$hash, $fila['cuenta_id']]);
                $db->prepare('UPDATE tokens_reset SET usado_en = NOW() WHERE id = ?')->execute([$fila['id']]);
                $db->prepare('UPDATE tokens_reset SET usado_en = NOW() WHERE cuenta_id = ? AND usado_en IS NULL')
                    ->execute([$fila['cuenta_id']]);
                $db->commit();

                // Destroy the current session if it belongs to the account
                // being reset, so it cannot keep using the old credential
                // state and must re-authenticate with the new password.
                if (isset($_SESSION['cuenta_id']) && (int) $_SESSION['cuenta_id'] === (int) $fila['cuenta_id']) {
                    $_SESSION = [];
                    session_destroy();
                }

                header('Location: login.php?restablecido=1');
                exit;
            }
        }
    }
}
require __DIR__ . '/../views/reset-confirm.php';
