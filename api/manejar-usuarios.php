<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validation.php';

$usuario = requerir_rol_json(['administrador']);

const ROLES_STAFF = [
    'medico'        => 'Medico',
    'profesional'   => 'Profesional',
    'administrador' => 'Administrador',
];

const ESPECIALIZACIONES = [
    'placa'       => 'Placa',
    'radiografia' => 'Radiografia',
    'alineadores' => 'Alineadores',
];

const TABLA_POR_ROL = [
    'medico'        => 'medicos',
    'profesional'   => 'profesionales',
    'administrador' => 'administradores',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'toggle_activo') {
        $cuentaId = filter_input(INPUT_POST, 'cuenta_id', FILTER_VALIDATE_INT);

        if (!$cuentaId) {
            echo json_encode(['ok' => false, 'error' => 'Cuenta invalida.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($cuentaId === $usuario['cuenta_id']) {
            echo json_encode(['ok' => false, 'error' => 'No podes desactivar tu propia cuenta.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db   = db();
        $stmt = $db->prepare('SELECT id, rol, activo FROM cuentas WHERE id = ?');
        $stmt->execute([$cuentaId]);
        $cuentaObjetivo = $stmt->fetch();

        if (!$cuentaObjetivo) {
            echo json_encode(['ok' => false, 'error' => 'Cuenta no encontrada.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $estabaActiva = (int) $cuentaObjetivo['activo'] === 1;

        if ($estabaActiva && $cuentaObjetivo['rol'] === 'administrador') {
            $stmt = $db->prepare("SELECT COUNT(*) FROM cuentas WHERE rol = 'administrador' AND activo = 1");
            $stmt->execute();
            if ((int) $stmt->fetchColumn() <= 1) {
                echo json_encode(['ok' => false, 'error' => 'No se puede desactivar el ultimo administrador activo.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $stmt = $db->prepare('UPDATE cuentas SET activo = ? WHERE id = ?');
        $stmt->execute([$estabaActiva ? 0 : 1, $cuentaId]);

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($accion === 'asignar_paciente') {
        $medicoId   = filter_input(INPUT_POST, 'medico_id', FILTER_VALIDATE_INT);
        $pacienteId = filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT);

        if (!$medicoId || !$pacienteId) {
            echo json_encode(['ok' => false, 'error' => 'Elegi un medico y un paciente validos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db   = db();
        $stmt = $db->prepare('SELECT id FROM medicos WHERE id = ?');
        $stmt->execute([$medicoId]);
        if (!$stmt->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Elegi un medico y un paciente validos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $db->prepare('SELECT id FROM pacientes WHERE id = ?');
        $stmt->execute([$pacienteId]);
        if (!$stmt->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Elegi un medico y un paciente validos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $stmt = $db->prepare('INSERT INTO medico_paciente (medico_id, paciente_id) VALUES (?, ?)');
            $stmt->execute([$medicoId, $pacienteId]);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            $mensaje = $e->getCode() === '23000'
                ? 'Ese paciente ya esta asignado a ese medico.'
                : 'No se pudo crear la asignacion. Intenta nuevamente.';
            echo json_encode(['ok' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if ($accion === 'quitar_asignacion') {
        $medicoId   = filter_input(INPUT_POST, 'medico_id', FILTER_VALIDATE_INT);
        $pacienteId = filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT);

        if (!$medicoId || !$pacienteId) {
            echo json_encode(['ok' => false, 'error' => 'Asignacion invalida.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = db()->prepare('DELETE FROM medico_paciente WHERE medico_id = ? AND paciente_id = ?');
        $stmt->execute([$medicoId, $pacienteId]);

        echo json_encode(
            $stmt->rowCount() > 0
                ? ['ok' => true]
                : ['ok' => false, 'error' => 'La asignacion no existe.'],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    if ($accion === 'crear_staff') {
        $valores = [
            'rol'      => trim((string) ($_POST['rol'] ?? '')),
            'nombre'   => trim((string) ($_POST['nombre'] ?? '')),
            'apellido' => trim((string) ($_POST['apellido'] ?? '')),
            'email'    => trim((string) ($_POST['email'] ?? '')),
        ];

        $especializacionesEnviadas = $_POST['especializacion'] ?? [];
        if (!is_array($especializacionesEnviadas)) {
            $especializacionesEnviadas = [];
        }
        $especializaciones = array_values(array_unique(array_map('strval', $especializacionesEnviadas)));

        $errores = [];
        if (!isset(ROLES_STAFF[$valores['rol']])) {
            $errores['rol'] = 'Elegi un rol valido.';
        }

        $errores = array_merge($errores, validar(REGLAS_STAFF, $_POST));

        if ($valores['rol'] === 'profesional') {
            if (!$especializaciones) {
                $errores['especializacion'] = 'Elegi al menos una especializacion.';
            } else {
                foreach ($especializaciones as $especializacion) {
                    if (!isset(ESPECIALIZACIONES[$especializacion])) {
                        $errores['especializacion'] = 'Elegi especializaciones validas.';
                        break;
                    }
                }
            }
        } elseif ($especializaciones) {
            $errores['especializacion'] = 'La especializacion solo aplica al rol Profesional.';
        }

        if ($errores) {
            echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = db();
        try {
            $db->beginTransaction();

            if ($valores['rol'] === 'profesional') {
                $stmt = $db->prepare('INSERT INTO profesionales (nombre, apellido) VALUES (?, ?)');
            } else {
                $tabla = TABLA_POR_ROL[$valores['rol']];
                $stmt  = $db->prepare("INSERT INTO {$tabla} (nombre, apellido) VALUES (?, ?)");
            }
            $stmt->execute([$valores['nombre'], $valores['apellido']]);
            $refId = (int) $db->lastInsertId();

            if ($valores['rol'] === 'profesional') {
                $stmtEspecializacion = $db->prepare(
                    'INSERT INTO profesional_especializacion (profesional_id, especializacion) VALUES (?, ?)'
                );
                foreach ($especializaciones as $especializacion) {
                    $stmtEspecializacion->execute([$refId, $especializacion]);
                }
            }

            $hash = password_hash((string) $_POST['contrasena'], PASSWORD_BCRYPT);
            $stmt = $db->prepare('INSERT INTO cuentas (email, password_hash, rol, ref_id, verificado, activo) VALUES (?, ?, ?, ?, 1, 1)');
            $stmt->execute([$valores['email'], $hash, $valores['rol'], $refId]);

            $db->commit();

            echo json_encode(['ok' => true, 'redirect' => 'manejar-usuarios.html?staff_ok=1'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uq_cuentas_email')) {
                echo json_encode(['ok' => false, 'errores' => ['email' => 'Ya existe una cuenta registrada con ese correo.']], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['ok' => false, 'errores' => ['general' => 'No se pudo crear la cuenta. Intenta nuevamente.']], JSON_UNESCAPED_UNICODE);
            }
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Accion invalida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$medicos   = db()->query('SELECT id, nombre, apellido FROM medicos ORDER BY apellido, nombre')->fetchAll();
$pacientes = db()->query('SELECT id, nombre, apellido, ci FROM pacientes ORDER BY apellido, nombre')->fetchAll();

$asignacionesFilas = db()->query(
    "SELECT mp.medico_id, m.nombre AS medico_nombre, m.apellido AS medico_apellido,
            mp.paciente_id, p.nombre AS paciente_nombre, p.apellido AS paciente_apellido
       FROM medico_paciente mp
       JOIN medicos m ON m.id = mp.medico_id
       JOIN pacientes p ON p.id = mp.paciente_id
      ORDER BY m.apellido, p.apellido"
)->fetchAll();

$stmt = db()->query(
    "SELECT c.id AS cuenta_id, c.email, c.rol, c.verificado, c.activo,
            COALESCE(p.nombre, m.nombre, pr.nombre, a.nombre)       AS nombre,
            COALESCE(p.apellido, m.apellido, pr.apellido, a.apellido) AS apellido
       FROM cuentas c
       LEFT JOIN pacientes      p  ON c.rol = 'paciente'      AND p.id  = c.ref_id
       LEFT JOIN medicos        m  ON c.rol = 'medico'        AND m.id  = c.ref_id
       LEFT JOIN profesionales  pr ON c.rol = 'profesional'   AND pr.id = c.ref_id
       LEFT JOIN administradores a ON c.rol = 'administrador' AND a.id  = c.ref_id
      ORDER BY c.rol, apellido, nombre"
);
$cuentasFilas = $stmt->fetchAll();

$adminActivosCount = 0;
foreach ($cuentasFilas as $fila) {
    if ($fila['rol'] === 'administrador' && (int) $fila['activo'] === 1) {
        $adminActivosCount++;
    }
}

echo json_encode([
    'cuenta_actual_id'    => $usuario['cuenta_id'],
    'admin_activos_count' => $adminActivosCount,
    'cuentas'             => array_map(static function (array $fila): array {
        return [
            'cuenta_id'  => (int) $fila['cuenta_id'],
            'email'      => $fila['email'],
            'rol'        => $fila['rol'],
            'verificado' => (int) $fila['verificado'] === 1,
            'activo'     => (int) $fila['activo'] === 1,
            'nombre'     => (string) $fila['nombre'],
            'apellido'   => (string) $fila['apellido'],
        ];
    }, $cuentasFilas),
    'medicos'      => $medicos,
    'pacientes'    => $pacientes,
    'asignaciones' => $asignacionesFilas,
], JSON_UNESCAPED_UNICODE);
