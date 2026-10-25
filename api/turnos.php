<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/agenda.php';
require_once __DIR__ . '/../includes/horarios.php';
require_once __DIR__ . '/../includes/validar_reserva.php';
require_once __DIR__ . '/../includes/mailer.php';

requerir_rol_json(['administrador', 'recepcionista']);

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
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $accion = $_POST['accion'] ?? '';
    $citaId = filter_input(INPUT_POST, 'cita_id', FILTER_VALIDATE_INT);

    if (!$citaId || !in_array($accion, ['cancelar', 'reprogramar'], true)) {
        error_json(400, MENSAJE_TURNO_NO_PROCESADO);
    }

    $cita = cita_gestionable($citaId);
    if ($cita === null) {
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

    $dia = (int) (new DateTimeImmutable($fecha))->format('N');
    $stmt = db()->prepare('SELECT hora_apertura, hora_cierre FROM sucursal_horarios WHERE sucursal_id = ? AND dia_semana = ? ORDER BY hora_apertura');
    $stmt->execute([$cita['sucursal_id'], $dia]);
    $slots = slots_de_turnos($stmt->fetchAll());

    $ocupadas = horas_ocupadas((int) $cita['sucursal_id'], $fecha, (int) $cita['profesional_id'], $cita['estudio'], (int) $cita['id']);

    $ahora = new DateTimeImmutable();
    $horas = array_values(array_filter(
        $slots,
        static fn (string $h): bool => !in_array($h, $ocupadas, true) && new DateTimeImmutable($fecha . ' ' . $h) > $ahora
    ));
    echo json_encode(['horas' => $horas], JSON_UNESCAPED_UNICODE);
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
$profesionalId = filter_input(INPUT_GET, 'profesional_id', FILTER_VALIDATE_INT) ?: null;
$estado     = (string) ($_GET['estado'] ?? '');
if ($estado !== '' && !isset(ESTADOS_CITA[$estado])) {
    error_json(422, 'Estado inválido.');
}

// Base de listado y totales (el filtro de estado solo aplica al listado).
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
if ($sucursalId !== null) {
    $where   .= ' AND c.sucursal_id = ?';
    $params[] = $sucursalId;
}
if ($profesionalId !== null) {
    $where   .= ' AND c.profesional_id = ?';
    $params[] = $profesionalId;
}

$stmt = db()->prepare("SELECT c.estado, COUNT(*) FROM citas c WHERE {$where} GROUP BY c.estado");
$stmt->execute($params);
$totales = array_fill_keys(array_keys(ESTADOS_CITA), 0);
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
// ponytail: tope de filas sin paginacion; agregar paginacion si un rango supera MAX_FILAS_TURNOS
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
