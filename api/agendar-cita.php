<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/horarios.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/validar_reserva.php';
require_once __DIR__ . '/../includes/mailer.php';

$usuario = requerir_rol_json(['paciente']);

[$fechaMin, $fechaMax] = rango_reserva();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cancelar') {
        $citaId = filter_input(INPUT_POST, 'cita_id', FILTER_VALIDATE_INT);

        if (!$citaId) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Cita inválida.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // Pending or confirmed upcoming citas; patients are not asked for a reason, the note
        // only tells staff who cancelled.
        $stmt = db()->prepare(
            "UPDATE citas SET estado = 'cancelada', notas_admin = 'Cancelado por el paciente.'
             WHERE id = ? AND paciente_id = ? AND estado IN ('pendiente', 'confirmada')
               AND fecha_hora_solicitada >= NOW()"
        );
        $stmt->execute([$citaId, $usuario['ref_id']]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'No se pudo cancelar la cita: no existe, no es tuya o ya no se puede cancelar.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        registrar_evento('cita_cancelar', 'cita=' . $citaId);
        echo json_encode(['ok' => true, 'redirect' => 'agendar-cita.html?cancelado=1'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($accion === 'crear') {
        $valores = [
            'sucursal_id'    => (string) ($_POST['sucursal_id'] ?? ''),
            'estudio'        => (string) ($_POST['estudio'] ?? ''),
            'profesional_id' => (string) ($_POST['profesional_id'] ?? ''),
            'fecha'          => (string) ($_POST['fecha'] ?? ''),
            'hora'           => (string) ($_POST['hora'] ?? ''),
        ];

        [$errores, $sucursalId, $profesionalId, $marcaTiempo, $estudio] = validar_reserva($valores, estudios_autoagendables(), $fechaMin, $fechaMax, RANGO_MESES, 0);

        if ($errores) {
            echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = db()->prepare(
            "INSERT INTO citas (paciente_id, profesional_id, sucursal_id, fecha_hora_solicitada, estudio, estado)
             VALUES (?, ?, ?, ?, ?, 'pendiente')"
        );
        try {
            $stmt->execute([
                $usuario['ref_id'],
                $profesionalId,
                $sucursalId,
                $marcaTiempo->format('Y-m-d H:i:s'),
                $estudio,
            ]);
        } catch (PDOException $e) {
            hora_ocupada_json($e);
        }
        $citaId = (int) db()->lastInsertId();
        registrar_evento('cita_crear', 'cita=' . $citaId);
        avisar_solicitud_recibida($citaId);
        echo json_encode(['ok' => true, 'redirect' => 'agendar-cita.html?ok=1&codigo=' . codigo_cita($citaId)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Acción inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = db()->prepare(
    "SELECT c.*, s.nombre AS sucursal_nombre
     FROM citas c
     LEFT JOIN sucursales s ON s.id = c.sucursal_id
     WHERE c.paciente_id = ?
       AND ((c.estado IN ('pendiente', 'confirmada') AND c.fecha_hora_solicitada >= NOW())
         OR (c.estado IN ('rechazada', 'cancelada') AND c.fecha_hora_solicitada >= DATE_SUB(NOW(), INTERVAL 30 DAY)))
     ORDER BY c.fecha_hora_solicitada ASC"
);
$stmt->execute([$usuario['ref_id']]);
$citas = array_map(static function (array $cita): array {
    return [
        'id'                  => (int) $cita['id'],
        'codigo'              => codigo_cita((int) $cita['id']),
        'fechaHoraSolicitada' => $cita['fecha_hora_solicitada'],
        'sucursalNombre'      => $cita['sucursal_nombre'],
        'estudioLabel'        => estudios_etiquetas()[$cita['estudio']] ?? $cita['estudio'],
        'estado'              => $cita['estado'],
        'estadoLabel'         => ESTADOS_CITA[$cita['estado']] ?? $cita['estado'],
        'motivo'              => in_array($cita['estado'], ['rechazada', 'cancelada'], true) ? $cita['notas_admin'] : null,
    ];
}, $stmt->fetchAll());

echo json_encode([
    'fechaMin'      => $fechaMin,
    'fechaMax'      => $fechaMax,
    'citas'         => $citas,
] + catalogo_reserva(estudios_autoagendables()), JSON_UNESCAPED_UNICODE);
