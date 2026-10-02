<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/resultados_upload.php';
require_once __DIR__ . '/../includes/pacientes_profesional.php';

$usuario = requerir_rol_json(['profesional']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);

    $fechaMaxEstudio = (new DateTimeImmutable('today'))->format('Y-m-d');

    $pacienteIdPost = (string) ($_POST['paciente_id'] ?? '');
    $citaIdPost     = (string) ($_POST['cita_id'] ?? '');
    $nombreEstudio  = trim((string) ($_POST['nombre_estudio'] ?? ''));
    $fechaEstudio   = (string) ($_POST['fecha_estudio'] ?? '');
    $observaciones  = trim((string) ($_POST['observaciones'] ?? ''));

    $errores = [];

    $pacienteId = filter_var($pacienteIdPost, FILTER_VALIDATE_INT);
    if (!$pacienteId) {
        $errores['paciente_id'] = 'Elegi un paciente.';
    } elseif (!paciente_de_profesional($usuario['ref_id'], $pacienteId)) {
        $errores['paciente_id'] = 'Elegi un paciente valido.';
        $pacienteId = null;
    }

    $citaId = null;
    if ($citaIdPost !== '') {
        $citaIdCandidato = filter_var($citaIdPost, FILTER_VALIDATE_INT);
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

    if ($nombreEstudio === '') {
        $errores['nombre_estudio'] = 'El nombre del estudio es obligatorio.';
    } elseif (mb_strlen($nombreEstudio) > 120) {
        $errores['nombre_estudio'] = 'El nombre del estudio no puede superar 120 caracteres.';
    }

    if ($fechaEstudio === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEstudio)) {
        $errores['fecha_estudio'] = 'Elegi una fecha valida.';
    } else {
        [$anioEstudio, $mesEstudio, $diaEstudio] = array_map('intval', explode('-', $fechaEstudio));
        if (!checkdate($mesEstudio, $diaEstudio, $anioEstudio)) {
            $errores['fecha_estudio'] = 'Elegi una fecha valida.';
        } elseif ($fechaEstudio > $fechaMaxEstudio) {
            $errores['fecha_estudio'] = 'La fecha del estudio no puede ser futura.';
        }
    }

    $archivosValidados = [];
    $archivos = normalizar_archivos($_FILES['imagenes'] ?? null);
    if (!$archivos) {
        $errores['imagenes'] = 'Subi al menos una imagen.';
    } else {
        foreach ($archivos as $archivo) {
            $extension   = null;
            $errorImagen = validar_imagen($archivo, $extension);
            if ($errorImagen !== null) {
                $errores['imagenes'] = $errorImagen;
                break;
            }
            $archivo['extension'] = $extension;
            $archivosValidados[]  = $archivo;
        }
    }

    if ($errores) {
        echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $resultadoId = guardar_resultado_con_imagenes(
            $pacienteId,
            $citaId,
            $nombreEstudio,
            $fechaEstudio,
            $observaciones !== '' ? $observaciones : null,
            $usuario['cuenta_id'],
            $archivosValidados
        );
        echo json_encode(['ok' => true, 'redirect' => 'cargar-resultados.html?ok=' . $resultadoId], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'errores' => ['general' => 'No se pudo guardar el resultado. Intenta nuevamente.']], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$pacientes = pacientes_de_profesional($usuario['ref_id']);

$stmtCitas = db()->prepare(
    "SELECT id, paciente_id, fecha_hora_solicitada, estudio
     FROM citas
     WHERE profesional_id = ? AND estado = 'confirmada'
     ORDER BY fecha_hora_solicitada DESC"
);
$stmtCitas->execute([$usuario['ref_id']]);

$citasPorPaciente = [];
foreach ($stmtCitas->fetchAll() as $cita) {
    $fecha    = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $cita['fecha_hora_solicitada']);
    $etiqueta = ($fecha ? $fecha->format('d/m/Y H:i') : $cita['fecha_hora_solicitada'])
        . ' - ' . (ESTUDIOS[$cita['estudio']] ?? $cita['estudio']);
    $citasPorPaciente[(string) $cita['paciente_id']][] = [
        'id'    => (int) $cita['id'],
        'label' => $etiqueta,
    ];
}

echo json_encode([
    'pacientes'          => $pacientes,
    'citas_por_paciente' => $citasPorPaciente,
], JSON_UNESCAPED_UNICODE);
