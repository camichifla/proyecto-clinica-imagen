<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/horarios.php';
require_once __DIR__ . '/../includes/mailer.php';

$usuario = requerir_rol_json(['administrador']);

const ROLES_STAFF = [
    'medico'        => 'Médico',
    'profesional'   => 'Profesional',
    'administrador' => 'Administrador',
    'recepcionista' => 'Recepcionista',
];

// Roles "Crear cuenta" offers: the staff ones plus paciente.
const ROLES_CREABLES = ROLES_STAFF + ['paciente' => 'Paciente'];

function columna_de(string $sql, int $id): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function turnos_propios_de(int $id): array
{
    $stmt = db()->prepare(
        "SELECT dia_semana AS dia, TIME_FORMAT(hora_apertura, '%H:%i') AS apertura, TIME_FORMAT(hora_cierre, '%H:%i') AS cierre
           FROM profesional_horarios WHERE profesional_id = ? ORDER BY dia_semana, hora_apertura"
    );
    $stmt->execute([$id]);
    return array_map(static fn (array $t): array => ['dia' => (int) $t['dia'], 'apertura' => $t['apertura'], 'cierre' => $t['cierre']], $stmt->fetchAll());
}

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
            echo json_encode(['ok' => false, 'error' => 'Elegí un médico y un paciente válidos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db   = db();
        $stmt = $db->prepare('SELECT id FROM medicos WHERE id = ?');
        $stmt->execute([$medicoId]);
        if (!$stmt->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Elegí un médico y un paciente válidos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $db->prepare('SELECT id FROM pacientes WHERE id = ?');
        $stmt->execute([$pacienteId]);
        if (!$stmt->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Elegí un médico y un paciente válidos.'], JSON_UNESCAPED_UNICODE);
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
                : 'No se pudo crear la asignación. Intentá nuevamente.';
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

            $stmt = $db->prepare("SELECT nombre, apellido, ci FROM {$tablaVieja} WHERE id = ?");
            $stmt->execute([$refViejo]);
            $perfil = $stmt->fetch();
            if (!$perfil) {
                throw new RuntimeException('perfil de origen inexistente');
            }

            $db->prepare("INSERT INTO {$tablaNueva} (nombre, apellido, ci) VALUES (?, ?, ?)")->execute([$perfil['nombre'], $perfil['apellido'], $perfil['ci']]);
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
            echo json_encode(['ok' => false, 'error' => 'No se pudo cambiar el rol. Intentá nuevamente.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        registrar_evento('cuenta_rol', 'cuenta=' . $cuenta['id'] . ' de=' . $rolViejo . ' a=' . $nuevoRol);
        echo json_encode(['ok' => true, 'perfil_anterior_conservado' => $referenciado], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($accion === 'editar_cuenta') {
        $cuentaId = filter_input(INPUT_POST, 'cuenta_id', FILTER_VALIDATE_INT);
        $db   = db();
        $stmt = $db->prepare('SELECT id, rol, ref_id, email, email_anterior FROM cuentas WHERE id = ?');
        $stmt->execute([$cuentaId ?: 0]);
        $cuenta = $stmt->fetch();
        if (!$cuenta) {
            echo json_encode(['ok' => false, 'error' => 'Cuenta no encontrada.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $rol   = $cuenta['rol'];
        $refId = (int) $cuenta['ref_id'];

        // The same rules as creating the account, minus the password (not editable here).
        $reglas = $rol === 'paciente' ? REGLAS_PACIENTE : REGLAS_STAFF;
        unset($reglas['contrasena']);
        $errores = validar($reglas, $_POST);

        $sucursalRecep = null;
        if ($rol === 'recepcionista') {
            $sucursalRecep = filter_input(INPUT_POST, 'sucursal_id', FILTER_VALIDATE_INT) ?: null;
            if ($sucursalRecep !== null) {
                $existe = $db->prepare('SELECT 1 FROM sucursales WHERE id = ?');
                $existe->execute([$sucursalRecep]);
                if (!$existe->fetchColumn()) {
                    $errores['sucursal_id'] = 'Sede inválida.';
                }
            }
        }

        $sucursales = $claves = $turnos = [];
        if ($rol === 'profesional') {
            $enviadas   = $_POST['sucursales'] ?? [];
            $sucursales = is_array($enviadas) ? array_values(array_unique(array_map('strval', $enviadas))) : [];
            $validas    = array_map('strval', $db->query('SELECT id FROM sucursales')->fetchAll(PDO::FETCH_COLUMN));
            if (array_diff($sucursales, $validas)) {
                $errores['sucursales'] = 'Elegí sedes válidas.';
            }

            // Active studies; inactive ones the professional already had may be kept but not added.
            $enviados   = $_POST['especializacion'] ?? [];
            $claves     = is_array($enviados) ? array_values(array_unique(array_map('strval', $enviados))) : [];
            $permitidos = array_merge(
                array_keys(estudios_activos()),
                columna_de('SELECT especializacion FROM profesional_especializacion WHERE profesional_id = ?', $refId)
            );
            if (!$claves) {
                $errores['especializacion'] = 'Elegí al menos una especialización.';
            } elseif (array_diff($claves, $permitidos)) {
                $errores['especializacion'] = 'Elegí especializaciones válidas.';
            }

            $turnos = validar_turnos($_POST['horarios'] ?? '');
            if (is_string($turnos)) {
                $errores['horarios'] = $turnos;
                $turnos = [];
            }
        }

        if ($errores) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $nombre   = trim((string) $_POST['nombre']);
        $apellido = trim((string) $_POST['apellido']);
        $ci       = trim((string) $_POST['ci']);
        $email    = trim((string) $_POST['email']);
        $tabla    = TABLA_POR_ROL[$rol];
        $horariosCambiaron = $rol === 'profesional' && turnos_propios_de($refId) !== $turnos;

        try {
            $db->beginTransaction();
            if ($rol === 'paciente') {
                $db->prepare('UPDATE pacientes SET nombre = ?, apellido = ?, ci = ?, direccion = ?, numero = ? WHERE id = ?')
                    ->execute([$nombre, $apellido, $ci, trim((string) $_POST['direccion']), trim((string) $_POST['numero']), $refId]);
            } elseif ($rol === 'recepcionista') {
                $db->prepare('UPDATE recepcionistas SET nombre = ?, apellido = ?, ci = ?, sucursal_id = ? WHERE id = ?')
                    ->execute([$nombre, $apellido, $ci, $sucursalRecep, $refId]);
            } else {
                $db->prepare("UPDATE {$tabla} SET nombre = ?, apellido = ?, ci = ? WHERE id = ?")->execute([$nombre, $apellido, $ci, $refId]);
            }
            $tokenEmail = aplicar_cambio_email($db, $cuenta, $email);

            if ($rol === 'profesional') {
                foreach (['profesional_sucursal', 'profesional_especializacion', 'profesional_horarios'] as $t) {
                    $db->prepare("DELETE FROM {$t} WHERE profesional_id = ?")->execute([$refId]);
                }
                $stmt = $db->prepare('INSERT INTO profesional_sucursal (profesional_id, sucursal_id) VALUES (?, ?)');
                foreach ($sucursales as $sucursalId) {
                    $stmt->execute([$refId, (int) $sucursalId]);
                }
                $stmt = $db->prepare('INSERT INTO profesional_especializacion (profesional_id, especializacion) VALUES (?, ?)');
                foreach ($claves as $clave) {
                    $stmt->execute([$refId, $clave]);
                }
                $stmt = $db->prepare('INSERT INTO profesional_horarios (profesional_id, dia_semana, hora_apertura, hora_cierre) VALUES (?, ?, ?, ?)');
                foreach ($turnos as $t) {
                    $stmt->execute([$refId, $t['dia'], $t['apertura'] . ':00', $t['cierre'] . ':00']);
                }
            }
            $db->commit();
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uq_cuentas_email')) {
                $errores = ['email' => 'Ya existe una cuenta registrada con ese correo.'];
            } elseif ($e->getCode() === '23000' && str_contains($e->getMessage(), '_ci')) {
                $errores = ['ci' => 'Ya existe una cuenta registrada con esa cédula.'];
            } else {
                error_log('manejar-usuarios editar_cuenta: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la cuenta. Intentá nuevamente.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            http_response_code(422);
            echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
            exit;
        }

        registrar_evento('cuenta_edicion', 'cuenta=' . $cuenta['id']);
        if ($tokenEmail !== null) {
            enviar_confirmacion_email($email, $cuenta['email'], APP_URL . '/verify.html?token=' . $tokenEmail);
        }
        if ($rol === 'profesional') {
            registrar_evento('profesional_cambio', 'profesional=' . $refId);
            if ($horariosCambiaron) {
                registrar_evento('profesional_horarios', 'profesional=' . $refId);
            }
        }
        echo json_encode(['ok' => true, 'email_pendiente' => $tokenEmail !== null ? $email : null], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($accion === 'crear_staff') {
        $valores = [
            'rol'      => trim((string) ($_POST['rol'] ?? '')),
            'nombre'   => trim((string) ($_POST['nombre'] ?? '')),
            'apellido' => trim((string) ($_POST['apellido'] ?? '')),
            'ci'       => trim((string) ($_POST['ci'] ?? '')),
            'email'    => trim((string) ($_POST['email'] ?? '')),
        ];

        $especializacionesEnviadas = $_POST['especializacion'] ?? [];
        if (!is_array($especializacionesEnviadas)) {
            $especializacionesEnviadas = [];
        }
        $especializaciones = array_values(array_unique(array_map('strval', $especializacionesEnviadas)));

        $errores = [];
        if (!isset(ROLES_CREABLES[$valores['rol']])) {
            $errores['rol'] = 'Elegí un rol válido.';
        }

        // Same rules as the public sign-up for a paciente (adds dirección and teléfono); staff need nombre, apellido, CI, email, password.
        $errores = array_merge($errores, validar($valores['rol'] === 'paciente' ? REGLAS_PACIENTE : REGLAS_STAFF, $_POST));

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
            if ($valores['rol'] === 'paciente') {
                $stmt = $db->prepare('INSERT INTO pacientes (nombre, apellido, ci, direccion, numero) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$valores['nombre'], $valores['apellido'], $valores['ci'], trim((string) $_POST['direccion']), trim((string) $_POST['numero'])]);
            } else {
                $stmt = $db->prepare("INSERT INTO {$tabla} (nombre, apellido, ci) VALUES (?, ?, ?)");
                $stmt->execute([$valores['nombre'], $valores['apellido'], $valores['ci']]);
            }
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
            } elseif ($e->getCode() === '23000' && str_contains($e->getMessage(), '_ci')) {
                echo json_encode(['ok' => false, 'errores' => ['ci' => 'Ya existe una cuenta registrada con esa cédula.']], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['ok' => false, 'errores' => ['general' => 'No se pudo crear la cuenta. Intentá nuevamente.']], JSON_UNESCAPED_UNICODE);
            }
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Acción inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$medicos   = db()->query("SELECT id, nombre, apellido, ci, (SELECT email FROM cuentas WHERE rol = 'medico' AND ref_id = medicos.id LIMIT 1) AS email FROM medicos ORDER BY apellido, nombre")->fetchAll();
$pacientes = db()->query("SELECT id, nombre, apellido, ci, (SELECT email FROM cuentas WHERE rol = 'paciente' AND ref_id = pacientes.id LIMIT 1) AS email FROM pacientes ORDER BY apellido, nombre")->fetchAll();

$asignacionesFilas = db()->query(
    "SELECT mp.medico_id, m.nombre AS medico_nombre, m.apellido AS medico_apellido,
            mp.paciente_id, p.nombre AS paciente_nombre, p.apellido AS paciente_apellido, p.ci AS paciente_ci
       FROM medico_paciente mp
       JOIN medicos m ON m.id = mp.medico_id
       JOIN pacientes p ON p.id = mp.paciente_id
      ORDER BY m.apellido, p.apellido"
)->fetchAll();

$stmt = db()->query(
    "SELECT c.id AS cuenta_id, c.email, c.rol, c.verificado, c.activo, c.ref_id, r.sucursal_id AS recep_sucursal_id, p.direccion, p.numero,
            COALESCE(p.ci, m.ci, pr.ci, a.ci, r.ci) AS ci,
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
        $extra = [];
        if ($fila['rol'] === 'profesional') {
            $id = (int) $fila['ref_id'];
            $extra = [
                'sedes'           => array_map('intval', columna_de('SELECT sucursal_id FROM profesional_sucursal WHERE profesional_id = ? ORDER BY sucursal_id', $id)),
                'especializacion' => columna_de('SELECT especializacion FROM profesional_especializacion WHERE profesional_id = ? ORDER BY especializacion', $id),
                'horarios'        => turnos_propios_de($id),
            ];
        }
        return $extra + [
            'cuenta_id'  => (int) $fila['cuenta_id'],
            'direccion'  => (string) $fila['direccion'],
            'numero'     => (string) $fila['numero'],
            'email'      => $fila['email'],
            'rol'        => $fila['rol'],
            'verificado' => (int) $fila['verificado'] === 1,
            'activo'     => (int) $fila['activo'] === 1,
            'nombre'     => (string) $fila['nombre'],
            'apellido'   => (string) $fila['apellido'],
            'ci'         => (string) $fila['ci'],
            'sucursalId' => $fila['recep_sucursal_id'] !== null ? (int) $fila['recep_sucursal_id'] : null,
        ];
    }, $cuentasFilas),
    'medicos'      => $medicos,
    'sucursales'   => db()->query('SELECT id, nombre, activo FROM sucursales ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC),
    'pacientes'    => $pacientes,
    'asignaciones' => $asignacionesFilas,
    'estudios_catalogo' => array_map(static fn (array $e): array => ['clave' => $e['clave'], 'etiqueta' => $e['etiqueta'], 'activo' => (int) $e['activo'] === 1], estudios_catalogo()),
    'estudios'     => array_map(static fn (string $clave, string $etiqueta): array => ['clave' => $clave, 'etiqueta' => $etiqueta], array_keys(estudios_activos()), estudios_activos()),
], JSON_UNESCAPED_UNICODE);
