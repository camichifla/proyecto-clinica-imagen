<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/recepcion.php';
require_once __DIR__ . '/../includes/agenda.php';
require_once __DIR__ . '/../includes/horarios.php';
require_once __DIR__ . '/../includes/validar_reserva.php';
require_once __DIR__ . '/../includes/mailer.php';

$usuario  = requerir_rol_json(['administrador', 'recepcionista']);
$sucRecep = filtro_sucursal_recepcion($usuario);

const MENSAJE_TURNO_NO_PROCESADO = 'No se pudo procesar la solicitud (puede que la cita ya no este activa).';
const MAX_FILAS_TURNOS = 500;

function error_json(int $codigo, string $mensaje): never
{
    http_response_code($codigo);
    echo json_encode(['ok' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

function fecha_valida(string $fecha): bool
{
    $f = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    return $f !== false && $f->format('Y-m-d') === $fecha;
}

function cita_gestionable(int $citaId): ?array
{
    $stmt = db()->prepare(
        "SELECT id, estado, sucursal_id, profesional_id, estudio, paciente_id, email_pendiente, fecha_hora_solicitada
           FROM citas WHERE id = ? AND estado IN ('pendiente','confirmada')"
    );
    $stmt->execute([$citaId]);
    return $stmt->fetch() ?: null;
}

function avisar_paciente(array $cita, string $estado, string $fechaHora, string $notas = ''): void
{
    $email = email_notificacion_cita($cita['paciente_id'], $cita['email_pendiente']);
    if ($email !== null) {
        enviar_notificacion_cita($email, $estado, $fechaHora, $notas);
    }
    avisar_medico_cita((int) $cita['id'], $estado, $fechaHora, $notas);
}

// A registered patient whose account (if any) isn't deactivated.
function paciente_reservable(int $pacienteId): bool
{
    $stmt = db()->prepare(
        "SELECT 1 FROM pacientes p WHERE p.id = ?
            AND NOT EXISTS (SELECT 1 FROM cuentas cu WHERE cu.rol = 'paciente' AND cu.ref_id = p.id AND cu.activo = 0)"
    );
    $stmt->execute([$pacienteId]);
    return (bool) $stmt->fetchColumn();
}

// Staff books on a patient's behalf (e.g. by phone): any active study, confirmed right away.
function crear_cita_staff(?int $sucRecep): never
{
    $pacienteId = filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT);
    $valores = [
        // A receptionist only books in their own branch.
        'sucursal_id'    => $sucRecep !== null ? (string) $sucRecep : (string) ($_POST['sucursal_id'] ?? ''),
        'estudio'        => (string) ($_POST['estudio'] ?? ''),
        'profesional_id' => (string) ($_POST['profesional_id'] ?? ''),
        'fecha'          => (string) ($_POST['fecha'] ?? ''),
        'hora'           => (string) ($_POST['hora'] ?? ''),
    ];
    [$fechaMin, $fechaMax] = rango_reserva();
    [$errores, $sucursalId, $profesionalId, $marcaTiempo, $estudio] = validar_reserva($valores, estudios_activos(), $fechaMin, $fechaMax, RANGO_MESES);
    if (!$pacienteId || !paciente_reservable($pacienteId)) {
        $errores['paciente_id'] = 'Elegí un paciente válido.';
    }
    if ($errores) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fechaHora = $marcaTiempo->format('Y-m-d H:i:s');
    $stmt = db()->prepare(
        "INSERT INTO citas (paciente_id, profesional_id, sucursal_id, fecha_hora_solicitada, fecha_hora_confirmada, estudio, estado)
         VALUES (?, ?, ?, ?, ?, ?, 'confirmada')"
    );
    try {
        $stmt->execute([$pacienteId, $profesionalId, $sucursalId, $fechaHora, $fechaHora, $estudio]);
    } catch (PDOException $e) {
        hora_ocupada_json($e);
    }
    $citaId = (int) db()->lastInsertId();

    registrar_evento('cita_crear_staff', 'cita=' . $citaId);
    try {
        $email = email_notificacion_cita($pacienteId, null);
        if ($email !== null) {
            enviar_notificacion_cita($email, 'confirmada', $fechaHora);
        }
    } catch (Throwable $e) {
        error_log('cita_crear_staff mail: ' . $e->getMessage());
    }
    echo json_encode(['ok' => true, 'codigo' => codigo_cita($citaId)], JSON_UNESCAPED_UNICODE);
    exit;
}

// Booking catalog plus the patients staff can book for; a receptionist only gets their own branch.
function catalogo_staff(?int $sucRecep): array
{
    $catalogo = catalogo_reserva(estudios_activos());
    if ($sucRecep !== null) {
        $catalogo['sucursalesOrden'] = array_values(array_filter($catalogo['sucursalesOrden'], static fn (array $s): bool => $s['id'] === $sucRecep));
        $catalogo['sucursales']      = array_intersect_key($catalogo['sucursales'], [(string) $sucRecep => true]);
    }
    $catalogo['pacientes'] = array_map(static fn (array $p): array => ['id' => (int) $p['id'], 'etiqueta' => $p['apellido'] . ', ' . $p['nombre'] . ' (' . $p['ci'] . ')'], db()->query(
        "SELECT id, nombre, apellido, ci FROM pacientes p
          WHERE NOT EXISTS (SELECT 1 FROM cuentas cu WHERE cu.rol = 'paciente' AND cu.ref_id = p.id AND cu.activo = 0)
          ORDER BY apellido, nombre"
    )->fetchAll());
    return $catalogo;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        crear_cita_staff($sucRecep);
    }
    $citaId = filter_input(INPUT_POST, 'cita_id', FILTER_VALIDATE_INT);

    if (!$citaId || !in_array($accion, ['cancelar', 'reprogramar'], true)) {
        error_json(400, MENSAJE_TURNO_NO_PROCESADO);
    }

    $cita = cita_gestionable($citaId);
    if ($cita === null || ($sucRecep !== null && (int) $cita['sucursal_id'] !== $sucRecep)) {
        error_json(409, MENSAJE_TURNO_NO_PROCESADO);
    }

    if ($accion === 'cancelar') {
        $prefijo = 'Cancelado por la clínica';
        $motivo  = mb_substr(trim((string) ($_POST['notas_admin'] ?? '')), 0, NOTAS_ADMIN_MAX - mb_strlen($prefijo) - 2);

        $stmt = db()->prepare(
            "UPDATE citas SET estado = 'cancelada', notas_admin = ?
              WHERE id = ? AND estado IN ('pendiente','confirmada')"
        );
        $stmt->execute([$motivo !== '' ? $prefijo . ': ' . $motivo : $prefijo, $citaId]);
        if ($stmt->rowCount() === 0) {
            error_json(409, MENSAJE_TURNO_NO_PROCESADO);
        }

        registrar_evento('cita_cancelar', 'cita=' . $citaId);
        avisar_paciente($cita, 'cancelada', $cita['fecha_hora_solicitada'], $motivo);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    [$fechaMin, $fechaMax] = rango_reserva();
    [$errores, , , $marcaTiempo] = validar_reserva([
        'sucursal_id'    => (string) $cita['sucursal_id'],
        'estudio'        => (string) $cita['estudio'],
        'profesional_id' => (string) $cita['profesional_id'],
        'fecha'          => (string) ($_POST['fecha'] ?? ''),
        'hora'           => (string) ($_POST['hora'] ?? ''),
    ], estudios_etiquetas(), $fechaMin, $fechaMax, RANGO_MESES);

    if ($errores) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $nueva = $marcaTiempo->format('Y-m-d H:i:s');
    $stmt  = db()->prepare(
        "UPDATE citas
            SET fecha_hora_solicitada = ?,
                fecha_hora_confirmada = IF(estado = 'confirmada', ?, fecha_hora_confirmada)
          WHERE id = ? AND estado IN ('pendiente','confirmada')"
    );
    try {
        $stmt->execute([$nueva, $nueva, $citaId]);
    } catch (PDOException $e) {
        hora_ocupada_json($e);
    }
    if ($stmt->rowCount() === 0) {
        error_json(409, MENSAJE_TURNO_NO_PROCESADO);
    }

    registrar_evento('cita_reprogramar', 'cita=' . $citaId);
    avisar_paciente($cita, 'reprogramada', $nueva);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['horas'])) {
    $citaId = filter_input(INPUT_GET, 'cita_id', FILTER_VALIDATE_INT);
    $fecha  = (string) ($_GET['fecha'] ?? '');
    $cita   = $citaId ? cita_gestionable($citaId) : null;
    [$fechaMin, $fechaMax] = rango_reserva();
    if ($cita === null || $cita['sucursal_id'] === null || !fecha_valida($fecha) || $fecha < $fechaMin || $fecha > $fechaMax) {
        error_json(422, 'Elegí una fecha válida para esa cita.');
    }

    $profesional = $cita['profesional_id'] !== null ? (int) $cita['profesional_id'] : null;
    $horas = horas_libres((int) $cita['sucursal_id'], $fecha, $profesional, $cita['estudio'], (int) $cita['id']);
    echo json_encode(['horas' => $horas], JSON_UNESCAPED_UNICODE);
    exit;
}

// Live free hours for a branch/day (optionally narrowed to a professional and/or study).
if (isset($_GET['disponibles'])) {
    $sucursalId    = $sucRecep ?? filter_input(INPUT_GET, 'sucursal_id', FILTER_VALIDATE_INT);
    $fecha         = (string) ($_GET['fecha'] ?? '');
    $profesionalId = filter_input(INPUT_GET, 'profesional_id', FILTER_VALIDATE_INT) ?: null;
    $estudio       = (string) ($_GET['estudio'] ?? '');
    [$fechaMin, $fechaMax] = rango_reserva();
    if (!$sucursalId || !fecha_valida($fecha) || $fecha < $fechaMin || $fecha > $fechaMax) {
        error_json(422, 'Elegí una sede y una fecha válidas.');
    }
    if ($estudio !== '' && !isset(estudios_activos()[$estudio])) {
        error_json(422, 'Elegí un estudio válido.');
    }
    $stmt = db()->prepare('SELECT 1 FROM sucursales WHERE id = ? AND activo = 1');
    $stmt->execute([$sucursalId]);
    if (!$stmt->fetchColumn()) {
        error_json(422, 'Elegí una sede válida.');
    }
    echo json_encode(['horas' => horas_libres($sucursalId, $fecha, $profesionalId, $estudio)], JSON_UNESCAPED_UNICODE);
    exit;
}

// Booking catalog only (Nueva cita page), without the list.
if (isset($_GET['catalogo'])) {
    echo json_encode(['reserva' => catalogo_staff($sucRecep), 'rangoReserva' => ['min' => rango_reserva()[0], 'max' => rango_reserva()[1]]], JSON_UNESCAPED_UNICODE);
    exit;
}

$hoy   = new DateTimeImmutable('today');
// Absent (first load): today .. +30 days. Sent empty (cleared with ✕): that end of the range stays open.
$desde = (string) ($_GET['desde'] ?? $hoy->format('Y-m-d'));
$hasta = (string) ($_GET['hasta'] ?? $hoy->modify('+30 days')->format('Y-m-d'));
if (($desde !== '' && !fecha_valida($desde)) || ($hasta !== '' && !fecha_valida($hasta)) || ($desde !== '' && $hasta !== '' && $hasta < $desde)) {
    error_json(422, 'El rango de fechas no es válido.');
}

$sucursales    = db()->query('SELECT id, nombre FROM sucursales ORDER BY nombre')->fetchAll();
$profesionales = db()->query('SELECT id, nombre, apellido FROM profesionales ORDER BY apellido, nombre')->fetchAll();

$sucursalId = filter_input(INPUT_GET, 'sucursal_id', FILTER_VALIDATE_INT) ?: null;
if ($sucRecep !== null) {
    $sucursalId = $sucRecep; // receptionist: always their own branch
    $sucursales = array_values(array_filter($sucursales, static fn (array $s): bool => (int) $s['id'] === $sucRecep));
}
$profesionalId = filter_input(INPUT_GET, 'profesional_id', FILTER_VALIDATE_INT) ?: null;
$estado     = (string) ($_GET['estado'] ?? '');
if ($estado !== '' && (!isset(ESTADOS_CITA[$estado]) || $estado === 'pendiente')) {
    error_json(422, 'Estado inválido.');
}

// Base for the listing and the totals (the state filter only applies to the listing).
$codigo = trim((string) ($_GET['codigo'] ?? ''));
if ($codigo !== '') {
    // A code that matches no cita (e.g. "CI-0" while still typing) just lists nothing, no error.
    $where  = 'c.id = ?';
    $params = [id_de_codigo($codigo) ?? 0];
} else {
    $where  = '1 = 1';
    $params = [];
    if ($desde !== '') {
        $where   .= ' AND c.fecha_hora_solicitada >= ?';
        $params[] = $desde . ' 00:00:00';
    }
    if ($hasta !== '') {
        $where   .= ' AND c.fecha_hora_solicitada < ?';
        $params[] = (new DateTimeImmutable($hasta))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    }
}
// Pending citas are handled in Solicitudes de cita, never listed or counted here.
$where .= " AND c.estado <> 'pendiente'";
if ($sucursalId !== null) {
    $where   .= ' AND c.sucursal_id = ?';
    $params[] = $sucursalId;
}
if ($profesionalId !== null) {
    $where   .= ' AND c.profesional_id = ?';
    $params[] = $profesionalId;
}
// Patient: every word must match the registered patient's name/surname/CI, or the pending booking's name/surname.
$palabras = preg_split('/\s+/', trim((string) ($_GET['paciente'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
foreach (array_slice($palabras, 0, 5) as $palabra) {
    $where .= ' AND (c.paciente_id IN (SELECT id FROM pacientes WHERE nombre LIKE ? OR apellido LIKE ? OR ci LIKE ?)
                     OR c.nombre_pendiente LIKE ? OR c.apellido_pendiente LIKE ?)';
    $like   = '%' . addcslashes($palabra, '\\%_') . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

$stmt = db()->prepare("SELECT c.estado, COUNT(*) FROM citas c WHERE {$where} GROUP BY c.estado");
$stmt->execute($params);
$totales = array_fill_keys(array_keys(ESTADOS_CITA), 0);
unset($totales['pendiente']);
foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$e, $n]) {
    $totales[$e] = (int) $n;
}
$totales['total'] = array_sum($totales);

$whereLista  = $where;
$paramsLista = $params;
if ($estado !== '') {
    $whereLista   .= ' AND c.estado = ?';
    $paramsLista[] = $estado;
}
// ponytail: row cap without pagination; add pagination if a range exceeds MAX_FILAS_TURNOS
$stmt = db()->prepare(SQL_CITAS_CON_ETIQUETAS . " WHERE {$whereLista} ORDER BY c.fecha_hora_solicitada ASC LIMIT " . (MAX_FILAS_TURNOS + 1));
$stmt->execute($paramsLista);
$filas    = $stmt->fetchAll();
$truncado = count($filas) > MAX_FILAS_TURNOS;

echo json_encode([
    'desde'         => $desde,
    'hasta'         => $hasta,
    'totales'       => $totales,
    'citas'         => array_map(static fn (array $c): array => [
        'codigo'      => codigo_cita((int) $c['id']),
        'fecha'       => substr($c['fecha_hora_solicitada'], 0, 10),
        'notas'       => $c['notas_admin'],
        'gestionable' => in_array($c['estado'], ['pendiente', 'confirmada'], true),
    ] + cita_agenda_json($c), array_slice($filas, 0, MAX_FILAS_TURNOS)),
    'sucursales'    => array_map(static fn (array $s): array => ['id' => (int) $s['id'], 'nombre' => $s['nombre']], $sucursales),
    'profesionales' => array_map(static fn (array $p): array => ['id' => (int) $p['id'], 'label' => $p['apellido'] . ', ' . $p['nombre']], $profesionales),
    'rangoReserva'  => ['min' => rango_reserva()[0], 'max' => rango_reserva()[1]],
    'notasAdminMax' => NOTAS_ADMIN_MAX,
    'truncado'      => $truncado,
], JSON_UNESCAPED_UNICODE);
