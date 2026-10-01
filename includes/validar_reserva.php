<?php
// includes/validar_reserva.php — shared cita-booking validation, used by
// agendar-cita.php (paciente) and agendar-cita-medico.php (medico agendando
// para un paciente): mismos 5 pasos de sede/estudio/profesional/horario,
// solo cambia el INSERT/UPDATE final en cada caller.
require_once __DIR__ . '/horarios.php';

function validar_reserva(array $datos, array $estudiosPermitidos, string $fechaMin, string $fechaMax, int $rangoMeses): array
{
    $errores = [];

    if (!isset($estudiosPermitidos[$datos['estudio']])) {
        $errores['estudio'] = 'Elegi un estudio valido.';
    }

    $sucursalId = filter_var($datos['sucursal_id'], FILTER_VALIDATE_INT);
    if (!$sucursalId) {
        $errores['sucursal_id'] = 'Elegi una sede valida.';
    } else {
        $stmt = db()->prepare('SELECT id FROM sucursales WHERE id = ?');
        $stmt->execute([$sucursalId]);
        if (!$stmt->fetch()) {
            $errores['sucursal_id'] = 'Elegi una sede valida.';
            $sucursalId = null;
        }
    }

    $profesionalId = filter_var($datos['profesional_id'], FILTER_VALIDATE_INT);
    if (!$profesionalId) {
        $errores['profesional_id'] = 'Elegi un profesional.';
    }

    if (!$errores && $profesionalId) {
        $stmt = db()->prepare('SELECT 1 FROM profesional_especializacion WHERE profesional_id = ? AND especializacion = ?');
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
        $errores['fecha'] = 'Elegi una fecha y hora validas.';
    } elseif ($marcaTiempo <= new DateTimeImmutable()) {
        $errores['fecha'] = 'La fecha y hora debe ser futura.';
    } elseif ($datos['fecha'] < $fechaMin || $datos['fecha'] > $fechaMax) {
        $errores['fecha'] = 'Solo se puede agendar dentro de los proximos ' . $rangoMeses . ' meses.';
    }

    if (!$errores && $sucursalId && $marcaTiempo) {
        $dia  = (int) $marcaTiempo->format('N');
        $stmt = db()->prepare('SELECT hora_apertura, hora_cierre FROM sucursal_horarios WHERE sucursal_id = ? AND dia_semana = ?');
        $stmt->execute([$sucursalId, $dia]);
        $turnosDelDia = $stmt->fetchAll();

        if (!$turnosDelDia) {
            $errores['fecha'] = 'La sede elegida no atiende ese dia.';
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

    return [$errores, $sucursalId, $profesionalId, $marcaTiempo, $datos['estudio']];
}
