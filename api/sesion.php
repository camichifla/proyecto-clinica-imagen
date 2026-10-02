<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/menu.php';

if (isset($_GET['opcional'])) {
    header('Content-Type: application/json; charset=utf-8');
    $usuario = usuario_actual();
    if ($usuario === null) {
        echo json_encode(['logueado' => false, 'csrf' => csrf_token()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'logueado' => true,
        'nombre'   => $usuario['nombre'],
        'rol'      => $usuario['rol'],
        'menu'     => MENU_POR_ROL[$usuario['rol']] ?? [],
        'csrf'     => csrf_token(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$usuario = requerir_rol_json(array_keys(MENU_POR_ROL));

echo json_encode([
    'nombre' => $usuario['nombre'],
    'rol'    => $usuario['rol'],
    'menu'   => MENU_POR_ROL[$usuario['rol']] ?? [],
    'csrf'   => csrf_token(),
], JSON_UNESCAPED_UNICODE);
