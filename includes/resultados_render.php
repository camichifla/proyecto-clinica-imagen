<?php
/**
 * Fetches a patient's resultados plus their images, batched into two
 * queries regardless of row count.
 *
 * @return array{0: array<int,array<string,mixed>>, 1: array<int,array<int,array<string,mixed>>>}
 */
function obtener_resultados_de_paciente(PDO $db, int $pacienteId): array
{
    $stmt = $db->prepare('SELECT * FROM resultados WHERE paciente_id = ? ORDER BY fecha_estudio DESC');
    $stmt->execute([$pacienteId]);
    $resultados = $stmt->fetchAll();

    $imagenesPorResultado = [];
    if ($resultados) {
        $ids = array_column($resultados, 'id');
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmtImg = $db->prepare(
            "SELECT id, resultado_id, nombre_original FROM resultado_imagenes WHERE resultado_id IN ({$marcadores}) ORDER BY creado_en ASC"
        );
        $stmtImg->execute($ids);
        foreach ($stmtImg->fetchAll() as $imagen) {
            $imagenesPorResultado[(int) $imagen['resultado_id']][] = $imagen;
        }
    }

    return [$resultados, $imagenesPorResultado];
}

