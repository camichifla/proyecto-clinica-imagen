<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/recepcion.php';

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
if ($usuario['rol'] === 'recepcionista' && sucursal_a_cargo($usuario) === null) {
    $sesion['sinSede'] = true;
}
echo json_encode($opcional ? ['logueado' => true] + $sesion : $sesion, JSON_UNESCAPED_UNICODE);
