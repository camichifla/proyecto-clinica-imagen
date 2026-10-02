<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/horarios.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/validar_reserva.php';

$usuario = requerir_rol_json(['paciente']);

[$fechaMin, $fechaMax] = rango_reserva();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cancelar') {
        $citaId = filter_input(INPUT_POST, 'cita_id', FILTER_VALIDATE_INT);
        $motivo = trim((string) ($_POST['notas_admin'] ?? ''));

        $prefijoPaciente = 'Cancelado por el paciente: ';
        $maxMotivo = NOTAS_ADMIN_MAX - mb_strlen($prefijoPaciente);
        if (mb_strlen($motivo) > $maxMotivo) {
            $motivo = mb_substr($motivo, 0, $maxMotivo);
        }
        $notaGuardada = $motivo !== '' ? $prefijoPaciente . $motivo : null;

        if ($citaId) {

            $stmt = db()->prepare(
                "UPDATE citas SET estado = 'cancelada', notas_admin = ?
                 WHERE id = ? AND paciente_id = ? AND estado = 'pendiente'"
            );
            $stmt->execute([$notaGuardada, $citaId, $usuario['ref_id']]);
        }
        echo json_encode(['ok' => true, 'redirect' => 'agendar-cita.html?cancelado=1'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($accion === 'crear' || $accion === 'reprogramar') {
        $valores = [
            'sucursal_id'    => (string) ($_POST['sucursal_id'] ?? ''),
            'estudio'        => (string) ($_POST['estudio'] ?? ''),
            'profesional_id' => (string) ($_POST['profesional_id'] ?? ''),
            'fecha'          => (string) ($_POST['fecha'] ?? ''),
            'hora'           => (string) ($_POST['hora'] ?? ''),
        ];

        [$errores, $sucursalId, $profesionalId, $marcaTiempo, $estudio] = validar_reserva($valores, ESTUDIOS_BASE, $fechaMin, $fechaMax, RANGO_MESES);

        if ($errores) {
            echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($accion === 'crear') {
            $stmt = db()->prepare(
                "INSERT INTO citas (paciente_id, profesional_id, sucursal_id, fecha_hora_solicitada, estudio, estado)
                 VALUES (?, ?, ?, ?, ?, 'pendiente')"
            );
            $stmt->execute([
                $usuario['ref_id'],
                $profesionalId,
                $sucursalId,
                $marcaTiempo->format('Y-m-d H:i:s'),
                $estudio,
            ]);
            echo json_encode(['ok' => true, 'redirect' => 'agendar-cita.html?ok=1'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $citaId = filter_input(INPUT_POST, 'cita_id', FILTER_VALIDATE_INT);
        if ($citaId) {

            $stmt = db()->prepare(
                "UPDATE citas SET fecha_hora_solicitada = ?, profesional_id = ?, sucursal_id = ?, estudio = ?
                 WHERE id = ? AND paciente_id = ? AND estado = 'pendiente'"
            );
            $stmt->execute([
                $marcaTiempo->format('Y-m-d H:i:s'),
                $profesionalId,
                $sucursalId,
                $estudio,
                $citaId,
                $usuario['ref_id'],
            ]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['ok' => true, 'redirect' => 'agendar-cita.html?reprogramado=1'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        echo json_encode(['ok' => true, 'redirect' => 'agendar-cita.html?error=reprogramar'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Accion invalida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$editar = null;
$editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
if ($editarId) {
    $stmt = db()->prepare(
        "SELECT sucursal_id, estudio, profesional_id, fecha_hora_solicitada
           FROM citas WHERE id = ? AND paciente_id = ? AND estado = 'pendiente'"
    );
    $stmt->execute([$editarId, $usuario['ref_id']]);
    $citaAEditar = $stmt->fetch();
    if ($citaAEditar) {
        $fechaHoraEditar = new DateTimeImmutable($citaAEditar['fecha_hora_solicitada']);
        $editar = [
            'citaId'        => $editarId,
            'sucursalId'    => (string) $citaAEditar['sucursal_id'],
            'estudio'       => (string) $citaAEditar['estudio'],
            'profesionalId' => (string) $citaAEditar['profesional_id'],
            'fecha'         => $fechaHoraEditar->format('Y-m-d'),
            'hora'          => $fechaHoraEditar->format('H:i'),
        ];
    }
}

$stmt = db()->prepare(
    'SELECT c.*, s.nombre AS sucursal_nombre
     FROM citas c
     LEFT JOIN sucursales s ON s.id = c.sucursal_id
     WHERE c.paciente_id = ?
     ORDER BY c.creado_en DESC'
);
$stmt->execute([$usuario['ref_id']]);
$citas = array_map(static function (array $cita): array {
    return [
        'id'                  => (int) $cita['id'],
        'fechaHoraSolicitada' => $cita['fecha_hora_solicitada'],
        'sucursalNombre'      => $cita['sucursal_nombre'],
        'estudioLabel'        => ESTUDIOS_BASE[$cita['estudio']] ?? $cita['estudio'],
        'estado'              => $cita['estado'],
        'estadoLabel'         => ESTADOS_CITA[$cita['estado']] ?? $cita['estado'],
        'editable'            => $cita['estado'] === 'pendiente',
    ];
}, $stmt->fetchAll());

echo json_encode([
    'fechaMin'      => $fechaMin,
    'fechaMax'      => $fechaMax,
    'notasAdminMax' => NOTAS_ADMIN_MAX,
    'citas'         => $citas,
    'editar'        => $editar,
] + catalogo_reserva(), JSON_UNESCAPED_UNICODE);
