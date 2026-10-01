<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
$usuario = requerir_rol(['paciente']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../includes/resultados_render.php';

[$resultados, $imagenesPorResultado] = obtener_resultados_de_paciente(db(), $usuario['ref_id']);
require __DIR__ . '/../views/ver-resultados.php';
