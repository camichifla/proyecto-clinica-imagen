<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
$usuario = requerir_rol(['administrador']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/resultados_upload.php';

$hoy             = new DateTimeImmutable('today');
$fechaMaxEstudio = $hoy->format('Y-m-d');

$errores = [];
$valores = ['paciente_id' => '', 'cita_id' => '', 'nombre_estudio' => '', 'fecha_estudio' => '', 'observaciones' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);

    $valores['paciente_id']    = (string) ($_POST['paciente_id'] ?? '');
    $valores['cita_id']        = (string) ($_POST['cita_id'] ?? '');
    $valores['nombre_estudio'] = trim((string) ($_POST['nombre_estudio'] ?? ''));
    $valores['fecha_estudio']  = (string) ($_POST['fecha_estudio'] ?? '');
    $valores['observaciones']  = trim((string) ($_POST['observaciones'] ?? ''));

    // --- Paciente: must exist ---
    $pacienteId = filter_var($valores['paciente_id'], FILTER_VALIDATE_INT);
    if (!$pacienteId) {
        $errores['paciente_id'] = 'Elegi un paciente.';
    } else {
        $stmt = db()->prepare('SELECT id FROM pacientes WHERE id = ?');
        $stmt->execute([$pacienteId]);
        if (!$stmt->fetch()) {
            $errores['paciente_id'] = 'Elegi un paciente valido.';
            $pacienteId = null;
        }
    }

    // --- Cita opcional: never trust the client filter — if a cita_id is
    // submitted it MUST belong to the selected paciente AND be confirmada,
    // otherwise the whole submission is rejected. ---
    $citaId = null;
    if ($valores['cita_id'] !== '') {
        $citaIdCandidato = filter_var($valores['cita_id'], FILTER_VALIDATE_INT);
        if (!$citaIdCandidato) {
            $errores['cita_id'] = 'Cita invalida.';
        } elseif (!$pacienteId) {
            $errores['cita_id'] = 'Elegi un paciente valido primero.';
        } else {
            $stmt = db()->prepare("SELECT id FROM citas WHERE id = ? AND paciente_id = ? AND estado = 'confirmada'");
            $stmt->execute([$citaIdCandidato, $pacienteId]);
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
            header('Location: enviar-resultados.php?ok=' . $resultadoId);
            exit;
        } catch (Throwable $e) {
            $errores['general'] = 'No se pudo guardar el resultado. Intenta nuevamente.';
        }
    }
}

$pacientes = db()->query('SELECT id, nombre, apellido, ci FROM pacientes ORDER BY apellido, nombre')->fetchAll();

$citasFilas = db()->query(
    "SELECT id, paciente_id, fecha_hora_solicitada, estudio
     FROM citas
     WHERE estado = 'confirmada'
     ORDER BY fecha_hora_solicitada DESC"
)->fetchAll();

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
require __DIR__ . '/../views/enviar-resultados.php';
