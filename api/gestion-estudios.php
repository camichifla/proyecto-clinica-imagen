<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/estudios.php';

requerir_rol_json(['administrador']);

const ETIQUETA_ESTUDIO_MAX = 60;
const DESCRIPCION_ESTUDIO_MAX = 255;

function respuesta_estudios(int $codigo, array $cuerpo): never
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $accion = (string) ($_POST['accion'] ?? '');
    if (!in_array($accion, ['crear', 'editar'], true)) {
        respuesta_estudios(400, ['ok' => false, 'error' => 'Acción inválida.']);
    }

    $clave         = (string) ($_POST['clave'] ?? '');
    $etiqueta      = trim((string) ($_POST['etiqueta'] ?? ''));
    $descripcion   = trim((string) ($_POST['descripcion'] ?? ''));
    $autoagendable = ($_POST['autoagendable'] ?? '') === '1' ? 1 : 0;
    $errores       = [];

    if (!preg_match('/^[a-z][a-z0-9_]{1,29}$/', $clave)) {
        $errores['clave'] = 'La clave usa 2 a 30 letras minusculas, numeros o guion bajo, y empieza con una letra.';
    }
    if ($etiqueta === '' || mb_strlen($etiqueta) > ETIQUETA_ESTUDIO_MAX) {
        $errores['etiqueta'] = 'La etiqueta es obligatoria (máximo ' . ETIQUETA_ESTUDIO_MAX . ' caracteres).';
    } else {
        $stmt = db()->prepare('SELECT 1 FROM estudios WHERE etiqueta = ? AND clave <> ?');
        $stmt->execute([$etiqueta, $clave]);
        if ($stmt->fetchColumn()) {
            $errores['etiqueta'] = 'Ya existe un estudio con esa etiqueta.';
        }
    }

    if (mb_strlen($descripcion) > DESCRIPCION_ESTUDIO_MAX) {
        $errores['descripcion'] = 'La descripción admite hasta ' . DESCRIPCION_ESTUDIO_MAX . ' caracteres.';
    }

    $stmt = db()->prepare('SELECT 1 FROM estudios WHERE clave = ?');
    $stmt->execute([$clave]);
    $existe = (bool) $stmt->fetchColumn();
    if ($accion === 'crear' && $existe && !isset($errores['clave'])) {
        $errores['clave'] = 'Ya existe un estudio con esa clave.';
    }
    if ($accion === 'editar' && !$existe && !isset($errores['clave'])) {
        respuesta_estudios(404, ['ok' => false, 'error' => 'Estudio no encontrado.']);
    }
    if ($errores) {
        respuesta_estudios(422, ['ok' => false, 'errores' => $errores]);
    }

    if ($accion === 'crear') {
        db()->prepare('INSERT INTO estudios (clave, etiqueta, descripcion, autoagendable) VALUES (?, ?, ?, ?)')
            ->execute([$clave, $etiqueta, $descripcion === '' ? null : $descripcion, $autoagendable]);
        registrar_evento('estudio_alta', 'estudio=' . $clave);
    } else {
        $activo = ($_POST['activo'] ?? '') === '1' ? 1 : 0;
        db()->prepare('UPDATE estudios SET etiqueta = ?, descripcion = ?, autoagendable = ?, activo = ? WHERE clave = ?')
            ->execute([$etiqueta, $descripcion === '' ? null : $descripcion, $autoagendable, $activo, $clave]);
        registrar_evento('estudio_cambio', 'estudio=' . $clave . ' activo=' . $activo . ' autoagendable=' . $autoagendable);
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'etiquetaMax' => ETIQUETA_ESTUDIO_MAX,
    'descripcionMax' => DESCRIPCION_ESTUDIO_MAX,
    'estudios'    => array_map(static fn (array $e): array => [
        'clave'         => $e['clave'],
        'etiqueta'      => $e['etiqueta'],
        'descripcion'   => $e['descripcion'] ?? '',
        'autoagendable' => (int) $e['autoagendable'] === 1,
        'activo'        => (int) $e['activo'] === 1,
    ], estudios_catalogo()),
], JSON_UNESCAPED_UNICODE);
