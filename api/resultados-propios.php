<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/resultados.php';

$usuario = requerir_rol_json(['paciente']);

[$resultados, $imagenesPorResultado] = obtener_resultados_de_paciente(db(), $usuario['ref_id']);

echo json_encode(['resultados' => resultados_json($resultados, $imagenesPorResultado)], JSON_UNESCAPED_UNICODE);
