<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/pacientes.php';

require_post_json();

if (demasiados_eventos('registro', 'ip', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 10, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'errores' => ['general' => 'Demasiados registros desde esta conexion. Proba de nuevo mas tarde.']], JSON_UNESCAPED_UNICODE);
    exit;
}

$campos  = ['nombre', 'apellido', 'ci', 'direccion', 'numero', 'email'];
$valores = array_map(static fn (string $campo): string => trim((string) ($_POST[$campo] ?? '')), array_combine($campos, $campos));

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

    vincular_citas_pendientes($db, $pacienteId, $valores['email']);

    $db->commit();

    registrar_evento('registro', 'cuenta=' . $cuentaId);

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
