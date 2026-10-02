<?php
// includes/pacientes_medico.php — "pacientes conectados a este medico": los
// que ya aparecen en alguna de sus ordenes (ordenes.creado_por) o los que un
// administrador vinculo explicitamente via medico_paciente.

/**
 * @return array<int,array{id:int,nombre:string,apellido:string,ci:string}>
 */
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
