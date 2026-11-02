<?php

require_once __DIR__ . '/cifrado.php';

const NOTA_MAX = 2000;

function notas_de_paciente(PDO $db, int $pacienteId, ?int $cuentaIdActual = null): array
{
    $stmt = $db->prepare(
        'SELECT n.id, n.texto, n.creado_en, u.rol, n.cuenta_id,
                COALESCE(CONCAT(m.apellido, \', \', m.nombre), CONCAT(p.apellido, \', \', p.nombre)) AS autor
           FROM notas_clinicas n
           JOIN cuentas u ON u.id = n.cuenta_id
           LEFT JOIN medicos m ON u.rol = \'medico\' AND m.id = u.ref_id
           LEFT JOIN profesionales p ON u.rol = \'profesional\' AND p.id = u.ref_id
          WHERE n.paciente_id = ? AND n.eliminada_en IS NULL
          ORDER BY n.creado_en DESC, n.id DESC'
    );
    $stmt->execute([$pacienteId]);
    return array_map(static fn (array $f): array => [
        'id'        => (int) $f['id'],
        'texto'     => descifrar($f['texto']),
        'creado_en' => $f['creado_en'],
        'autor'     => $f['autor'] ?? '-',
        'rol'       => $f['rol'],
        'propia'    => $cuentaIdActual !== null && (int) $f['cuenta_id'] === $cuentaIdActual,
    ], $stmt->fetchAll());
}
