<?php

/**
 * Links to the newly created patient the citas and orders made by a doctor when only their email ($email) was known.
 * Runs inside the registration transaction.
 */
function vincular_citas_pendientes(PDO $db, int $pacienteId, string $email): void
{
    $stmt = $db->prepare('UPDATE citas SET paciente_id = ?, email_pendiente = NULL, nombre_pendiente = NULL, apellido_pendiente = NULL WHERE email_pendiente = ? AND paciente_id IS NULL');
    $stmt->execute([$pacienteId, $email]);

    $stmt = $db->prepare(
        'UPDATE ordenes o JOIN citas c ON c.id = o.cita_id
            SET o.paciente_id = ?
          WHERE c.paciente_id = ? AND o.paciente_id IS NULL'
    );
    $stmt->execute([$pacienteId, $pacienteId]);
}
