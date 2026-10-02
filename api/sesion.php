<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/menu.php';

$opcional = isset($_GET['opcional']);
if ($opcional) {
    header('Content-Type: application/json; charset=utf-8');
    $usuario = usuario_actual();
    if ($usuario === null) {
        echo json_encode(['logueado' => false, 'csrf' => csrf_token()], JSON_UNESCAPED_UNICODE);
        exit;
    }
} else {
    $usuario = requerir_rol_json(array_keys(MENU_POR_ROL));
}

$sesion = [
    'nombre' => $usuario['nombre'],
    'rol'    => $usuario['rol'],
    'menu'   => MENU_POR_ROL[$usuario['rol']] ?? [],
    'csrf'   => csrf_token(),
];
echo json_encode($opcional ? ['logueado' => true] + $sesion : $sesion, JSON_UNESCAPED_UNICODE);
