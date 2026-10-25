<?php
// includes/pacientes_medico.php — "patients connected to this doctor": those
// already appearing in any of their orders (ordenes.creado_por) or those an
// administrator explicitly linked via medico_paciente.

function pacientes_de_medico(int $cuentaId, int $medicoId): array
{
    $stmt = db()->prepare(
        'SELECT DISTINCT p.id, p.nombre, p.apellido, p.ci
           FROM pacientes p
          WHERE p.id IN (
              SELECT DISTINCT o.paciente_id FROM ordenes o WHERE o.creado_por = ? AND o.paciente_id IS NOT NULL
              UNION
              SELECT mp.paciente_id FROM medico_paciente mp WHERE mp.medico_id = ?
          )
          ORDER BY p.apellido, p.nombre'
    );
    $stmt->execute([$cuentaId, $medicoId]);
    return $stmt->fetchAll();
}

function paciente_conectado_a_medico(int $cuentaId, int $medicoId, int $pacienteId): bool
{
    $stmt = db()->prepare(
        'SELECT 1 WHERE ? IN (
             SELECT DISTINCT o.paciente_id FROM ordenes o WHERE o.creado_por = ? AND o.paciente_id IS NOT NULL
             UNION
             SELECT mp.paciente_id FROM medico_paciente mp WHERE mp.medico_id = ?
         )'
    );
    $stmt->execute([$pacienteId, $cuentaId, $medicoId]);
    return (bool) $stmt->fetchColumn();
}
