<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
$usuario = requerir_rol(['medico']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../includes/resultados_render.php';
require_once __DIR__ . '/../includes/pacientes_medico.php';

$paciente = null;
$pacienteNoEncontrado = false;
$resultados = [];
$imagenesPorResultado = [];

// Solo pacientes conectados a este medico — ver includes/pacientes_medico.php.
$pacientes = pacientes_de_medico($usuario['cuenta_id'], $usuario['ref_id']);

if (isset($_GET['paciente_id'])) {
    $pacienteId = filter_var($_GET['paciente_id'], FILTER_VALIDATE_INT);
    if ($pacienteId) {
        foreach ($pacientes as $fila) {
            if ((int) $fila['id'] === $pacienteId) {
                $paciente = $fila;
                break;
            }
        }
    }
    if ($paciente) {
        [$resultados, $imagenesPorResultado] = obtener_resultados_de_paciente(db(), (int) $paciente['id']);
    } else {
        $pacienteNoEncontrado = true;
    }
}
require __DIR__ . '/../views/resultados.php';
