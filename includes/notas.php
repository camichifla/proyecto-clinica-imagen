<?php
// includes/notas.php — append-only clinical record. Rows are only ever
// inserted and read, never updated or deleted. Visible to medico/profesional
// accounts that already have access to the patient.

require_once __DIR__ . '/cifrado.php';

const NOTA_MAX = 2000;

/**
 * Newest first. The caller has already checked that the session may see this patient.
 *
 * @return array<int,array{id:int,texto:string,creado_en:string,autor:string,rol:string}>
 */
function notas_de_paciente(PDO $db, int $pacienteId): array
{
    $stmt = $db->prepare(
        'SELECT n.id, n.texto, n.creado_en, u.rol,
                COALESCE(CONCAT(m.apellido, \', \', m.nombre), CONCAT(p.apellido, \', \', p.nombre)) AS autor
           FROM notas_clinicas n
           JOIN cuentas u ON u.id = n.cuenta_id
           LEFT JOIN medicos m ON u.rol = \'medico\' AND m.id = u.ref_id
           LEFT JOIN profesionales p ON u.rol = \'profesional\' AND p.id = u.ref_id
          WHERE n.paciente_id = ?
          ORDER BY n.creado_en DESC, n.id DESC'
    );
    $stmt->execute([$pacienteId]);
    return array_map(static fn (array $f): array => [
        'id'        => (int) $f['id'],
        'texto'     => descifrar($f['texto']),
        'creado_en' => $f['creado_en'],
        'autor'     => $f['autor'] ?? '-',
        'rol'       => $f['rol'],
    ], $stmt->fetchAll());
}
