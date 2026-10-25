<?php
// Only the patient-scoping/cita-ownership logic differs between the two roles; the upload mechanics below are identical, so they live here once.

require_once __DIR__ . '/estudios.php';
require_once __DIR__ . '/mailer.php';

const MAX_TAMANIO_IMAGEN = 10 * 1024 * 1024;
const MAX_TAMANIO_INFORME = 10 * 1024 * 1024; // 10MB, one PDF per result

// Canonical extension derived from the REAL sniffed MIME type — never the
// client-reported Content-Type and never the original filename's extension.
const MIME_A_EXTENSION = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

const STORAGE_RESULTADOS_DIR = __DIR__ . '/../storage/resultados';

/** Reshapes PHP's parallel-array $_FILES['imagenes'] into per-file arrays; UPLOAD_ERR_NO_FILE slots are dropped. */
function normalizar_archivos(?array $archivos): array
{
    if (!$archivos || !isset($archivos['name']) || !is_array($archivos['name'])) {
        return [];
    }
    $normalizados = [];
    foreach ($archivos['name'] as $i => $nombre) {
        if ((int) $archivos['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $normalizados[] = [
            'name'     => (string) $nombre,
            'type'     => (string) $archivos['type'][$i],
            'tmp_name' => (string) $archivos['tmp_name'][$i],
            'error'    => (int) $archivos['error'][$i],
            'size'     => (int) $archivos['size'][$i],
        ];
    }
    return $normalizados;
}

/**
 * Validates against a MIME allowlist using the real sniffed type, never the client's. Returns an error message,
 * or null when valid.
 *
 * @param array<string,string> $permitidos mime => extension
 */
function validar_subida(array $archivo, int $maxBytes, array $permitidos, string $mensajeTipo, ?string &$extension): ?string
{
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return 'Error al subir "' . $archivo['name'] . '".';
    }
    if (!is_uploaded_file($archivo['tmp_name'])) {
        return 'Error al subir "' . $archivo['name'] . '".';
    }
    if ($archivo['size'] > $maxBytes) {
        return '"' . $archivo['name'] . '" supera el tamaño máximo de ' . intdiv($maxBytes, 1024 * 1024) . 'MB.';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? finfo_file($finfo, $archivo['tmp_name']) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!$mime || !isset($permitidos[$mime])) {
        return '"' . $archivo['name'] . '" ' . $mensajeTipo;
    }

    $extension = $permitidos[$mime];
    return null;
}

function validar_imagen(array $archivo, ?string &$extension): ?string
{
    return validar_subida($archivo, MAX_TAMANIO_IMAGEN, MIME_A_EXTENSION, 'no es una imagen válida (se aceptan JPG, PNG o WEBP).', $extension);
}

function validar_informe(array $archivo, ?string &$extension): ?string
{
    return validar_subida($archivo, MAX_TAMANIO_INFORME, ['application/pdf' => 'pdf'], 'no es un PDF válido.', $extension);
}

/**
 * Inserts a resultado plus its validated images in one transaction. On failure it rolls back, unlinks files already
 * moved (no orphans) and rethrows.
 *
 * @param array<int,array{tmp_name:string,name:string,extension:string}> $archivosValidados
 * @param ?array{tmp_name:string,name:string,extension:string}            $informe  optional PDF report
 */
function guardar_resultado_con_imagenes(
    int $pacienteId,
    ?int $citaId,
    string $nombreEstudio,
    string $fechaEstudio,
    ?string $observaciones,
    int $creadoPor,
    array $archivosValidados,
    ?array $informe = null
): int {
    $archivosMovidos = [];
    try {
        db()->beginTransaction();

        $informeArchivo  = $informe ? bin2hex(random_bytes(16)) . '.' . $informe['extension'] : null;
        $informeOriginal = $informe ? mb_substr(basename($informe['name']), 0, 255) : null;

        $stmt = db()->prepare(
            'INSERT INTO resultados (paciente_id, cita_id, nombre_estudio, fecha_estudio, observaciones, informe_archivo, informe_nombre_original, creado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$pacienteId, $citaId, $nombreEstudio, $fechaEstudio, $observaciones, $informeArchivo, $informeOriginal, $creadoPor]);
        $resultadoId = (int) db()->lastInsertId();

        if ($informe) {
            $destinoInforme = STORAGE_RESULTADOS_DIR . '/' . $informeArchivo;
            if (!move_uploaded_file($informe['tmp_name'], $destinoInforme)) {
                throw new RuntimeException('No se pudo guardar el informe.');
            }
            $archivosMovidos[] = $destinoInforme;
        }

        $stmtImagen = db()->prepare(
            'INSERT INTO resultado_imagenes (resultado_id, nombre_archivo, nombre_original)
             VALUES (?, ?, ?)'
        );
        foreach ($archivosValidados as $archivo) {
            // Random/unpredictable on-disk name — the client's original
            // filename is NEVER trusted or reused for storage.
            $nombreGenerado = bin2hex(random_bytes(16)) . '.' . $archivo['extension'];
            $destino        = STORAGE_RESULTADOS_DIR . '/' . $nombreGenerado;

            if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
                throw new RuntimeException('No se pudo guardar la imagen "' . $archivo['name'] . '".');
            }
            $archivosMovidos[] = $destino;

            $nombreOriginal = mb_substr(basename($archivo['name']), 0, 255);
            $stmtImagen->execute([$resultadoId, $nombreGenerado, $nombreOriginal]);
        }

        db()->commit();
        registrar_evento('resultado_carga', 'resultado=' . $resultadoId);
        return $resultadoId;
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        foreach ($archivosMovidos as $ruta) {
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }
        throw $e;
    }
}

/**
 * Validates and saves a POST of the "cargar resultado" form and echoes the JSON. The caller does the csrf_check.
 *
 * @param callable(int):bool $pacienteValido  whether the caller may use this paciente
 * @param ?int               $profesionalId   when set, the cita must belong to this profesional too
 */
function procesar_post_resultado(array $usuario, callable $pacienteValido, ?int $profesionalId, string $redirectBase): void
{
    $fechaMaxEstudio = (new DateTimeImmutable('today'))->format('Y-m-d');

    $pacienteIdPost = (string) ($_POST['paciente_id'] ?? '');
    $citaIdPost     = (string) ($_POST['cita_id'] ?? '');
    $nombreEstudio  = trim((string) ($_POST['nombre_estudio'] ?? ''));
    $fechaEstudio   = (string) ($_POST['fecha_estudio'] ?? '');
    $observaciones  = mb_substr(trim((string) ($_POST['observaciones'] ?? '')), 0, 2000);

    $errores = [];

    $pacienteId = filter_var($pacienteIdPost, FILTER_VALIDATE_INT);
    if (!$pacienteId) {
        $errores['paciente_id'] = 'Elegí un paciente.';
    } elseif (!$pacienteValido($pacienteId)) {
        $errores['paciente_id'] = 'Elegí un paciente válido.';
        $pacienteId = null;
    }

    $citaId = null;
    if ($citaIdPost !== '') {
        $citaIdCandidato = filter_var($citaIdPost, FILTER_VALIDATE_INT);
        if (!$citaIdCandidato) {
            $errores['cita_id'] = 'Cita inválida.';
        } elseif (!$pacienteId) {
            $errores['cita_id'] = 'Elegí un paciente válido primero.';
        } else {
            $sql    = "SELECT id FROM citas WHERE id = ? AND paciente_id = ? AND estado = 'confirmada'";
            $params = [$citaIdCandidato, $pacienteId];
            if ($profesionalId !== null) {
                $sql     .= ' AND profesional_id = ?';
                $params[] = $profesionalId;
            }
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
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
        $errores['fecha_estudio'] = 'Elegí una fecha válida.';
    } else {
        [$anioEstudio, $mesEstudio, $diaEstudio] = array_map('intval', explode('-', $fechaEstudio));
        if (!checkdate($mesEstudio, $diaEstudio, $anioEstudio)) {
            $errores['fecha_estudio'] = 'Elegí una fecha válida.';
        } elseif ($fechaEstudio > $fechaMaxEstudio) {
            $errores['fecha_estudio'] = 'La fecha del estudio no puede ser futura.';
        }
    }

    $archivosValidados = [];
    $archivos = normalizar_archivos($_FILES['imagenes'] ?? null);

    // A single optional PDF (input name="informe"): wrapped into the list format.
    $informe = null;
    $informes = isset($_FILES['informe']) && !is_array($_FILES['informe']['name'] ?? null)
        ? normalizar_archivos(array_map(static fn ($v) => [$v], $_FILES['informe']))
        : [];
    if ($informes) {
        $extensionInforme = null;
        $errorInforme = validar_informe($informes[0], $extensionInforme);
        if ($errorInforme !== null) {
            $errores['informe'] = $errorInforme;
        } else {
            $informe = $informes[0] + ['extension' => $extensionInforme];
        }
    }

    if (!$archivos && !$informe && !isset($errores['informe'])) {
        $errores['imagenes'] = 'Subi al menos una imagen o un informe PDF.';
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
        return;
    }

    try {
        $resultadoId = guardar_resultado_con_imagenes(
            $pacienteId,
            $citaId,
            $nombreEstudio,
            $fechaEstudio,
            $observaciones !== '' ? $observaciones : null,
            $usuario['cuenta_id'],
            $archivosValidados,
            $informe
        );
        notificar_resultado_listo($pacienteId);
        echo json_encode(['ok' => true, 'redirect' => $redirectBase . '?ok=' . $resultadoId], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'errores' => ['general' => 'No se pudo guardar el resultado. Intenta nuevamente.']], JSON_UNESCAPED_UNICODE);
    }
}

/** One "result ready" email per saved resultado. Failures are swallowed: the resultado is already committed. */
function notificar_resultado_listo(int $pacienteId): void
{
    try {
        $email = email_notificacion_cita($pacienteId, null);
        if ($email !== null) {
            enviar_aviso_resultado($email, APP_URL . '/ver-resultados.html');
        }
    } catch (Throwable $e) {
        error_log('notificar_resultado_listo: ' . $e->getMessage());
    }
}

/** Confirmed-cita rows to the [paciente_id => [{id,label}]] map the upload forms consume. */
function citas_por_paciente(array $filas): array
{
    $porPaciente = [];
    foreach ($filas as $cita) {
        $fecha    = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $cita['fecha_hora_solicitada']);
        $etiqueta = ($fecha ? $fecha->format('d/m/Y H:i') : $cita['fecha_hora_solicitada'])
            . ' - ' . (estudios_etiquetas()[$cita['estudio']] ?? $cita['estudio']);
        $porPaciente[(string) $cita['paciente_id']][] = [
            'id'    => (int) $cita['id'],
            'label' => $etiqueta,
        ];
    }
    return $porPaciente;
}
