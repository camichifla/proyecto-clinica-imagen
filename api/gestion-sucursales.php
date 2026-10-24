<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/horarios.php';

requerir_rol_json(['administrador']);

const SUCURSAL_NOMBRE_MAX       = 80;
const SUCURSAL_DIRECCION_MAX    = 160;
const SUCURSAL_EQUIPAMIENTO_MAX = 1000;

function respuesta_sucursales(int $codigo, array $cuerpo): never
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

function coordenada(string $valor, float $limite): float|false|null
{
    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }
    if (!is_numeric($valor) || abs((float) $valor) > $limite) {
        return false;
    }
    return round((float) $valor, 6);
}

function turnos_de(int $id): array
{
    $stmt = db()->prepare(
        "SELECT dia_semana AS dia, TIME_FORMAT(hora_apertura, '%H:%i') AS apertura, TIME_FORMAT(hora_cierre, '%H:%i') AS cierre
           FROM sucursal_horarios WHERE sucursal_id = ? ORDER BY dia_semana, hora_apertura"
    );
    $stmt->execute([$id]);
    return array_map(static fn (array $t): array => ['dia' => (int) $t['dia'], 'apertura' => $t['apertura'], 'cierre' => $t['cierre']], $stmt->fetchAll());
}

function estudios_de(int $id): array
{
    $stmt = db()->prepare('SELECT estudio FROM sucursal_estudio WHERE sucursal_id = ? ORDER BY estudio');
    $stmt->execute([$id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    if (($_POST['accion'] ?? '') !== 'guardar') {
        respuesta_sucursales(400, ['ok' => false, 'error' => 'Acción inválida.']);
    }

    $idCrudo = (string) ($_POST['id'] ?? '');
    $id      = $idCrudo === '' ? null : filter_var($idCrudo, FILTER_VALIDATE_INT);
    if ($id === false || $id === 0) {
        respuesta_sucursales(400, ['ok' => false, 'error' => 'Sede inválida.']);
    }
    if ($id !== null) {
        $stmt = db()->prepare('SELECT 1 FROM sucursales WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) {
            respuesta_sucursales(404, ['ok' => false, 'error' => 'Sede no encontrada.']);
        }
    }

    $errores      = [];
    $nombre       = trim((string) ($_POST['nombre'] ?? ''));
    $direccion    = trim((string) ($_POST['direccion'] ?? ''));
    $equipamiento = trim(str_replace("\r\n", "\n", (string) ($_POST['equipamiento'] ?? '')));
    $activo       = ($_POST['activo'] ?? '') === '1' ? 1 : 0;

    if ($nombre === '' || mb_strlen($nombre) > SUCURSAL_NOMBRE_MAX) {
        $errores['nombre'] = 'El nombre es obligatorio (máximo ' . SUCURSAL_NOMBRE_MAX . ' caracteres).';
    } else {
        $stmt = db()->prepare('SELECT 1 FROM sucursales WHERE nombre = ? AND id <> ?');
        $stmt->execute([$nombre, $id ?? 0]);
        if ($stmt->fetchColumn()) {
            $errores['nombre'] = 'Ya existe una sede con ese nombre.';
        }
    }
    if (mb_strlen($direccion) > SUCURSAL_DIRECCION_MAX) {
        $errores['direccion'] = 'La dirección admite hasta ' . SUCURSAL_DIRECCION_MAX . ' caracteres.';
    }
    if (mb_strlen($equipamiento) > SUCURSAL_EQUIPAMIENTO_MAX) {
        $errores['equipamiento'] = 'El equipamiento admite hasta ' . SUCURSAL_EQUIPAMIENTO_MAX . ' caracteres.';
    }

    $latitud  = coordenada((string) ($_POST['latitud'] ?? ''), 90);
    $longitud = coordenada((string) ($_POST['longitud'] ?? ''), 180);
    if ($latitud === false) {
        $errores['latitud'] = 'La latitud debe estar entre -90 y 90.';
    }
    if ($longitud === false) {
        $errores['longitud'] = 'La longitud debe estar entre -180 y 180.';
    }
    if (!$errores && ($latitud === null) !== ($longitud === null)) {
        $errores['latitud'] = 'Completa latitud y longitud, o deja ambas vacias.';
    }

    $enviados = $_POST['estudios'] ?? [];
    $claves   = is_array($enviados) ? array_values(array_unique(array_map('strval', $enviados))) : [];
    if (array_diff($claves, array_keys(estudios_etiquetas()))) {
        $errores['estudios'] = 'Elegí estudios validos.';
    }

    $turnos = validar_turnos($_POST['horarios'] ?? '');
    if (is_string($turnos)) {
        $errores['horarios'] = $turnos;
    }

    if ($errores) {
        respuesta_sucursales(422, ['ok' => false, 'errores' => $errores]);
    }

    $esAlta            = $id === null;
    $horariosCambiaron = false;
    try {
        db()->beginTransaction();
        if ($esAlta) {
            db()->prepare('INSERT INTO sucursales (nombre, direccion, activo, equipamiento, latitud, longitud) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$nombre, $direccion !== '' ? $direccion : null, $activo, $equipamiento !== '' ? $equipamiento : null, $latitud, $longitud]);
            $id = (int) db()->lastInsertId();
        } else {
            $horariosCambiaron = turnos_de($id) !== $turnos;
            db()->prepare('UPDATE sucursales SET nombre = ?, direccion = ?, activo = ?, equipamiento = ?, latitud = ?, longitud = ? WHERE id = ?')
                ->execute([$nombre, $direccion !== '' ? $direccion : null, $activo, $equipamiento !== '' ? $equipamiento : null, $latitud, $longitud, $id]);
            db()->prepare('DELETE FROM sucursal_horarios WHERE sucursal_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM sucursal_estudio WHERE sucursal_id = ?')->execute([$id]);
        }
        $stmt = db()->prepare('INSERT INTO sucursal_horarios (sucursal_id, dia_semana, hora_apertura, hora_cierre) VALUES (?, ?, ?, ?)');
        foreach ($turnos as $t) {
            $stmt->execute([$id, $t['dia'], $t['apertura'] . ':00', $t['cierre'] . ':00']);
        }
        $stmt = db()->prepare('INSERT INTO sucursal_estudio (sucursal_id, estudio) VALUES (?, ?)');
        foreach ($claves as $clave) {
            $stmt->execute([$id, $clave]);
        }
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        error_log('gestion-sucursales: ' . $e->getMessage());
        respuesta_sucursales(500, ['ok' => false, 'error' => 'No se pudo guardar la sede. Intenta nuevamente.']);
    }

    registrar_evento($esAlta ? 'sucursal_alta' : 'sucursal_cambio', 'sucursal=' . $id . ' activo=' . $activo);
    if ($horariosCambiaron) {
        registrar_evento('sucursal_horarios', 'sucursal=' . $id);
    }
    echo json_encode(['ok' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
    exit;
}

$filas = db()->query('SELECT id, nombre, direccion, activo, equipamiento, latitud, longitud FROM sucursales ORDER BY nombre')->fetchAll();
echo json_encode([
    'max'        => ['nombre' => SUCURSAL_NOMBRE_MAX, 'direccion' => SUCURSAL_DIRECCION_MAX, 'equipamiento' => SUCURSAL_EQUIPAMIENTO_MAX],
    'estudios'   => array_map(static fn (array $e): array => ['clave' => $e['clave'], 'etiqueta' => $e['etiqueta'], 'activo' => (int) $e['activo'] === 1], estudios_catalogo()),
    'sucursales' => array_map(static fn (array $s): array => [
        'id'           => (int) $s['id'],
        'nombre'       => $s['nombre'],
        'direccion'    => $s['direccion'],
        'activo'       => (int) $s['activo'] === 1,
        'equipamiento' => $s['equipamiento'],
        'latitud'      => $s['latitud'] === null ? null : (float) $s['latitud'],
        'longitud'     => $s['longitud'] === null ? null : (float) $s['longitud'],
        'estudios'     => estudios_de((int) $s['id']),
        'horarios'     => turnos_de((int) $s['id']),
    ], $filas),
], JSON_UNESCAPED_UNICODE);
