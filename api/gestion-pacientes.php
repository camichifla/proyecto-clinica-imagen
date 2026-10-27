<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/pacientes.php';

$usuario = requerir_rol_json(['administrador', 'recepcionista']);
// A receptionist may only create patient accounts (same fields as the public sign-up): no listing, no edits.
$soloAlta = $usuario['rol'] === 'recepcionista';

const MAX_FILAS_PACIENTES = 50;
const CAMPOS_PACIENTE     = ['nombre', 'apellido', 'ci', 'direccion', 'numero'];

function respuesta_pacientes(int $codigo, array $cuerpo): never
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

function error_unico_paciente(PDOException $e): ?array
{
    if ($e->getCode() !== '23000') {
        return null;
    }
    if (str_contains($e->getMessage(), 'uq_pacientes_ci')) {
        return ['ci' => 'Ya existe un paciente con esa cédula.'];
    }
    if (str_contains($e->getMessage(), 'uq_cuentas_email')) {
        return ['email' => 'Ya existe una cuenta registrada con ese correo.'];
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $accion = $_POST['accion'] ?? '';
    if ($soloAlta && $accion !== 'crear') {
        respuesta_pacientes(403, ['ok' => false, 'error' => 'No tenés permiso para esta acción.']);
    }
    if (!in_array($accion, ['crear', 'guardar', 'toggle_activo', 'borrar_cuenta'], true)) {
        respuesta_pacientes(400, ['ok' => false, 'error' => 'Acción inválida.']);
    }

    // Baja/alta of the patient's login account; the patient record and history are kept.
    if ($accion === 'toggle_activo') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $stmt = db()->prepare("SELECT id, activo FROM cuentas WHERE rol = 'paciente' AND ref_id = ?");
        $stmt->execute([$id ?: 0]);
        $cuenta = $stmt->fetch();
        if (!$cuenta) {
            respuesta_pacientes(404, ['ok' => false, 'error' => 'El paciente no tiene cuenta.']);
        }
        $nuevo = (int) $cuenta['activo'] === 1 ? 0 : 1;
        db()->prepare('UPDATE cuentas SET activo = ? WHERE id = ?')->execute([$nuevo, $cuenta['id']]);
        registrar_evento('cuenta_activo', 'cuenta=' . $cuenta['id'] . ' paciente=' . $id . ' activo=' . $nuevo);
        echo json_encode(['ok' => true, 'activo' => $nuevo === 1], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Deletes only the login account, and only once it was deactivated ("dada de baja"). The patient record, citas,
    // results and clinical notes stay; the email is kept so signing up again with the same CI and email brings the
    // patient back (register.php). Nothing else references a patient's account except its tokens, which cascade.
    if ($accion === 'borrar_cuenta') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $stmt = db()->prepare("SELECT id, email, activo FROM cuentas WHERE rol = 'paciente' AND ref_id = ?");
        $stmt->execute([$id ?: 0]);
        $cuenta = $stmt->fetch();
        if (!$cuenta) {
            respuesta_pacientes(404, ['ok' => false, 'error' => 'El paciente no tiene cuenta.']);
        }
        if ((int) $cuenta['activo'] === 1) {
            respuesta_pacientes(409, ['ok' => false, 'error' => 'Primero hay que dar de baja la cuenta.']);
        }
        $db = db();
        $db->beginTransaction();
        $db->prepare('UPDATE pacientes SET email_anterior = ? WHERE id = ?')->execute([$cuenta['email'], $id]);
        $db->prepare("DELETE FROM cuentas WHERE id = ? AND rol = 'paciente' AND activo = 0")->execute([$cuenta['id']]);
        $db->commit();
        registrar_evento('cuenta_borrada', 'cuenta=' . $cuenta['id'] . ' paciente=' . $id);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $valores = [];
    foreach (CAMPOS_PACIENTE as $campo) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }
    $reglas = array_intersect_key(REGLAS_PACIENTE, $valores);
    if ($accion === 'crear') {
        $valores['email'] = trim((string) ($_POST['email'] ?? ''));
        $reglas['email']  = REGLAS_PACIENTE['email'];
        if ($soloAlta) {
            $reglas['contrasena'] = REGLAS_PACIENTE['contrasena'];
        }
    }
    $errores = validar($reglas, $_POST);
    if ($errores) {
        respuesta_pacientes(422, ['ok' => false, 'errores' => $errores]);
    }

    $db = db();

    if ($accion === 'guardar') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            respuesta_pacientes(400, ['ok' => false, 'error' => 'Paciente inválido.']);
        }
        $stmt = $db->prepare('SELECT 1 FROM pacientes WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) {
            respuesta_pacientes(404, ['ok' => false, 'error' => 'Paciente no encontrado.']);
        }
        try {
            $db->prepare('UPDATE pacientes SET nombre = ?, apellido = ?, ci = ?, direccion = ?, numero = ? WHERE id = ?')
                ->execute([...array_values($valores), $id]);
        } catch (PDOException $e) {
            $unico = error_unico_paciente($e);
            if ($unico === null) {
                throw $e;
            }
            respuesta_pacientes(422, ['ok' => false, 'errores' => $unico]);
        }
        registrar_evento('paciente_cambio', 'paciente=' . $id);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Walk-in registration: verified account with an unusable password; the patient picks theirs via the reset link.
    try {
        $db->beginTransaction();

        $db->prepare('INSERT INTO pacientes (nombre, apellido, ci, direccion, numero) VALUES (?, ?, ?, ?, ?)')
            ->execute([$valores['nombre'], $valores['apellido'], $valores['ci'], $valores['direccion'], $valores['numero']]);
        $pacienteId = (int) $db->lastInsertId();

        $hash = password_hash($soloAlta ? (string) $_POST['contrasena'] : bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
        $db->prepare("INSERT INTO cuentas (email, password_hash, rol, ref_id, verificado, activo) VALUES (?, ?, 'paciente', ?, 1, 1)")
            ->execute([$valores['email'], $hash, $pacienteId]);
        $cuentaId = (int) $db->lastInsertId();

        vincular_citas_pendientes($db, $pacienteId, $valores['email']);

        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $unico = error_unico_paciente($e);
        if ($unico === null) {
            error_log('gestion-pacientes: ' . $e->getMessage());
            respuesta_pacientes(500, ['ok' => false, 'error' => 'No se pudo registrar al paciente. Intentá nuevamente.']);
        }
        respuesta_pacientes(422, ['ok' => false, 'errores' => $unico]);
    }

    registrar_evento('paciente_alta', 'paciente=' . $pacienteId . ' cuenta=' . $cuentaId);

    // The receptionist set the password at the desk; the admin flow lets the patient pick it from an emailed link.
    if (!$soloAlta) {
        $tokenRaw = emitir_token_reset($db, $cuentaId, 24);
        enviar_bienvenida_paciente($valores['email'], APP_URL . '/reset-confirm.html?token=' . $tokenRaw);
    }

    echo json_encode(['ok' => true, 'id' => $pacienteId], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($soloAlta) {
    respuesta_pacientes(403, ['ok' => false, 'error' => 'No tenés permiso para listar pacientes.']);
}

$where   = '1';
$params  = [];
$palabras = preg_split('/\s+/', trim((string) ($_GET['q'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
foreach (array_slice($palabras, 0, 5) as $palabra) {
    $where   .= ' AND (p.nombre LIKE ? OR p.apellido LIKE ? OR p.ci LIKE ?)';
    $like     = '%' . addcslashes($palabra, '\\%_') . '%';
    array_push($params, $like, $like, $like);
}

$stmt = db()->prepare(
    "SELECT p.id, p.nombre, p.apellido, p.ci, p.direccion, p.numero, c.email, c.verificado, c.activo,
            (SELECT COUNT(*) FROM resultados r WHERE r.paciente_id = p.id) AS total_resultados
       FROM pacientes p
       JOIN cuentas c ON c.rol = 'paciente' AND c.ref_id = p.id -- patients whose account was deleted are not listed
      WHERE {$where}
      ORDER BY p.apellido, p.nombre
      LIMIT " . (MAX_FILAS_PACIENTES + 1)
);
$stmt->execute($params);
$filas = $stmt->fetchAll();

echo json_encode([
    'truncado'  => count($filas) > MAX_FILAS_PACIENTES,
    'max'       => MAX_FILAS_PACIENTES,
    'reglas'    => array_map(static fn (string $c): array => ['campo' => $c, 'label' => REGLAS_PACIENTE[$c]['label'], 'hint' => REGLAS_PACIENTE[$c]['hint']], array_merge(CAMPOS_PACIENTE, ['email'])),
    'pacientes' => array_map(static fn (array $p): array => [
        'id'         => (int) $p['id'],
        'nombre'     => $p['nombre'],
        'apellido'   => $p['apellido'],
        'ci'         => $p['ci'],
        'direccion'  => $p['direccion'],
        'numero'     => $p['numero'],
        'email'      => $p['email'],
        'resultados' => (int) $p['total_resultados'],
        'verificado' => $p['verificado'] !== null && (int) $p['verificado'] === 1,
        'activo'     => $p['activo'] !== null && (int) $p['activo'] === 1,
    ], array_slice($filas, 0, MAX_FILAS_PACIENTES)),
], JSON_UNESCAPED_UNICODE);
