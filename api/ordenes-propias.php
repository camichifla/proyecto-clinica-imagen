<?php
// Read-only: the logged-in patient's own orders. The patient id comes from
// the session, never from the request.
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ordenes.php';

$usuario = requerir_rol_json(['paciente']);

echo json_encode(['ordenes' => ordenes_de_paciente(db(), $usuario['ref_id'])], JSON_UNESCAPED_UNICODE);
