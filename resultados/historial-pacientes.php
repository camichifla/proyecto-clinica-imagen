<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
$usuario = requerir_rol(['profesional']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../includes/resultados_render.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/pacientes_profesional.php';

// Solo pacientes con los que este profesional tiene una relacion de
// atencion real (al menos una cita), nunca la lista completa.
$pacientes = pacientes_de_profesional($usuario['ref_id']);

$paciente = null;
$pacienteNoEncontrado = false;
$citas = [];
$resultados = [];
$imagenesPorResultado = [];

if (isset($_GET['paciente_id'])) {
    $pacienteId = filter_var($_GET['paciente_id'], FILTER_VALIDATE_INT);
    if ($pacienteId) {
        // Re-chequeo estricto: el paciente solicitado tiene que pertenecer
        // al propio conjunto de pacientes de este profesional, nunca
        // confiar en el paciente_id del GET a ciegas.
        $paciente = paciente_de_profesional($usuario['ref_id'], $pacienteId);
    }
    if ($paciente) {
        $stmtCitas = db()->prepare(
            'SELECT c.*, s.nombre AS sucursal_nombre
             FROM citas c
             LEFT JOIN sucursales s ON s.id = c.sucursal_id
             WHERE c.paciente_id = ? AND c.profesional_id = ?
             ORDER BY c.fecha_hora_solicitada DESC'
        );
        $stmtCitas->execute([(int) $paciente['id'], $usuario['ref_id']]);
        $citas = $stmtCitas->fetchAll();

        [$resultados, $imagenesPorResultado] = obtener_resultados_de_paciente(db(), (int) $paciente['id']);
    } else {
        $pacienteNoEncontrado = true;
    }
}
require __DIR__ . '/../views/historial-pacientes.php';
