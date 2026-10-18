<?php

/**
 * Vincula al paciente recien creado las citas y ordenes hechas por un medico cuando solo se conocia su correo ($email).
 * Va dentro de la transaccion del alta.
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
