<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/pacientes.php';

require_post_json();

// Throttles attempts, not just successes, so probing with existing emails/CIs is capped too.
if (demasiados_eventos('registro_intento', 'ip', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 10, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'errores' => ['general' => 'Demasiados registros desde esta conexión. Probá de nuevo más tarde.']], JSON_UNESCAPED_UNICODE);
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

// Anti-enumeration: whether the email or CI already exists, or the mail cap was hit, always answers the same success.
// Per-target-email cap: a victim's inbox cannot be flooded through this endpoint.
$limitadoEmail = demasiados_eventos('registro_intento', 'email', $valores['email'], 3, 60);
registrar_evento('registro_intento', null, $valores['email']);
$exito = static function (): never {
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
};
if ($limitadoEmail) {
    $exito();
}

$stmt = $db->prepare('SELECT 1 FROM cuentas WHERE email = ?');
$stmt->execute([$valores['email']]);
if ($stmt->fetchColumn()) {
    enviar_cuenta_existente($valores['email']);
    $exito();
}

// A patient whose account was deleted gets it back by signing up again with the same CI and the email that account
// had (pacientes.email_anterior): their data, citas and clinical history stay attached. Any other email is silently
// ignored (same response), so knowing someone's CI is not enough to take over their history or to probe for it.
$stmt = $db->prepare(
    "SELECT p.id, p.email_anterior, c.id AS cuenta_id, c.email AS cuenta_email
       FROM pacientes p
       LEFT JOIN cuentas c ON c.rol = 'paciente' AND c.ref_id = p.id
      WHERE p.ci = ?"
);
$stmt->execute([$valores['ci']]);
$existente = $stmt->fetch() ?: null;
if ($existente !== null
    && ($existente['cuenta_id'] !== null
        || $existente['email_anterior'] === null
        || strcasecmp($existente['email_anterior'], $valores['email']) !== 0)) {
    if ($existente['cuenta_email'] !== null) {
        enviar_cuenta_existente($existente['cuenta_email']);
    }
    $exito();
}

try {
    $db->beginTransaction();

    if ($existente !== null) {
        $pacienteId = (int) $existente['id'];
        $db->prepare('UPDATE pacientes SET nombre = ?, apellido = ?, direccion = ?, numero = ?, email_anterior = NULL WHERE id = ?')
            ->execute([$valores['nombre'], $valores['apellido'], $valores['direccion'], $valores['numero'], $pacienteId]);
    } else {
        $stmt = $db->prepare('INSERT INTO pacientes (nombre, apellido, ci, direccion, numero) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$valores['nombre'], $valores['apellido'], $valores['ci'], $valores['direccion'], $valores['numero']]);
        $pacienteId = (int) $db->lastInsertId();
    }

    $hash = password_hash((string) $_POST['contrasena'], PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO cuentas (email, password_hash, rol, ref_id, verificado) VALUES (?, ?, 'paciente', ?, 0)");
    $stmt->execute([$valores['email'], $hash, $pacienteId]);
    $cuentaId = (int) $db->lastInsertId();

    $tokenRaw = emitir_token_verificacion($db, $cuentaId);

    vincular_citas_pendientes($db, $pacienteId, $valores['email']);

    $db->commit();

    registrar_evento('registro', 'cuenta=' . $cuentaId . ($existente !== null ? ' paciente=' . $pacienteId . ' reactivado' : ''));

    $link = APP_URL . '/verify.html?token=' . $tokenRaw;
    enviar_verificacion($valores['email'], $link);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    if ($e->getCode() === '23000') {
        $exito(); // lost a race on CI/email uniqueness: same response as the pre-checks
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'errores' => ['general' => 'No se pudo completar el registro. Intentá nuevamente.']], JSON_UNESCAPED_UNICODE);
}
