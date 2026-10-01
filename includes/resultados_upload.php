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
