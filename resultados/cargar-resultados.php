<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
$usuario = requerir_rol(['profesional']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/resultados_upload.php';
require_once __DIR__ . '/../includes/pacientes_profesional.php';

$hoy             = new DateTimeImmutable('today');
$fechaMaxEstudio = $hoy->format('Y-m-d');

// Solo pacientes con los que este profesional tiene una relacion de
// atencion real (al menos una cita), nunca la lista completa — mismo
// patron que historial-pacientes.php.
$pacientes = pacientes_de_profesional($usuario['ref_id']);

$errores = [];
$valores = ['paciente_id' => '', 'cita_id' => '', 'nombre_estudio' => '', 'fecha_estudio' => '', 'observaciones' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);

    $valores['paciente_id']    = (string) ($_POST['paciente_id'] ?? '');
    $valores['cita_id']        = (string) ($_POST['cita_id'] ?? '');
    $valores['nombre_estudio'] = trim((string) ($_POST['nombre_estudio'] ?? ''));
    $valores['fecha_estudio']  = (string) ($_POST['fecha_estudio'] ?? '');
    $valores['observaciones']  = trim((string) ($_POST['observaciones'] ?? ''));

    // --- Paciente: nunca confiar en el paciente_id del POST a ciegas —
    // tiene que ser uno de los propios pacientes de este profesional
    // (mismo re-chequeo estricto que historial-pacientes.php). El mismo
    // mensaje generico cubre "no existe" y "no es tuyo": nunca se revela
    // cual de los dos casos aplica. ---
    $pacienteId = filter_var($valores['paciente_id'], FILTER_VALIDATE_INT);
    if (!$pacienteId) {
        $errores['paciente_id'] = 'Elegi un paciente.';
    } elseif (!paciente_de_profesional($usuario['ref_id'], $pacienteId)) {
        $errores['paciente_id'] = 'Elegi un paciente valido.';
        $pacienteId = null;
    }

    // --- Cita opcional: si se envia un cita_id, tiene que pertenecer al
    // paciente elegido, estar confirmada Y ser una cita propia de este
    // profesional — nunca de otro profesional con el mismo paciente. ---
    $citaId = null;
    if ($valores['cita_id'] !== '') {
        $citaIdCandidato = filter_var($valores['cita_id'], FILTER_VALIDATE_INT);
        if (!$citaIdCandidato) {
            $errores['cita_id'] = 'Cita invalida.';
        } elseif (!$pacienteId) {
            $errores['cita_id'] = 'Elegi un paciente valido primero.';
        } else {
            $stmt = db()->prepare(
                "SELECT id FROM citas
                 WHERE id = ? AND paciente_id = ? AND profesional_id = ? AND estado = 'confirmada'"
            );
            $stmt->execute([$citaIdCandidato, $pacienteId, $usuario['ref_id']]);
            if (!$stmt->fetch()) {
                $errores['cita_id'] = 'La cita elegida no pertenece a este paciente o no esta confirmada.';
            } else {
                $citaId = $citaIdCandidato;
            }
        }
    }

    // --- Nombre del estudio ---
    if ($valores['nombre_estudio'] === '') {
        $errores['nombre_estudio'] = 'El nombre del estudio es obligatorio.';
    } elseif (mb_strlen($valores['nombre_estudio']) > 120) {
        $errores['nombre_estudio'] = 'El nombre del estudio no puede superar 120 caracteres.';
    }

    // --- Fecha del estudio: valida y nunca futura ---
    if ($valores['fecha_estudio'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valores['fecha_estudio'])) {
        $errores['fecha_estudio'] = 'Elegi una fecha valida.';
    } else {
        [$anioEstudio, $mesEstudio, $diaEstudio] = array_map('intval', explode('-', $valores['fecha_estudio']));
        if (!checkdate($mesEstudio, $diaEstudio, $anioEstudio)) {
            $errores['fecha_estudio'] = 'Elegi una fecha valida.';
        } elseif ($valores['fecha_estudio'] > $fechaMaxEstudio) {
            $errores['fecha_estudio'] = 'La fecha del estudio no puede ser futura.';
        }
    }

    // --- Imagenes: al menos una, cada una re-validada server-side por
    // contenido real (finfo), nunca por Content-Type ni por extension. ---
    $archivosValidados = [];
    $archivos           = normalizar_archivos($_FILES['imagenes'] ?? null);
    if (!$archivos) {
        $errores['imagenes'] = 'Subi al menos una imagen.';
    } else {
        foreach ($archivos as $archivo) {
            $extension = null;
            $errorImagen = validar_imagen($archivo, $extension);
            if ($errorImagen !== null) {
                $errores['imagenes'] = $errorImagen;
                break;
            }
            $archivo['extension'] = $extension;
            $archivosValidados[]  = $archivo;
        }
    }

    if (!$errores) {
        try {
            $resultadoId = guardar_resultado_con_imagenes(
                $pacienteId,
                $citaId,
                $valores['nombre_estudio'],
                $valores['fecha_estudio'],
                $valores['observaciones'] !== '' ? $valores['observaciones'] : null,
                $usuario['cuenta_id'],
                $archivosValidados
            );
            header('Location: cargar-resultados.php?ok=' . $resultadoId);
            exit;
        } catch (Throwable $e) {
            $errores['general'] = 'No se pudo guardar el resultado. Intenta nuevamente.';
        }
    }
}

// Citas confirmadas propias de este profesional, agrupadas por
// paciente_id, para el filtrado UX-only del select de citas — el
// servidor re-valida paciente + profesional + estado 'confirmada'
// independientemente de lo que envie el cliente.
$stmtCitas = db()->prepare(
    "SELECT id, paciente_id, fecha_hora_solicitada, estudio
     FROM citas
     WHERE profesional_id = ? AND estado = 'confirmada'
     ORDER BY fecha_hora_solicitada DESC"
);
$stmtCitas->execute([$usuario['ref_id']]);
$citasFilas = $stmtCitas->fetchAll();

$citasPorPaciente = [];
foreach ($citasFilas as $cita) {
    $fecha = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $cita['fecha_hora_solicitada']);
    $etiqueta = ($fecha ? $fecha->format('d/m/Y H:i') : $cita['fecha_hora_solicitada'])
        . ' - ' . (ESTUDIOS[$cita['estudio']] ?? $cita['estudio']);
    $citasPorPaciente[(string) $cita['paciente_id']][] = [
        'id'      => (int) $cita['id'],
        'label'   => $etiqueta,
    ];
}
require __DIR__ . '/../views/cargar-resultados.php';
