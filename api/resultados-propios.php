<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/resultados_render.php';

$usuario = requerir_rol_json(['paciente']);

[$resultados, $imagenesPorResultado] = obtener_resultados_de_paciente(db(), $usuario['ref_id']);

echo json_encode([
    'resultados' => array_map(static function (array $resultado) use ($imagenesPorResultado): array {
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
    }, $resultados),
], JSON_UNESCAPED_UNICODE);
