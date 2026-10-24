<?php
// includes/validar_reserva.php — shared cita-booking validation, used by
// agendar-cita.php (paciente) and agendar-cita-medico.php (medico agendando
// para un paciente): mismos 5 pasos de sede/estudio/profesional/horario,
// solo cambia el INSERT/UPDATE final en cada caller.
require_once __DIR__ . '/horarios.php';

function es_hora_ocupada(Throwable $e): bool
{
    return $e instanceof PDOException && $e->getCode() === '23000' && str_contains($e->getMessage(), 'uq_citas_slot');
}

// Responde 422 si la excepcion es el choque con uq_citas_slot; si no, la relanza.
function hora_ocupada_json(PDOException $e): never
{
    if (!es_hora_ocupada($e)) {
        throw $e;
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'errores' => ['hora' => 'Esa hora ya fue reservada. Elegí otra.']], JSON_UNESCAPED_UNICODE);
    exit;
}

// $ignorarCitaId: la cita que se esta reprogramando; su propio horario no cuenta como ocupado al autoasignar.
function validar_reserva(array $datos, array $estudiosPermitidos, string $fechaMin, string $fechaMax, int $rangoMeses, int $ignorarCitaId = 0): array
{
    $errores = [];

    if (!isset($estudiosPermitidos[$datos['estudio']])) {
        $errores['estudio'] = 'Elegí un estudio válido.';
    }

    $sucursalId = filter_var($datos['sucursal_id'], FILTER_VALIDATE_INT);
    if (!$sucursalId) {
        $errores['sucursal_id'] = 'Elegí una sede válida.';
    } else {
        $stmt = db()->prepare('SELECT activo FROM sucursales WHERE id = ?');
        $stmt->execute([$sucursalId]);
        $activa = $stmt->fetchColumn();
        if ($activa === false || (int) $activa !== 1) {
            $errores['sucursal_id'] = $activa === false ? 'Elegí una sede válida.' : 'La sede elegida no esta disponible.';
            $sucursalId = null;
        }
    }

    // Vacio = "cualquiera disponible": se asigna al final, con todo lo demas validado.
    $autoAsignar   = $datos['profesional_id'] === '';
    $profesionalId = $autoAsignar ? null : filter_var($datos['profesional_id'], FILTER_VALIDATE_INT);
    if (!$autoAsignar && !$profesionalId) {
        $errores['profesional_id'] = 'Elegí un profesional.';
    }

    if (!$errores && $profesionalId) {
        $stmt = db()->prepare('SELECT 1 FROM profesional_especializacion pe WHERE pe.profesional_id = ? AND pe.especializacion = ? AND '
            . sql_profesional_habilitado('pe.profesional_id'));
        $stmt->execute([$profesionalId, $datos['estudio']]);
        if (!$stmt->fetch()) {
            $errores['profesional_id'] = 'Ese profesional no atiende el estudio elegido.';
        }
    }

    $marcaTiempo = null;
    if (!isset($errores['fecha']) && $datos['fecha'] !== '' && $datos['hora'] !== '') {
        $marcaTiempo = DateTimeImmutable::createFromFormat('Y-m-d H:i', $datos['fecha'] . ' ' . $datos['hora']);
    }

    if (!$marcaTiempo) {
        $errores['fecha'] = 'Elegí una fecha y hora válidas.';
    } elseif ($marcaTiempo <= new DateTimeImmutable()) {
        $errores['fecha'] = 'La fecha y hora debe ser futura.';
    } elseif ($datos['fecha'] < $fechaMin || $datos['fecha'] > $fechaMax) {
        $errores['fecha'] = 'Solo se puede agendar dentro de los próximos ' . $rangoMeses . ' meses.';
    }

    if (!$errores && $sucursalId && $marcaTiempo) {
        $dia  = (int) $marcaTiempo->format('N');
        $stmt = db()->prepare('SELECT hora_apertura, hora_cierre FROM sucursal_horarios WHERE sucursal_id = ? AND dia_semana = ?');
        $stmt->execute([$sucursalId, $dia]);
        $turnosDelDia = $stmt->fetchAll();

        if (!$turnosDelDia) {
            $errores['fecha'] = 'La sede elegida no atiende ese día.';
        } elseif (!in_array($datos['hora'], slots_de_turnos($turnosDelDia), true)) {
            $errores['hora'] = 'Esa hora no esta disponible en la sede elegida.';
        }
    }

    if (!$errores && $sucursalId && isset($estudiosPermitidos[$datos['estudio']])) {
        $stmt = db()->prepare('SELECT estudio FROM sucursal_estudio WHERE sucursal_id = ?');
        $stmt->execute([$sucursalId]);
        $estudiosDeSede = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($estudiosDeSede && !in_array($datos['estudio'], $estudiosDeSede, true)) {
            $errores['estudio'] = 'La sede elegida no realiza ese estudio.';
        }
    }

    if (!$errores && $sucursalId && $profesionalId) {
        $stmt = db()->prepare('SELECT 1 FROM profesional_sucursal WHERE profesional_id = ? AND sucursal_id = ?');
        $stmt->execute([$profesionalId, $sucursalId]);
        if (!$stmt->fetch()) {
            $errores['profesional_id'] = 'Ese profesional no atiende en la sede elegida.';
        }
    }

    if (!$errores && $sucursalId && $profesionalId && $marcaTiempo) {
        $propios = horarios_propios([$profesionalId], (int) $marcaTiempo->format('N'));
        if (isset($propios[$profesionalId]) && !hora_en_turnos($propios[$profesionalId], $datos['hora'])) {
            $errores['hora'] = 'Ese profesional no atiende en ese horario. Elegí otra hora.';
        }
    }

    if (!$errores && $autoAsignar && $sucursalId && $marcaTiempo) {
        // ponytail: lowest id wins, no load balancing; upgrade to least-booked if it matters
        $stmt = db()->prepare(
            'SELECT p.id
               FROM profesionales p
               JOIN profesional_especializacion pe ON pe.profesional_id = p.id AND pe.especializacion = ?
               JOIN profesional_sucursal ps ON ps.profesional_id = p.id AND ps.sucursal_id = ?
              WHERE ' . sql_profesional_habilitado('p.id') . '
                AND NOT EXISTS (
                      SELECT 1 FROM citas c
                       WHERE c.profesional_id = p.id AND c.fecha_hora_solicitada = ? AND c.slot_activo = 1 AND c.id <> ?)
              ORDER BY p.id'
        );
        $stmt->execute([$datos['estudio'], $sucursalId, $marcaTiempo->format('Y-m-d H:i:s'), $ignorarCitaId]);
        $libres  = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $propios = horarios_propios($libres, (int) $marcaTiempo->format('N'));
        $profesionalId = null;
        foreach ($libres as $id) {
            if (!isset($propios[$id]) || hora_en_turnos($propios[$id], $datos['hora'])) {
                $profesionalId = $id;
                break;
            }
        }
        if ($profesionalId === null) {
            $errores['hora'] = 'No hay profesionales disponibles en esa hora. Elegí otra.';
        }
    }

    return [$errores, $sucursalId, $profesionalId, $marcaTiempo, $datos['estudio']];
}
