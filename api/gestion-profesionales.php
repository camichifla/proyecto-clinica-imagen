<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/horarios.php';

requerir_rol_json(['administrador']);

function respuesta_profesionales(int $codigo, array $cuerpo): never
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

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
    if (($_POST['accion'] ?? '') !== 'guardar') {
        respuesta_profesionales(400, ['ok' => false, 'error' => 'Acción inválida.']);
    }

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        respuesta_profesionales(400, ['ok' => false, 'error' => 'Profesional inválido.']);
    }
    $stmt = db()->prepare('SELECT 1 FROM profesionales WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) {
        respuesta_profesionales(404, ['ok' => false, 'error' => 'Profesional no encontrado.']);
    }

    $errores = [];

    $enviadas   = $_POST['sucursales'] ?? [];
    $sucursales = is_array($enviadas) ? array_values(array_unique(array_map('strval', $enviadas))) : [];
    $validas    = array_map('strval', db()->query('SELECT id FROM sucursales')->fetchAll(PDO::FETCH_COLUMN));
    if (array_diff($sucursales, $validas)) {
        $errores['sucursales'] = 'Elegí sedes válidas.';
    }

    // Estudios activos; los inactivos que ya tenia se pueden conservar pero no sumar.
    $enviados = $_POST['especializacion'] ?? [];
    $claves   = is_array($enviados) ? array_values(array_unique(array_map('strval', $enviados))) : [];
    $permitidos = array_merge(
        array_keys(estudios_activos()),
        columna_de('SELECT especializacion FROM profesional_especializacion WHERE profesional_id = ?', $id)
    );
    if (!$claves) {
        $errores['especializacion'] = 'Elegí al menos una especialización.';
    } elseif (array_diff($claves, $permitidos)) {
        $errores['especializacion'] = 'Elegí especializaciones válidas.';
    }

    $turnos = validar_turnos($_POST['horarios'] ?? '');
    if (is_string($turnos)) {
        $errores['horarios'] = $turnos;
    }

    if ($errores) {
        respuesta_profesionales(422, ['ok' => false, 'errores' => $errores]);
    }

    $horariosCambiaron = turnos_propios_de($id) !== $turnos;
    try {
        db()->beginTransaction();
        foreach (['profesional_sucursal', 'profesional_especializacion', 'profesional_horarios'] as $tabla) {
            db()->prepare("DELETE FROM {$tabla} WHERE profesional_id = ?")->execute([$id]);
        }
        $stmt = db()->prepare('INSERT INTO profesional_sucursal (profesional_id, sucursal_id) VALUES (?, ?)');
        foreach ($sucursales as $sucursalId) {
            $stmt->execute([$id, (int) $sucursalId]);
        }
        $stmt = db()->prepare('INSERT INTO profesional_especializacion (profesional_id, especializacion) VALUES (?, ?)');
        foreach ($claves as $clave) {
            $stmt->execute([$id, $clave]);
        }
        $stmt = db()->prepare('INSERT INTO profesional_horarios (profesional_id, dia_semana, hora_apertura, hora_cierre) VALUES (?, ?, ?, ?)');
        foreach ($turnos as $t) {
            $stmt->execute([$id, $t['dia'], $t['apertura'] . ':00', $t['cierre'] . ':00']);
        }
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        error_log('gestion-profesionales: ' . $e->getMessage());
        respuesta_profesionales(500, ['ok' => false, 'error' => 'No se pudo guardar el profesional. Intenta nuevamente.']);
    }

    registrar_evento('profesional_cambio', 'profesional=' . $id);
    if ($horariosCambiaron) {
        registrar_evento('profesional_horarios', 'profesional=' . $id);
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$filas = db()->query(
    "SELECT p.id, p.nombre, p.apellido, c.email, c.activo
       FROM profesionales p
       LEFT JOIN cuentas c ON c.rol = 'profesional' AND c.ref_id = p.id
      ORDER BY p.apellido, p.nombre"
)->fetchAll();

echo json_encode([
    'estudios'   => array_map(static fn (array $e): array => ['clave' => $e['clave'], 'etiqueta' => $e['etiqueta'], 'activo' => (int) $e['activo'] === 1], estudios_catalogo()),
    'sucursales' => array_map(static fn (array $s): array => ['id' => (int) $s['id'], 'nombre' => $s['nombre'], 'activo' => (int) $s['activo'] === 1],
        db()->query('SELECT id, nombre, activo FROM sucursales ORDER BY nombre')->fetchAll()),
    'profesionales' => array_map(static fn (array $p): array => [
        'id'              => (int) $p['id'],
        'nombre'          => $p['nombre'],
        'apellido'        => $p['apellido'],
        'email'           => $p['email'],
        'cuenta_activa'   => $p['activo'] === null ? null : (int) $p['activo'] === 1,
        'sucursales'      => array_map('intval', columna_de('SELECT sucursal_id FROM profesional_sucursal WHERE profesional_id = ? ORDER BY sucursal_id', (int) $p['id'])),
        'especializacion' => columna_de('SELECT especializacion FROM profesional_especializacion WHERE profesional_id = ? ORDER BY especializacion', (int) $p['id']),
        'horarios'        => turnos_propios_de((int) $p['id']),
    ], $filas),
], JSON_UNESCAPED_UNICODE);
