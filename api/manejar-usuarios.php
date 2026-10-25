<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/estudios.php';

$usuario = requerir_rol_json(['administrador']);

const ROLES_STAFF = [
    'medico'        => 'Médico',
    'profesional'   => 'Profesional',
    'administrador' => 'Administrador',
    'recepcionista' => 'Recepcionista',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'toggle_activo') {
        $cuentaId = filter_input(INPUT_POST, 'cuenta_id', FILTER_VALIDATE_INT);

        if (!$cuentaId) {
            echo json_encode(['ok' => false, 'error' => 'Cuenta inválida.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($cuentaId === $usuario['cuenta_id']) {
            echo json_encode(['ok' => false, 'error' => 'No podés desactivar tu propia cuenta.'], JSON_UNESCAPED_UNICODE);
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

        // Locking read inside the transaction so two simultaneous toggles cannot leave zero active admins.
        $db->beginTransaction();
        if ($estabaActiva && $cuentaObjetivo['rol'] === 'administrador') {
            $activos = (int) $db->query("SELECT COUNT(*) FROM cuentas WHERE rol = 'administrador' AND activo = 1 FOR UPDATE")->fetchColumn();
            if ($activos <= 1) {
                $db->rollBack();
                echo json_encode(['ok' => false, 'error' => 'No se puede desactivar el último administrador activo.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $stmt = $db->prepare('UPDATE cuentas SET activo = ? WHERE id = ?');
        $stmt->execute([$estabaActiva ? 0 : 1, $cuentaId]);
        $db->commit();

        registrar_evento('cuenta_activo', 'cuenta=' . $cuentaId);

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($accion === 'asignar_paciente') {
        $medicoId   = filter_input(INPUT_POST, 'medico_id', FILTER_VALIDATE_INT);
        $pacienteId = filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT);

        if (!$medicoId || !$pacienteId) {
            echo json_encode(['ok' => false, 'error' => 'Elegí un médico y un paciente validos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db   = db();
        $stmt = $db->prepare('SELECT id FROM medicos WHERE id = ?');
        $stmt->execute([$medicoId]);
        if (!$stmt->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Elegí un médico y un paciente validos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $db->prepare('SELECT id FROM pacientes WHERE id = ?');
        $stmt->execute([$pacienteId]);
        if (!$stmt->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Elegí un médico y un paciente validos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $stmt = $db->prepare('INSERT INTO medico_paciente (medico_id, paciente_id) VALUES (?, ?)');
            $stmt->execute([$medicoId, $pacienteId]);
            registrar_evento('paciente_asignado', 'medico=' . $medicoId . ' paciente=' . $pacienteId);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            $mensaje = $e->getCode() === '23000'
                ? 'Ese paciente ya está asignado a ese médico.'
                : 'No se pudo crear la asignación. Intenta nuevamente.';
            echo json_encode(['ok' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if ($accion === 'quitar_asignacion') {
        $medicoId   = filter_input(INPUT_POST, 'medico_id', FILTER_VALIDATE_INT);
        $pacienteId = filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT);

        if (!$medicoId || !$pacienteId) {
            echo json_encode(['ok' => false, 'error' => 'Asignación inválida.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = db()->prepare('DELETE FROM medico_paciente WHERE medico_id = ? AND paciente_id = ?');
        $stmt->execute([$medicoId, $pacienteId]);

        echo json_encode(
            $stmt->rowCount() > 0
                ? ['ok' => true]
                : ['ok' => false, 'error' => 'La asignación no existe.'],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    if ($accion === 'cambiar_rol') {
        $cuentaId = filter_input(INPUT_POST, 'cuenta_id', FILTER_VALIDATE_INT);
        $nuevoRol = trim((string) ($_POST['rol'] ?? ''));

        $especializacionesEnviadas = $_POST['especializacion'] ?? [];
        $especializaciones = is_array($especializacionesEnviadas) ? array_values(array_unique(array_map('strval', $especializacionesEnviadas))) : [];

        $db   = db();
        $stmt = $db->prepare('SELECT id, rol, ref_id, activo FROM cuentas WHERE id = ?');
        $stmt->execute([$cuentaId ?: 0]);
        $cuenta = $stmt->fetch();

        if (!$cuenta) {
            echo json_encode(['ok' => false, 'error' => 'Cuenta no encontrada.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // Patient accounts are excluded in both directions.
        if (!isset(ROLES_STAFF[$cuenta['rol']])) {
            echo json_encode(['ok' => false, 'error' => 'Las cuentas de pacientes no cambian de rol.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ((int) $cuenta['id'] === $usuario['cuenta_id']) {
            echo json_encode(['ok' => false, 'error' => 'No podés cambiar tu propio rol.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $errores = [];
        if (!isset(ROLES_STAFF[$nuevoRol])) {
            $errores['rol'] = 'Elegí un rol válido.';
        } elseif ($nuevoRol === $cuenta['rol']) {
            $errores['rol'] = 'La cuenta ya tiene ese rol.';
        }
        // The only thing the professionals table requires that the others don't: at least one specialization.
        if ($nuevoRol === 'profesional') {
            if (!$especializaciones) {
                $errores['especializacion'] = 'Elegí al menos una especialización.';
            } elseif (array_diff($especializaciones, array_keys(estudios_activos()))) {
                $errores['especializacion'] = 'Elegí especializaciones válidas.';
            }
        }
        if ($errores) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $rolViejo = $cuenta['rol'];
        $refViejo = (int) $cuenta['ref_id'];
        $tablaVieja = TABLA_POR_ROL[$rolViejo];
        $tablaNueva = TABLA_POR_ROL[$nuevoRol];

        // Without a professional profile nobody would attend those citas: reassign or cancel them first.
        if ($rolViejo === 'profesional') {
            $stmt = $db->prepare('SELECT COUNT(*) FROM citas WHERE profesional_id = ? AND slot_activo = 1 AND fecha_hora_solicitada > NOW()');
            $stmt->execute([$refViejo]);
            $pendientes = (int) $stmt->fetchColumn();
            if ($pendientes > 0) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => "El profesional tiene {$pendientes} cita(s) futura(s) pendiente(s) o confirmada(s). Reasignalas o cancelalas antes de cambiar el rol."], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        try {
            $db->beginTransaction();

            // Only one active administrator: read with a lock so two simultaneous changes can't leave zero.
            if ($rolViejo === 'administrador' && (int) $cuenta['activo'] === 1) {
                $activos = (int) $db->query("SELECT COUNT(*) FROM cuentas WHERE rol = 'administrador' AND activo = 1 FOR UPDATE")->fetchColumn();
                if ($activos <= 1) {
                    $db->rollBack();
                    echo json_encode(['ok' => false, 'error' => 'No se puede cambiar el rol del último administrador activo.'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            $stmt = $db->prepare("SELECT nombre, apellido FROM {$tablaVieja} WHERE id = ?");
            $stmt->execute([$refViejo]);
            $perfil = $stmt->fetch();
            if (!$perfil) {
                throw new RuntimeException('perfil de origen inexistente');
            }

            $db->prepare("INSERT INTO {$tablaNueva} (nombre, apellido) VALUES (?, ?)")->execute([$perfil['nombre'], $perfil['apellido']]);
            $refNuevo = (int) $db->lastInsertId();

            if ($nuevoRol === 'profesional') {
                $stmt = $db->prepare('INSERT INTO profesional_especializacion (profesional_id, especializacion) VALUES (?, ?)');
                foreach ($especializaciones as $especializacion) {
                    $stmt->execute([$refNuevo, $especializacion]);
                }
            }

            $db->prepare('UPDATE cuentas SET rol = ?, ref_id = ? WHERE id = ?')->execute([$nuevoRol, $refNuevo, $cuenta['id']]);

            // The previous profile is deleted unless it has citas (history). The medico_paciente assignments
            // are deleted in cascade: without a medico account behind them they would grant access to nobody.
            $referenciado = false;
            if ($rolViejo === 'profesional') {
                $stmt = $db->prepare('SELECT 1 FROM citas WHERE profesional_id = ? LIMIT 1');
                $stmt->execute([$refViejo]);
                $referenciado = (bool) $stmt->fetchColumn();
            }
            if ($referenciado) {
                // Kept as history; a professional without branches is no longer offered for booking.
                if ($rolViejo === 'profesional') {
                    $db->prepare('DELETE FROM profesional_sucursal WHERE profesional_id = ?')->execute([$refViejo]);
                }
            } else {
                $db->prepare("DELETE FROM {$tablaVieja} WHERE id = ?")->execute([$refViejo]);
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('manejar-usuarios cambiar_rol: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'No se pudo cambiar el rol. Intenta nuevamente.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        registrar_evento('cuenta_rol', 'cuenta=' . $cuenta['id'] . ' de=' . $rolViejo . ' a=' . $nuevoRol);
        echo json_encode(['ok' => true, 'perfil_anterior_conservado' => $referenciado], JSON_UNESCAPED_UNICODE);
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
            $errores['rol'] = 'Elegí un rol válido.';
        }

        $errores = array_merge($errores, validar(REGLAS_STAFF, $_POST));

        if ($valores['rol'] === 'profesional') {
            if (!$especializaciones) {
                $errores['especializacion'] = 'Elegí al menos una especialización.';
            } else {
                foreach ($especializaciones as $especializacion) {
                    if (!isset(estudios_activos()[$especializacion])) {
                        $errores['especializacion'] = 'Elegí especializaciones válidas.';
                        break;
                    }
                }
            }
        } elseif ($especializaciones) {
            $errores['especializacion'] = 'La especialización solo aplica al rol Profesional.';
        }

        if ($errores) {
            echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = db();
        try {
            $db->beginTransaction();

            $tabla = TABLA_POR_ROL[$valores['rol']];
            $stmt  = $db->prepare("INSERT INTO {$tabla} (nombre, apellido) VALUES (?, ?)");
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

            registrar_evento('staff_alta', 'cuenta=' . $db->lastInsertId());

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
    echo json_encode(['ok' => false, 'error' => 'Acción inválida.'], JSON_UNESCAPED_UNICODE);
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
            COALESCE(p.nombre, m.nombre, pr.nombre, a.nombre, r.nombre)       AS nombre,
            COALESCE(p.apellido, m.apellido, pr.apellido, a.apellido, r.apellido) AS apellido
       FROM cuentas c
       LEFT JOIN pacientes      p  ON c.rol = 'paciente'      AND p.id  = c.ref_id
       LEFT JOIN medicos        m  ON c.rol = 'medico'        AND m.id  = c.ref_id
       LEFT JOIN profesionales  pr ON c.rol = 'profesional'   AND pr.id = c.ref_id
       LEFT JOIN administradores a ON c.rol = 'administrador' AND a.id  = c.ref_id
       LEFT JOIN recepcionistas r  ON c.rol = 'recepcionista' AND r.id  = c.ref_id
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
    'estudios'     => array_map(static fn (string $clave, string $etiqueta): array => ['clave' => $clave, 'etiqueta' => $etiqueta], array_keys(estudios_activos()), estudios_activos()),
], JSON_UNESCAPED_UNICODE);
