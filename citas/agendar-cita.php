<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
$usuario = requerir_rol(['paciente']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../includes/horarios.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/validar_reserva.php';

const RANGO_MESES = 3;
const NOTAS_ADMIN_MAX = 255;

$hoy       = new DateTimeImmutable('today');
$fechaMin  = $hoy->format('Y-m-d');
$fechaMax  = $hoy->modify('+' . RANGO_MESES . ' months')->format('Y-m-d');

$errores = [];
$valores = ['sucursal_id' => '', 'estudio' => '', 'profesional_id' => '', 'fecha' => '', 'hora' => ''];

$profesionales = db()->query('SELECT id, nombre, apellido FROM profesionales ORDER BY apellido, nombre')->fetchAll();

// Especializaciones are now a many-to-many relation (profesional_especializacion,
// replaces the old profesionales.especializacion column), reshaped here into
// $especializacionesPorProfesional[$profesionalId] = ['placa', ...] — mirrors
// how $datosSucursales['profesionales'] below is built from profesional_sucursal.
$profesionalEspecializacionFilas = db()->query('SELECT profesional_id, especializacion FROM profesional_especializacion')->fetchAll();
$especializacionesPorProfesional = [];
foreach ($profesionalEspecializacionFilas as $fila) {
    $especializacionesPorProfesional[(int) $fila['profesional_id']][] = $fila['especializacion'];
}

// Four flat reads, reshaped into one blob keyed by sucursal_id — no join,
// see design.md Decision 5 (a join across horarios x profesionales fans out).
$sucursalesFilas = db()->query('SELECT id, nombre, direccion FROM sucursales ORDER BY nombre')->fetchAll();
$horariosFilas   = db()->query(
    "SELECT sucursal_id, dia_semana,
            TIME_FORMAT(hora_apertura, '%H:%i') AS apertura,
            TIME_FORMAT(hora_cierre,   '%H:%i') AS cierre
       FROM sucursal_horarios
      ORDER BY sucursal_id, dia_semana, hora_apertura"
)->fetchAll();
$estudioFilas             = db()->query('SELECT sucursal_id, estudio FROM sucursal_estudio')->fetchAll();
$profesionalSucursalFilas = db()->query('SELECT sucursal_id, profesional_id FROM profesional_sucursal')->fetchAll();

$datosSucursales = [];
foreach ($sucursalesFilas as $sucursal) {
    $datosSucursales[(string) $sucursal['id']] = [
        'nombre'        => $sucursal['nombre'],
        'direccion'     => $sucursal['direccion'],
        'estudios'      => [],           // [] = sin restriccion (design.md Decision 3)
        'horarios'      => [],           // dia_semana (string) => [[apertura, cierre], ...]
        'profesionales' => [],
    ];
}
foreach ($horariosFilas as $turno) {
    $clave = (string) $turno['sucursal_id'];
    $datosSucursales[$clave]['horarios'][(string) $turno['dia_semana']][] = [$turno['apertura'], $turno['cierre']];
}
foreach ($estudioFilas as $restriccion) {
    $datosSucursales[(string) $restriccion['sucursal_id']]['estudios'][] = $restriccion['estudio'];
}
foreach ($profesionalSucursalFilas as $asignacion) {
    $datosSucursales[(string) $asignacion['sucursal_id']]['profesionales'][] = (int) $asignacion['profesional_id'];
}

$formAccion = 'crear';
$formCitaId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cancelar') {
        $citaId = filter_input(INPUT_POST, 'cita_id', FILTER_VALIDATE_INT);
        $motivo = trim((string) ($_POST['notas_admin'] ?? ''));

        // Prefijo server-side, nunca parte del input del paciente: sin esto
        // un paciente podia escribir texto que se mostraba al staff en
        // historial-pacientes.php indistinguible de una nota real de
        // administrador (misma columna, sin indicar autoria).
        $prefijoPaciente = 'Cancelado por el paciente: ';
        $maxMotivo = NOTAS_ADMIN_MAX - mb_strlen($prefijoPaciente);
        if (mb_strlen($motivo) > $maxMotivo) {
            $motivo = mb_substr($motivo, 0, $maxMotivo);
        }
        $notaGuardada = $motivo !== '' ? $prefijoPaciente . $motivo : null;

        if ($citaId) {
            // Ownership AND state enforced in the WHERE clause itself, not
            // just checked beforehand in PHP.
            $stmt = db()->prepare(
                "UPDATE citas SET estado = 'cancelada', notas_admin = ?
                 WHERE id = ? AND paciente_id = ? AND estado = 'pendiente'"
            );
            $stmt->execute([$notaGuardada, $citaId, $usuario['ref_id']]);
        }
        header('Location: agendar-cita.php?cancelado=1');
        exit;
    }

    if ($accion === 'crear') {
        $valores['sucursal_id']    = (string) ($_POST['sucursal_id'] ?? '');
        $valores['estudio']        = (string) ($_POST['estudio'] ?? '');
        $valores['profesional_id'] = (string) ($_POST['profesional_id'] ?? '');
        $valores['fecha']          = (string) ($_POST['fecha'] ?? '');
        $valores['hora']           = (string) ($_POST['hora'] ?? '');

        [$errores, $sucursalId, $profesionalId, $marcaTiempo, $estudio] = validar_reserva($valores, ESTUDIOS_BASE, $fechaMin, $fechaMax, RANGO_MESES);

        if (!$errores) {
            $stmt = db()->prepare(
                "INSERT INTO citas (paciente_id, profesional_id, sucursal_id, fecha_hora_solicitada, estudio, estado)
                 VALUES (?, ?, ?, ?, ?, 'pendiente')"
            );
            $stmt->execute([
                $usuario['ref_id'],
                $profesionalId,
                $sucursalId,
                $marcaTiempo->format('Y-m-d H:i:s'),
                $estudio,
            ]);
            header('Location: agendar-cita.php?ok=1');
            exit;
        }
    }

    if ($accion === 'reprogramar') {
        $valores['sucursal_id']    = (string) ($_POST['sucursal_id'] ?? '');
        $valores['estudio']        = (string) ($_POST['estudio'] ?? '');
        $valores['profesional_id'] = (string) ($_POST['profesional_id'] ?? '');
        $valores['fecha']          = (string) ($_POST['fecha'] ?? '');
        $valores['hora']           = (string) ($_POST['hora'] ?? '');
        $citaId                    = filter_input(INPUT_POST, 'cita_id', FILTER_VALIDATE_INT);

        [$errores, $sucursalId, $profesionalId, $marcaTiempo, $estudio] = validar_reserva($valores, ESTUDIOS_BASE, $fechaMin, $fechaMax, RANGO_MESES);

        if (!$errores) {
            if ($citaId) {
                // Ownership AND state enforced in the WHERE clause itself,
                // same atomic pattern as 'cancelar'.
                $stmt = db()->prepare(
                    "UPDATE citas SET fecha_hora_solicitada = ?, profesional_id = ?, sucursal_id = ?, estudio = ?
                     WHERE id = ? AND paciente_id = ? AND estado = 'pendiente'"
                );
                $stmt->execute([
                    $marcaTiempo->format('Y-m-d H:i:s'),
                    $profesionalId,
                    $sucursalId,
                    $estudio,
                    $citaId,
                    $usuario['ref_id'],
                ]);

                if ($stmt->rowCount() === 0) {
                    // La cita ya no era editable: no era del paciente, ya
                    // cambio de estado, o el id no existe.
                    header('Location: agendar-cita.php?error=reprogramar');
                    exit;
                }

                header('Location: agendar-cita.php?reprogramado=1');
                exit;
            }

            header('Location: agendar-cita.php?error=reprogramar');
            exit;
        }

        // Validation failed: fall through to re-render the form with the
        // submitted values, keeping accion=reprogramar and cita_id.
        $formAccion = 'reprogramar';
        $formCitaId = $citaId;
    }
}

if (isset($_GET['editar'])) {
    $editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
    if ($editarId) {
        $stmt = db()->prepare(
            "SELECT sucursal_id, estudio, profesional_id, fecha_hora_solicitada
               FROM citas WHERE id = ? AND paciente_id = ? AND estado = 'pendiente'"
        );
        $stmt->execute([$editarId, $usuario['ref_id']]);
        $citaAEditar = $stmt->fetch();
        if ($citaAEditar) {
            $fechaHoraEditar = new DateTimeImmutable($citaAEditar['fecha_hora_solicitada']);
            $valores['sucursal_id']    = (string) $citaAEditar['sucursal_id'];
            $valores['estudio']        = (string) $citaAEditar['estudio'];
            $valores['profesional_id'] = (string) $citaAEditar['profesional_id'];
            $valores['fecha']          = $fechaHoraEditar->format('Y-m-d');
            $valores['hora']           = $fechaHoraEditar->format('H:i');
            $formAccion = 'reprogramar';
            $formCitaId = $editarId;
        }
    }
}

$stmt = db()->prepare(
    'SELECT c.*, p.nombre AS profesional_nombre, p.apellido AS profesional_apellido, s.nombre AS sucursal_nombre
     FROM citas c
     JOIN profesionales p ON p.id = c.profesional_id
     LEFT JOIN sucursales s ON s.id = c.sucursal_id
     WHERE c.paciente_id = ?
     ORDER BY c.creado_en DESC'
);
$stmt->execute([$usuario['ref_id']]);
$citas = $stmt->fetchAll();
require __DIR__ . '/../views/agendar-cita.php';
