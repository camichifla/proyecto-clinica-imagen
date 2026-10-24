<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/resultados.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/notas.php';

$usuario = requerir_rol_json(['paciente']);
$pacienteId = (int) $usuario['ref_id'];

[$resultados, $imagenesPorResultado] = obtener_resultados_de_paciente(db(), $pacienteId);

// Read-only clinical history, scoped to the session's own patient id (never taken from the request).
try {
    $notas = array_map(static fn (array $n): array => [
        'fecha' => $n['creado_en'],
        'autor' => $n['autor'],
        'texto' => $n['texto'],
    ], notas_de_paciente(db(), $pacienteId));
} catch (RuntimeException $e) {
    $notas = null; // decryption failed (e.g. APP_KEY missing): results still load
}

$stmt = db()->prepare(
    'SELECT c.estudio, c.estado, c.fecha_hora_solicitada, s.nombre AS sucursal_nombre
       FROM citas c
       LEFT JOIN sucursales s ON s.id = c.sucursal_id
      WHERE c.paciente_id = ?
      ORDER BY c.fecha_hora_solicitada DESC'
);
$stmt->execute([$pacienteId]);
$citas = array_map(static fn (array $c): array => [
    'fecha'   => $c['fecha_hora_solicitada'],
    'estudio' => estudios_etiquetas()[$c['estudio']] ?? $c['estudio'],
    'sede'    => $c['sucursal_nombre'],
    'estado'  => $c['estado'],
    'estado_label' => ESTADOS_CITA[$c['estado']] ?? $c['estado'],
], $stmt->fetchAll());

echo json_encode([
    'resultados' => resultados_json($resultados, $imagenesPorResultado),
    'notas'      => $notas,
    'citas'      => $citas,
], JSON_UNESCAPED_UNICODE);
