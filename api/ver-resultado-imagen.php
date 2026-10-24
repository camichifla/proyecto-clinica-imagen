<?php
// Only path to the deny-all storage/resultados/: streams an image (?id=) or PDF (?informe=) after verifying ownership.
require_once __DIR__ . '/../includes/session_bootstrap.php';
$usuario = usuario_actual();
if ($usuario === null || !in_array($usuario['rol'], ['paciente', 'medico', 'profesional', 'administrador'], true)) {
    header('Location: ' . APP_URL . '/login.html');
    exit;
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pacientes_medico.php';

const MIMES_PERMITIDOS = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'pdf'  => 'application/pdf',
];

/** Same 404 for "does not exist" and "not yours": never reveals which. */
function no_encontrado(): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No encontrado.';
    exit;
}

$esInforme = isset($_GET['informe']);
$id = filter_input(INPUT_GET, $esInforme ? 'informe' : 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    no_encontrado();
}

if ($esInforme) {
    $stmt = db()->prepare('SELECT informe_archivo AS nombre_archivo, paciente_id FROM resultados WHERE id = ? AND informe_archivo IS NOT NULL');
} else {
    $stmt = db()->prepare(
        'SELECT ri.nombre_archivo, r.paciente_id
         FROM resultado_imagenes ri
         JOIN resultados r ON r.id = ri.resultado_id
         WHERE ri.id = ?'
    );
}
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

// El informe solo se sirve si el archivo guardado es un PDF y las imagenes
// nunca como PDF: el tipo sale de la extension generada por el servidor.
if (($extension === 'pdf') !== $esInforme) {
    no_encontrado();
}

registrar_evento('resultado_ver', ($esInforme ? 'informe=' : 'imagen=') . $id);

header('Content-Type: ' . MIMES_PERMITIDOS[$extension]);
if ($esInforme) {
    header('Content-Disposition: inline; filename="informe.pdf"');
} else {
    // Sandbox CSP is image-only: it breaks Chrome's PDF viewer.
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
}
header('Content-Length: ' . (string) filesize($ruta));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
exit;
