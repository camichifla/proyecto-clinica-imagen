<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
$usuario = requerir_rol(['administrador']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/menu.php';

const ROLES_STAFF = [
    'medico'        => 'Medico',
    'profesional'   => 'Profesional',
    'administrador' => 'Administrador',
];

const ROLES_TODOS = [
    'paciente'      => 'Paciente',
    'medico'        => 'Medico',
    'profesional'   => 'Profesional',
    'administrador' => 'Administrador',
];

const ESPECIALIZACIONES = [
    'placa'       => 'Placa',
    'radiografia' => 'Radiografia',
    'alineadores' => 'Alineadores',
];

// Role -> role-detail table, matches cuentas.rol / cuentas.ref_id (Decision 1).
const TABLA_POR_ROL = [
    'paciente'      => 'pacientes',
    'medico'        => 'medicos',
    'profesional'   => 'profesionales',
    'administrador' => 'administradores',
];

$erroresCreacion  = [];
$valoresCreacion  = ['rol' => '', 'nombre' => '', 'apellido' => '', 'especializacion' => [], 'email' => ''];
$creacionExitosa  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'toggle_activo') {
        $cuentaId = filter_input(INPUT_POST, 'cuenta_id', FILTER_VALIDATE_INT);

        if (!$cuentaId) {
            header('Location: manejar-usuarios.php?toggle_error=' . urlencode('Cuenta invalida.'));
            exit;
        }

        // Safety rule 1: never let an administrador deactivate their own account.
        if ($cuentaId === $usuario['cuenta_id']) {
            header('Location: manejar-usuarios.php?toggle_error=' . urlencode('No podes desactivar tu propia cuenta.'));
            exit;
        }

        $db   = db();
        $stmt = $db->prepare('SELECT id, rol, activo FROM cuentas WHERE id = ?');
        $stmt->execute([$cuentaId]);
        $cuentaObjetivo = $stmt->fetch();

        if (!$cuentaObjetivo) {
            header('Location: manejar-usuarios.php?toggle_error=' . urlencode('Cuenta no encontrada.'));
            exit;
        }

        $estabaActiva = (int) $cuentaObjetivo['activo'] === 1;

        // Safety rule 2: never let the last active administrador be deactivated
        // (would lock every admin out of the system).
        if ($estabaActiva && $cuentaObjetivo['rol'] === 'administrador') {
            $stmt = $db->prepare("SELECT COUNT(*) FROM cuentas WHERE rol = 'administrador' AND activo = 1");
            $stmt->execute();
            $activos = (int) $stmt->fetchColumn();
            if ($activos <= 1) {
                header('Location: manejar-usuarios.php?toggle_error=' . urlencode('No se puede desactivar el ultimo administrador activo.'));
                exit;
            }
        }

        $stmt = $db->prepare('UPDATE cuentas SET activo = ? WHERE id = ?');
        $stmt->execute([$estabaActiva ? 0 : 1, $cuentaId]);

        header('Location: manejar-usuarios.php?toggle_ok=1');
        exit;
    }

    if ($accion === 'asignar_paciente') {
        $medicoId   = filter_input(INPUT_POST, 'medico_id', FILTER_VALIDATE_INT);
        $pacienteId = filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT);

        if (!$medicoId || !$pacienteId) {
            header('Location: manejar-usuarios.php?asignacion_error=' . urlencode('Elegi un medico y un paciente validos.'));
            exit;
        }

        $db = db();
        $stmt = $db->prepare('SELECT id FROM medicos WHERE id = ?');
        $stmt->execute([$medicoId]);
        if (!$stmt->fetch()) {
            header('Location: manejar-usuarios.php?asignacion_error=' . urlencode('Elegi un medico y un paciente validos.'));
            exit;
        }

        $stmt = $db->prepare('SELECT id FROM pacientes WHERE id = ?');
        $stmt->execute([$pacienteId]);
        if (!$stmt->fetch()) {
            header('Location: manejar-usuarios.php?asignacion_error=' . urlencode('Elegi un medico y un paciente validos.'));
            exit;
        }

        try {
            $stmt = $db->prepare('INSERT INTO medico_paciente (medico_id, paciente_id) VALUES (?, ?)');
            $stmt->execute([$medicoId, $pacienteId]);
            header('Location: manejar-usuarios.php?asignacion_ok=1');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                header('Location: manejar-usuarios.php?asignacion_error=' . urlencode('Ese paciente ya esta asignado a ese medico.'));
            } else {
                header('Location: manejar-usuarios.php?asignacion_error=' . urlencode('No se pudo crear la asignacion. Intenta nuevamente.'));
            }
            exit;
        }
    }

    if ($accion === 'quitar_asignacion') {
        $medicoId   = filter_input(INPUT_POST, 'medico_id', FILTER_VALIDATE_INT);
        $pacienteId = filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT);

        if (!$medicoId || !$pacienteId) {
            header('Location: manejar-usuarios.php?asignacion_error=' . urlencode('Asignacion invalida.'));
            exit;
        }

        $stmt = db()->prepare('DELETE FROM medico_paciente WHERE medico_id = ? AND paciente_id = ?');
        $stmt->execute([$medicoId, $pacienteId]);
        header('Location: manejar-usuarios.php?' . ($stmt->rowCount() > 0 ? 'asignacion_quitada=1' : 'asignacion_error=' . urlencode('La asignacion no existe.')));
        exit;
    }

    if ($accion === 'crear_staff') {
        foreach ($valoresCreacion as $campo => $_valor) {
            if ($campo === 'especializacion') {
                continue;
            }
            $valoresCreacion[$campo] = trim((string) ($_POST[$campo] ?? ''));
        }

        // especializacion[] is now multi-valued (profesional_especializacion
        // M2M), so it is collected separately from the generic trim loop
        // above rather than trim()'d as a scalar.
        $especializacionesEnviadas = $_POST['especializacion'] ?? [];
        if (!is_array($especializacionesEnviadas)) {
            $especializacionesEnviadas = [];
        }
        $valoresCreacion['especializacion'] = array_values(array_unique(array_map('strval', $especializacionesEnviadas)));

        if (!isset(ROLES_STAFF[$valoresCreacion['rol']])) {
            $erroresCreacion['rol'] = 'Elegi un rol valido.';
        }

        $erroresCreacion = array_merge($erroresCreacion, validar(REGLAS_STAFF, $_POST));

        // especializacion required iff rol === 'profesional'; never trust the
        // client-side show/hide for this. Every submitted value must be a
        // real ESPECIALIZACIONES key — the whole submission is rejected if
        // any value is invalid, never silently dropped.
        if ($valoresCreacion['rol'] === 'profesional') {
            if (!$valoresCreacion['especializacion']) {
                $erroresCreacion['especializacion'] = 'Elegi al menos una especializacion.';
            } else {
                foreach ($valoresCreacion['especializacion'] as $especializacion) {
                    if (!isset(ESPECIALIZACIONES[$especializacion])) {
                        $erroresCreacion['especializacion'] = 'Elegi especializaciones validas.';
                        break;
                    }
                }
            }
        } elseif ($valoresCreacion['especializacion']) {
            $erroresCreacion['especializacion'] = 'La especializacion solo aplica al rol Profesional.';
        }

        if (!$erroresCreacion) {
            $db = db();
            try {
                $db->beginTransaction();

                if ($valoresCreacion['rol'] === 'profesional') {
                    $stmt = $db->prepare('INSERT INTO profesionales (nombre, apellido) VALUES (?, ?)');
                    $stmt->execute([$valoresCreacion['nombre'], $valoresCreacion['apellido']]);
                } else {
                    $tabla = TABLA_POR_ROL[$valoresCreacion['rol']];
                    $stmt  = $db->prepare("INSERT INTO {$tabla} (nombre, apellido) VALUES (?, ?)");
                    $stmt->execute([$valoresCreacion['nombre'], $valoresCreacion['apellido']]);
                }
                $refId = (int) $db->lastInsertId();

                if ($valoresCreacion['rol'] === 'profesional') {
                    $stmtEspecializacion = $db->prepare(
                        'INSERT INTO profesional_especializacion (profesional_id, especializacion) VALUES (?, ?)'
                    );
                    foreach ($valoresCreacion['especializacion'] as $especializacion) {
                        $stmtEspecializacion->execute([$refId, $especializacion]);
                    }
                }

                // verificado=1 and activo=1: an admin-created account is
                // already vetted by a human, unlike patient self-registration.
                $hash = password_hash((string) $_POST['contrasena'], PASSWORD_BCRYPT);
                $stmt = $db->prepare('INSERT INTO cuentas (email, password_hash, rol, ref_id, verificado, activo) VALUES (?, ?, ?, ?, 1, 1)');
                $stmt->execute([$valoresCreacion['email'], $hash, $valoresCreacion['rol'], $refId]);

                $db->commit();

                $creacionExitosa = true;
                $valoresCreacion = ['rol' => '', 'nombre' => '', 'apellido' => '', 'especializacion' => [], 'email' => ''];
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uq_cuentas_email')) {
                    $erroresCreacion['email'] = 'Ya existe una cuenta registrada con ese correo.';
                } else {
                    $erroresCreacion['general'] = 'No se pudo crear la cuenta. Intenta nuevamente.';
                }
            }
        }
    }
}

$mensajeToggle = null;
$errorToggle   = null;
if (isset($_GET['toggle_ok'])) {
    $mensajeToggle = 'Estado de la cuenta actualizado.';
} elseif (isset($_GET['toggle_error'])) {
    $errorToggle = (string) $_GET['toggle_error'];
}

$mensajeAsignacion = null;
$errorAsignacion   = null;
if (isset($_GET['asignacion_ok'])) {
    $mensajeAsignacion = 'Paciente asignado correctamente.';
} elseif (isset($_GET['asignacion_quitada'])) {
    $mensajeAsignacion = 'Asignacion eliminada.';
} elseif (isset($_GET['asignacion_error'])) {
    $errorAsignacion = (string) $_GET['asignacion_error'];
}

$medicosParaAsignar   = db()->query('SELECT id, nombre, apellido FROM medicos ORDER BY apellido, nombre')->fetchAll();
$pacientesParaAsignar = db()->query('SELECT id, nombre, apellido, ci FROM pacientes ORDER BY apellido, nombre')->fetchAll();

$asignaciones = db()->query(
    "SELECT mp.medico_id, m.nombre AS medico_nombre, m.apellido AS medico_apellido,
            mp.paciente_id, p.nombre AS paciente_nombre, p.apellido AS paciente_apellido
       FROM medico_paciente mp
       JOIN medicos m ON m.id = mp.medico_id
       JOIN pacientes p ON p.id = mp.paciente_id
      ORDER BY m.apellido, p.apellido"
)->fetchAll();

// One query, four LEFT JOINs scoped by rol so ref_id (polymorphic per
// design Decision 1) resolves to the right role-detail row.
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
$cuentas = $stmt->fetchAll();

$adminActivosCount = 0;
foreach ($cuentas as $fila) {
    if ($fila['rol'] === 'administrador' && (int) $fila['activo'] === 1) {
        $adminActivosCount++;
    }
}
require __DIR__ . '/../views/manejar-usuarios.php';
