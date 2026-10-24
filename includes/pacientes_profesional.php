<?php
// includes/pacientes_profesional.php — "pacientes con los que este
// profesional tiene una relacion de atencion real (al menos una cita)".
// Intentional: any cita (any estado) grants access to ALL of that patient's
// results and clinical notes, including other doctors'. Not an IDOR.

function pacientes_de_profesional(int $profesionalId): array
{
    $stmt = db()->prepare(
        'SELECT DISTINCT p.id, p.nombre, p.apellido, p.ci
         FROM pacientes p
         JOIN citas c ON c.paciente_id = p.id
         WHERE c.profesional_id = ?
         ORDER BY p.apellido, p.nombre'
    );
    $stmt->execute([$profesionalId]);
    return $stmt->fetchAll();
}

function paciente_de_profesional(int $profesionalId, int $pacienteId)
{
    $stmt = db()->prepare(
        'SELECT DISTINCT p.id, p.nombre, p.apellido, p.ci
         FROM pacientes p
         JOIN citas c ON c.paciente_id = p.id
         WHERE c.profesional_id = ? AND p.id = ?'
    );
    $stmt->execute([$profesionalId, $pacienteId]);
    return $stmt->fetch();
}
