<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pacientes_medico.php';
require_once __DIR__ . '/../includes/pacientes_profesional.php';

$usuario = requerir_rol_json(['administrador', 'medico', 'profesional']);

switch ($usuario['rol']) {
    case 'administrador':
        $pacientes = db()->query('SELECT id, nombre, apellido, ci FROM pacientes ORDER BY apellido, nombre')->fetchAll();
        break;
    case 'medico':

        $pacientes = pacientes_de_medico($usuario['cuenta_id'], $usuario['ref_id']);
        break;
    case 'profesional':

        $pacientes = pacientes_de_profesional($usuario['ref_id']);
        break;
}

echo json_encode(['pacientes' => $pacientes], JSON_UNESCAPED_UNICODE);
