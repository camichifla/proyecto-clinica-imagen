<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/mailer.php';

$usuario = requerir_rol_json(['paciente', 'medico', 'profesional', 'administrador', 'recepcionista']);

const CAMPOS_POR_ROL = [
    'paciente'      => ['nombre' => 'text', 'apellido' => 'text', 'direccion' => 'text', 'numero' => 'tel'],
    'medico'        => ['nombre' => 'text', 'apellido' => 'text', 'ci' => 'text'],
    'profesional'   => ['nombre' => 'text', 'apellido' => 'text', 'ci' => 'text'],
    'administrador' => ['nombre' => 'text', 'apellido' => 'text', 'ci' => 'text'],
    'recepcionista' => ['nombre' => 'text', 'apellido' => 'text', 'ci' => 'text'],
];

$campos = CAMPOS_POR_ROL[$usuario['rol']] ?? null;
if ($campos === null) {
    http_response_code(404);
    echo json_encode(['error' => 'No hay campos editables para este rol.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tabla = TABLA_POR_ROL[$usuario['rol']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);

    $accion = (string) ($_POST['accion'] ?? '');
    if ($accion === 'email' || $accion === 'contrasena') {
        $db   = db();
        $stmt = $db->prepare('SELECT id, email, password_hash FROM cuentas WHERE id = ?');
        $stmt->execute([$usuario['cuenta_id']]);
        $cuenta = $stmt->fetch();

        // Caps guessing the current password from a hijacked session: 5 misses per account every 15 minutes.
        if (demasiados_eventos('cuenta_password_fallo', 'email', $cuenta['email'], 5, 15)) {
            http_response_code(429);
            echo json_encode(['ok' => false, 'errores' => ['contrasena_actual' => 'Demasiados intentos fallidos. Probá de nuevo en 15 minutos.']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!password_verify((string) ($_POST['contrasena_actual'] ?? ''), $cuenta['password_hash'])) {
            registrar_evento('cuenta_password_fallo', 'cuenta=' . $cuenta['id'] . ' accion=' . $accion, $cuenta['email']);
            http_response_code(422);
            echo json_encode(['ok' => false, 'errores' => ['contrasena_actual' => 'La contraseña actual es incorrecta.']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($accion === 'contrasena') {
            $errores = validar(['contrasena' => REGLAS_PACIENTE['contrasena']], $_POST);
            if ($errores) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $db->prepare('UPDATE cuentas SET password_hash = ? WHERE id = ?')
                ->execute([password_hash((string) $_POST['contrasena'], PASSWORD_BCRYPT), $cuenta['id']]);
            session_regenerate_id(true);
            registrar_evento('cuenta_password_cambio', 'cuenta=' . $cuenta['id']);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $email   = trim((string) ($_POST['email'] ?? ''));
        $errores = validar(['email' => REGLAS_PACIENTE['email']], ['email' => $email]);
        if ($errores) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (email_en_uso($db, $email, (int) $cuenta['id'])) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'errores' => ['email' => 'Ya existe una cuenta registrada con ese correo.']], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            $db->beginTransaction();
            $tokenEmail = aplicar_cambio_email($db, $cuenta, $email);
            $db->commit();
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uq_cuentas_email')) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'errores' => ['email' => 'Ya existe una cuenta registrada con ese correo.']], JSON_UNESCAPED_UNICODE);
                exit;
            }
            throw $e;
        }
        registrar_evento('cuenta_email_cambio', 'cuenta=' . $cuenta['id']);
        if ($tokenEmail !== null) {
            enviar_confirmacion_email($email, $cuenta['email'], APP_URL . '/verify.html?token=' . $tokenEmail);
        }
        echo json_encode(['ok' => true, 'email_pendiente' => $tokenEmail !== null ? $email : null], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $valores = [];
    foreach (array_keys($campos) as $campo) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    $reglas  = array_intersect_key(REGLAS_PACIENTE, $valores);
    $errores = validar($reglas, $_POST);

    if ($errores) {
        echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $asignaciones = implode(', ', array_map(static fn ($campo) => "{$campo} = ?", array_keys($valores)));
    $stmt = db()->prepare("UPDATE {$tabla} SET {$asignaciones} WHERE id = ?");
    try {
        $stmt->execute([...array_values($valores), $usuario['ref_id']]);
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') {
            throw $e;
        }
        echo json_encode(['ok' => false, 'errores' => ['ci' => 'Ya existe una cuenta registrada con esa cédula.']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    registrar_evento('perfil_cambio', 'cuenta=' . $usuario['cuenta_id']);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$columnas = implode(', ', array_keys($campos));
$stmt = db()->prepare("SELECT {$columnas} FROM {$tabla} WHERE id = ?");
$stmt->execute([$usuario['ref_id']]);
$fila = $stmt->fetch() ?: [];

limpiar_emails_pendientes(db());
$stmt = db()->prepare('SELECT email, email_nuevo FROM cuentas WHERE id = ?');
$stmt->execute([$usuario['cuenta_id']]);
$cuentaFila = $stmt->fetch();

echo json_encode([
    'email'  => (string) $cuentaFila['email'],
    'email_pendiente' => $cuentaFila['email_nuevo'],
    'campos' => array_map(static function (string $campo, string $tipo) use ($fila): array {
        return [
            'campo' => $campo,
            'tipo'  => $tipo,
            'label' => REGLAS_PACIENTE[$campo]['label'],
            'valor' => $fila[$campo] ?? '',
        ];
    }, array_keys($campos), array_values($campos)),
], JSON_UNESCAPED_UNICODE);
