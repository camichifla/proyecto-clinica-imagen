<?php
// ver-resultado-imagen.php — streams one resultado_imagenes file from the
// deny-all storage/resultados/ directory after verifying ownership. Never
// exposes storage/ directly; this is the only path to that image content.
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
$usuario = requerir_rol(['paciente', 'medico', 'profesional', 'administrador']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pacientes_medico.php';

const MIMES_PERMITIDOS = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];

/**
 * Generic 404 for both "does not exist" and "not yours" — never reveals
 * which case applies to a non-owner.
 */
function no_encontrado(): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No encontrado.';
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    no_encontrado();
}

$stmt = db()->prepare(
    'SELECT ri.nombre_archivo, r.paciente_id
     FROM resultado_imagenes ri
     JOIN resultados r ON r.id = ri.resultado_id
     WHERE ri.id = ?'
);
$stmt->execute([$id]);
$fila = $stmt->fetch();

if (!$fila) {
    no_encontrado();
}

// administrador ve cualquier imagen sin restriccion; los demas roles
// solo las de un paciente con el que tienen una relacion real.
$pacienteId = (int) $fila['paciente_id'];
switch ($usuario['rol']) {
    case 'paciente':
        if ($pacienteId !== $usuario['ref_id']) {
            no_encontrado();
        }
        break;
    case 'medico':
        if (!paciente_conectado_a_medico($usuario['cuenta_id'], $usuario['ref_id'], $pacienteId)) {
            no_encontrado();
        }
        break;
    case 'profesional':
        // Mismo patron de conexion que cargar-resultados.php.
        $stmtConectado = db()->prepare(
            'SELECT 1 FROM citas WHERE paciente_id = ? AND profesional_id = ? LIMIT 1'
        );
        $stmtConectado->execute([$pacienteId, $usuario['ref_id']]);
        if (!$stmtConectado->fetchColumn()) {
            no_encontrado();
        }
        break;
}

$nombreArchivo = basename($fila['nombre_archivo']);
$extension     = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));

if (!isset(MIMES_PERMITIDOS[$extension])) {
    no_encontrado();
}

$ruta = __DIR__ . '/../storage/resultados/' . $nombreArchivo;

if (!is_file($ruta)) {
    no_encontrado();
}

header('Content-Type: ' . MIMES_PERMITIDOS[$extension]);
header('Content-Length: ' . (string) filesize($ruta));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
exit;
