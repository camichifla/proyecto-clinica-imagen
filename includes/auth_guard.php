<?php
// includes/auth_guard.php — JSON role gate layered on top of session_bootstrap.php.
// Depends on usuario_actual() from session_bootstrap.php; require that first.

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
