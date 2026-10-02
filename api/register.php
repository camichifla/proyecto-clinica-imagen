<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

csrf_check($_POST['csrf'] ?? null);

$valores = ['nombre' => '', 'apellido' => '', 'ci' => '', 'direccion' => '', 'numero' => '', 'email' => ''];
foreach ($valores as $campo => $_valor) {
    $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
}

$errores = validar(REGLAS_PACIENTE, $_POST);

if ($errores) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = db();
try {
    $db->beginTransaction();

    $stmt = $db->prepare('INSERT INTO pacientes (nombre, apellido, ci, direccion, numero) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$valores['nombre'], $valores['apellido'], $valores['ci'], $valores['direccion'], $valores['numero']]);
    $pacienteId = (int) $db->lastInsertId();

    $hash = password_hash((string) $_POST['contrasena'], PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO cuentas (email, password_hash, rol, ref_id, verificado) VALUES (?, ?, 'paciente', ?, 0)");
    $stmt->execute([$valores['email'], $hash, $pacienteId]);
    $cuentaId = (int) $db->lastInsertId();

    $tokenRaw = emitir_token_verificacion($db, $cuentaId);

    $stmt = $db->prepare('UPDATE citas SET paciente_id = ?, email_pendiente = NULL, nombre_pendiente = NULL, apellido_pendiente = NULL WHERE email_pendiente = ? AND paciente_id IS NULL');
    $stmt->execute([$pacienteId, $valores['email']]);

    $stmt = $db->prepare(
        'UPDATE ordenes o JOIN citas c ON c.id = o.cita_id
            SET o.paciente_id = ?
          WHERE c.paciente_id = ? AND o.paciente_id IS NULL'
    );
    $stmt->execute([$pacienteId, $pacienteId]);

    $db->commit();

    $link = APP_URL . '/verify.html?token=' . $tokenRaw;
    enviar_verificacion($valores['email'], $link);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uq_pacientes_ci')) {
        $errores['ci'] = 'Ya existe una cuenta registrada con esa cedula.';
    } elseif ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uq_cuentas_email')) {
        $errores['email'] = 'Ya existe una cuenta registrada con ese correo.';
    } else {
        $errores['general'] = 'No se pudo completar el registro. Intenta nuevamente.';
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
}
