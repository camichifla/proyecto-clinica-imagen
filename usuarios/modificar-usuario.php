<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
$usuario = requerir_login();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/menu.php';

// Editable fields per role, mapped to their own detail table. Every field
// name here must exist as a key in REGLAS_PACIENTE (the shared field-rule
// source, despite the name — see includes/validation.php's REGLAS_STAFF
// comment for why it's not paciente-specific). Table/column names below are
// hardcoded, never user input, so interpolating them into SQL is safe.
const CAMPOS_POR_ROL = [
    'paciente'      => ['tabla' => 'pacientes',      'campos' => ['nombre' => 'text', 'apellido' => 'text', 'direccion' => 'text', 'numero' => 'tel']],
    'medico'        => ['tabla' => 'medicos',        'campos' => ['nombre' => 'text', 'apellido' => 'text']],
    'profesional'   => ['tabla' => 'profesionales',  'campos' => ['nombre' => 'text', 'apellido' => 'text']],
    'administrador' => ['tabla' => 'administradores','campos' => ['nombre' => 'text', 'apellido' => 'text']],
];

$errores = [];
$exito   = false;
$config  = CAMPOS_POR_ROL[$usuario['rol']] ?? null;
$valores = $config !== null ? array_fill_keys(array_keys($config['campos']), '') : [];

if ($config !== null) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check($_POST['csrf'] ?? null);

        foreach ($valores as $campo => $_valor) {
            $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
        }

        $reglas  = array_intersect_key(REGLAS_PACIENTE, $valores);
        $errores = validar($reglas, $_POST);

        if (!$errores) {
            $asignaciones = implode(', ', array_map(static fn ($campo) => "{$campo} = ?", array_keys($valores)));
            $stmt = db()->prepare("UPDATE {$config['tabla']} SET {$asignaciones} WHERE id = ?");
            $stmt->execute([...array_values($valores), $usuario['ref_id']]);
            $exito = true;
        }
    } else {
        $columnas = implode(', ', array_keys($valores));
        $stmt = db()->prepare("SELECT {$columnas} FROM {$config['tabla']} WHERE id = ?");
        $stmt->execute([$usuario['ref_id']]);
        $fila = $stmt->fetch();
        if ($fila) {
            $valores = array_intersect_key($fila, $valores);
        }
    }

}
require __DIR__ . '/../views/modificar-usuario.php';
