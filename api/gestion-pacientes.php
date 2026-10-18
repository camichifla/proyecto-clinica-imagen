<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/pacientes.php';

requerir_rol_json(['administrador', 'recepcionista']);

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
        return ['ci' => 'Ya existe un paciente con esa cedula.'];
    }
    if (str_contains($e->getMessage(), 'uq_cuentas_email')) {
        return ['email' => 'Ya existe una cuenta registrada con ese correo.'];
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $accion = $_POST['accion'] ?? '';
    if (!in_array($accion, ['crear', 'guardar'], true)) {
        respuesta_pacientes(400, ['ok' => false, 'error' => 'Accion invalida.']);
    }

    $valores = [];
    foreach (CAMPOS_PACIENTE as $campo) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }
    $reglas = array_intersect_key(REGLAS_PACIENTE, $valores);
    if ($accion === 'crear') {
        $valores['email'] = trim((string) ($_POST['email'] ?? ''));
        $reglas['email']  = REGLAS_PACIENTE['email'];
    }
    $errores = validar($reglas, $_POST);
    if ($errores) {
        respuesta_pacientes(422, ['ok' => false, 'errores' => $errores]);
    }

    $db = db();

    if ($accion === 'guardar') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            respuesta_pacientes(400, ['ok' => false, 'error' => 'Paciente invalido.']);
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

    // Alta en mostrador: cuenta verificada con clave inutilizable; el paciente elige la suya con el enlace de reset.
    try {
        $db->beginTransaction();

        $db->prepare('INSERT INTO pacientes (nombre, apellido, ci, direccion, numero) VALUES (?, ?, ?, ?, ?)')
            ->execute([$valores['nombre'], $valores['apellido'], $valores['ci'], $valores['direccion'], $valores['numero']]);
        $pacienteId = (int) $db->lastInsertId();

        $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
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
            respuesta_pacientes(500, ['ok' => false, 'error' => 'No se pudo registrar al paciente. Intenta nuevamente.']);
        }
        respuesta_pacientes(422, ['ok' => false, 'errores' => $unico]);
    }

    registrar_evento('paciente_alta', 'paciente=' . $pacienteId . ' cuenta=' . $cuentaId);

    $tokenRaw = emitir_token_reset($db, $cuentaId);
    enviar_reset($valores['email'], APP_URL . '/reset-confirm.html?token=' . $tokenRaw);

    echo json_encode(['ok' => true, 'id' => $pacienteId], JSON_UNESCAPED_UNICODE);
    exit;
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
    "SELECT p.id, p.nombre, p.apellido, p.ci, p.direccion, p.numero, c.email, c.verificado, c.activo
       FROM pacientes p
       LEFT JOIN cuentas c ON c.rol = 'paciente' AND c.ref_id = p.id
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
        'verificado' => $p['verificado'] !== null && (int) $p['verificado'] === 1,
        'activo'     => $p['activo'] !== null && (int) $p['activo'] === 1,
    ], array_slice($filas, 0, MAX_FILAS_PACIENTES)),
], JSON_UNESCAPED_UNICODE);
