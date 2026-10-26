<?php
// POST only: appends one clinical note for a patient the medico/profesional can
// already see, or (accion=borrar) deletes one, but only the note's own author.
// Reads go through resultados-paciente.php.
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notas.php';
require_once __DIR__ . '/../includes/pacientes_medico.php';
require_once __DIR__ . '/../includes/pacientes_profesional.php';

$usuario = requerir_rol_json(['medico', 'profesional']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}
csrf_check($_POST['csrf'] ?? null);

// Delete: only the author of the note may remove it (hard delete).
if (($_POST['accion'] ?? '') === 'borrar') {
    $notaId = filter_var($_POST['nota_id'] ?? '', FILTER_VALIDATE_INT);
    if (!$notaId) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Nota inválida.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt = db()->prepare('DELETE FROM notas_clinicas WHERE id = ? AND cuenta_id = ?');
    $stmt->execute([$notaId, $usuario['cuenta_id']]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'No se encontró la nota o no es tuya.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    registrar_evento('nota_borrar', 'nota=' . $notaId);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$pacienteId = filter_var($_POST['paciente_id'] ?? '', FILTER_VALIDATE_INT);
$permitido = $pacienteId && ($usuario['rol'] === 'medico'
    ? paciente_conectado_a_medico($usuario['cuenta_id'], $usuario['ref_id'], $pacienteId)
    : (bool) paciente_de_profesional($usuario['ref_id'], $pacienteId));
if (!$permitido) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Paciente no encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$texto = trim((string) ($_POST['texto'] ?? ''));
if ($texto === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errores' => ['texto' => 'Escribí la nota.']], JSON_UNESCAPED_UNICODE);
    exit;
}
if (mb_strlen($texto) > NOTA_MAX) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errores' => ['texto' => 'La nota no puede superar ' . NOTA_MAX . ' caracteres.']], JSON_UNESCAPED_UNICODE);
    exit;
}

db()->prepare('INSERT INTO notas_clinicas (paciente_id, cuenta_id, texto) VALUES (?, ?, ?)')
    ->execute([$pacienteId, $usuario['cuenta_id'], cifrar($texto)]);
$notaId = (int) db()->lastInsertId();

registrar_evento('nota_alta', 'paciente=' . $pacienteId . ' nota=' . $notaId);
echo json_encode(['ok' => true, 'id' => $notaId], JSON_UNESCAPED_UNICODE);
