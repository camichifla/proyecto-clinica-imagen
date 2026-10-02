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


/**
 * Maps resultados + their images (as returned by obtener_resultados_de_paciente)
 * to the JSON shape consumed by resultados-propios.js / resultados-paciente.js.
 */
function resultados_json(array $resultados, array $imagenesPorResultado): array
{
    return array_map(static function (array $resultado) use ($imagenesPorResultado): array {
        $imagenes = $imagenesPorResultado[(int) $resultado['id']] ?? [];
        return [
            'id'             => (int) $resultado['id'],
            'nombre_estudio' => $resultado['nombre_estudio'],
            'fecha_estudio'  => $resultado['fecha_estudio'],
            'observaciones'  => $resultado['observaciones'],
            'imagenes'       => array_map(static function (array $imagen): array {
                return [
                    'id'              => (int) $imagen['id'],
                    'url'             => 'ver-resultado-imagen.php?id=' . (int) $imagen['id'],
                    'nombre_original' => $imagen['nombre_original'],
                ];
            }, $imagenes),
        ];
    }, $resultados);
}
