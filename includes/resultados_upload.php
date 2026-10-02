<?php
// includes/resultados_upload.php — shared image-upload validation for a
// resultado's images, used by both enviar-resultados.php (administrador,
// any patient) and cargar-resultados.php (profesional, own patients only).
// Only the patient-scoping/cita-ownership logic differs between those two
// pages; the upload mechanics below are identical, so they live here once.

const MAX_TAMANIO_IMAGEN = 10 * 1024 * 1024; // 10MB por archivo

// Canonical extension derived from the REAL sniffed MIME type — never the
// client-reported Content-Type and never the original filename's extension.
const MIME_A_EXTENSION = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

const STORAGE_RESULTADOS_DIR = __DIR__ . '/../storage/resultados';

/**
 * Reshapes PHP's parallel-array $_FILES['imagenes'] structure into a list
 * of per-file associative arrays. Entries with UPLOAD_ERR_NO_FILE (an
 * empty file input slot) are dropped rather than treated as a real file.
 *
 * @return array<int,array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
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
 * Validates one normalized upload entry. Returns an error message, or
 * null when valid — in which case $extension is filled with the
 * canonical extension for the sniffed MIME type.
 */
function validar_imagen(array $archivo, ?string &$extension): ?string
{
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return 'Error al subir "' . $archivo['name'] . '".';
    }
    if (!is_uploaded_file($archivo['tmp_name'])) {
        return 'Error al subir "' . $archivo['name'] . '".';
    }
    if ($archivo['size'] > MAX_TAMANIO_IMAGEN) {
        return '"' . $archivo['name'] . '" supera el tamano maximo de 10MB.';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? finfo_file($finfo, $archivo['tmp_name']) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!$mime || !isset(MIME_A_EXTENSION[$mime])) {
        return '"' . $archivo['name'] . '" no es una imagen valida (se aceptan JPG, PNG o WEBP).';
    }

    $extension = MIME_A_EXTENSION[$mime];
    return null;
}

/**
 * Inserts a resultado plus its already-validated images in one transaction
 * (INSERT resultados, move each file, INSERT resultado_imagenes). On any
 * failure it rolls back, unlinks every file already moved in this attempt
 * (no orphaned files), and rethrows — the caller only needs a try/catch to
 * turn that into a user-facing error. Identical between cargar-resultados.php
 * (profesional) and enviar-resultados.php (administrador); only the
 * paciente/cita ownership check before this call differs between them.
 *
 * @param array<int,array{tmp_name:string,name:string,extension:string}> $archivosValidados
 */
function guardar_resultado_con_imagenes(
    int $pacienteId,
    ?int $citaId,
    string $nombreEstudio,
    string $fechaEstudio,
    ?string $observaciones,
    int $creadoPor,
    array $archivosValidados
): int {
    $archivosMovidos = [];
    try {
        db()->beginTransaction();

        $stmt = db()->prepare(
            'INSERT INTO resultados (paciente_id, cita_id, nombre_estudio, fecha_estudio, observaciones, creado_por)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$pacienteId, $citaId, $nombreEstudio, $fechaEstudio, $observaciones, $creadoPor]);
        $resultadoId = (int) db()->lastInsertId();

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
 * Validates a POST of the "cargar resultado" form, saves it and echoes the
 * JSON response. Shared by cargar-resultados.php (profesional) and
 * enviar-resultados.php (administrador). The caller does the csrf_check.
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
    $observaciones  = trim((string) ($_POST['observaciones'] ?? ''));

    $errores = [];

    $pacienteId = filter_var($pacienteIdPost, FILTER_VALIDATE_INT);
    if (!$pacienteId) {
        $errores['paciente_id'] = 'Elegi un paciente.';
    } elseif (!$pacienteValido($pacienteId)) {
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
            $archivosValidados
        );
        echo json_encode(['ok' => true, 'redirect' => $redirectBase . '?ok=' . $resultadoId], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'errores' => ['general' => 'No se pudo guardar el resultado. Intenta nuevamente.']], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Groups confirmed-cita rows (id, paciente_id, fecha_hora_solicitada, estudio)
 * into the [paciente_id => [{id,label}]] map the upload forms consume.
 */
function citas_por_paciente(array $filas): array
{
    $porPaciente = [];
    foreach ($filas as $cita) {
        $fecha    = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $cita['fecha_hora_solicitada']);
        $etiqueta = ($fecha ? $fecha->format('d/m/Y H:i') : $cita['fecha_hora_solicitada'])
            . ' - ' . (ESTUDIOS[$cita['estudio']] ?? $cita['estudio']);
        $porPaciente[(string) $cita['paciente_id']][] = [
            'id'    => (int) $cita['id'],
            'label' => $etiqueta,
        ];
    }
    return $porPaciente;
}
