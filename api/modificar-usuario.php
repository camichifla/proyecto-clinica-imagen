<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validation.php';

$usuario = requerir_rol_json(['paciente', 'medico', 'profesional', 'administrador']);

const CAMPOS_POR_ROL = [
    'paciente'      => ['nombre' => 'text', 'apellido' => 'text', 'direccion' => 'text', 'numero' => 'tel'],
    'medico'        => ['nombre' => 'text', 'apellido' => 'text'],
    'profesional'   => ['nombre' => 'text', 'apellido' => 'text'],
    'administrador' => ['nombre' => 'text', 'apellido' => 'text'],
];

$campos = CAMPOS_POR_ROL[$usuario['rol']] ?? null;
if ($campos === null) {
    http_response_code(404);
    echo json_encode(['error' => 'No hay campos editables para este rol.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tabla = TABLA_POR_ROL[$usuario['rol']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);

    $valores = [];
    foreach (array_keys($campos) as $campo) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    $reglas  = array_intersect_key(REGLAS_PACIENTE, $valores);
    $errores = validar($reglas, $_POST);

    if ($errores) {
        echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $asignaciones = implode(', ', array_map(static fn ($campo) => "{$campo} = ?", array_keys($valores)));
    $stmt = db()->prepare("UPDATE {$tabla} SET {$asignaciones} WHERE id = ?");
    $stmt->execute([...array_values($valores), $usuario['ref_id']]);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$columnas = implode(', ', array_keys($campos));
$stmt = db()->prepare("SELECT {$columnas} FROM {$tabla} WHERE id = ?");
$stmt->execute([$usuario['ref_id']]);
$fila = $stmt->fetch() ?: [];

echo json_encode([
    'campos' => array_map(static function (string $campo, string $tipo) use ($fila): array {
        return [
            'campo' => $campo,
            'tipo'  => $tipo,
            'label' => REGLAS_PACIENTE[$campo]['label'],
            'valor' => $fila[$campo] ?? '',
        ];
    }, array_keys($campos), array_values($campos)),
], JSON_UNESCAPED_UNICODE);
