<?php
// includes/auth_guard.php — redirect gates layered on top of session_bootstrap.php.
// Depends on usuario_actual() from session_bootstrap.php; require that first.

/**
 * Redirects to login.html and exits unless a session is authenticated.
 *
 * @return array{cuenta_id:int,rol:string,ref_id:int,nombre:string}
 */
function requerir_login(): array
{
    $usuario = usuario_actual();
    if ($usuario === null) {
        header('Location: ' . APP_URL . '/login.html');
        exit;
    }
    return $usuario;
}

/**
 * Redirects to login.html and exits unless the authenticated account's
 * `cuentas.rol` is one of the allowed roles. Adding a future role needs
 * no change here — callers simply pass it in $roles.
 *
 * @param string[] $roles
 * @return array{cuenta_id:int,rol:string,ref_id:int,nombre:string}
 */
function requerir_rol(array $roles): array
{
    $usuario = requerir_login();
    if (!in_array($usuario['rol'], $roles, true)) {
        header('Location: ' . APP_URL . '/login.html');
        exit;
    }
    return $usuario;
}

function requerir_rol_json(array $roles): array
{
    header('Content-Type: application/json; charset=utf-8');
    $usuario = usuario_actual();
    if ($usuario === null) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!in_array($usuario['rol'], $roles, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'No autorizado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $usuario;
}
