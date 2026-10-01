<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
$usuario = requerir_rol(['administrador']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../includes/resultados_render.php';

$paciente = null;
$pacienteNoEncontrado = false;
$resultados = [];
$imagenesPorResultado = [];

if (isset($_GET['paciente_id'])) {
    $pacienteId = filter_var($_GET['paciente_id'], FILTER_VALIDATE_INT);
    if ($pacienteId) {
        $stmt = db()->prepare('SELECT id, nombre, apellido, ci FROM pacientes WHERE id = ?');
        $stmt->execute([$pacienteId]);
        $paciente = $stmt->fetch();
    }
    if ($paciente) {
        [$resultados, $imagenesPorResultado] = obtener_resultados_de_paciente(db(), (int) $paciente['id']);
    } else {
        $pacienteNoEncontrado = true;
    }
}

$pacientes = db()->query('SELECT id, nombre, apellido, ci FROM pacientes ORDER BY apellido, nombre')->fetchAll();
require __DIR__ . '/../views/buscar-resultados.php';
