<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';

$usuario = requerir_rol_json(['administrador', 'recepcionista']);

const MAX_FILAS_MENSAJES = 200;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $atendido = ($_POST['atendido'] ?? '1') === '0' ? 0 : 1;
    if ($id === false || $id < 1) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Mensaje invalido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt = db()->prepare(
        'UPDATE mensajes_contacto
            SET atendido = ?, atendido_por = ?, atendido_en = IF(? = 1, NOW(), NULL)
          WHERE id = ?'
    );
    $stmt->execute([$atendido, $atendido ? $usuario['cuenta_id'] : null, $atendido, $id]);
    $existe = db()->prepare('SELECT 1 FROM mensajes_contacto WHERE id = ?');
    $existe->execute([$id]);
    if (!$existe->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Mensaje no encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    registrar_evento('contacto_atendido', 'mensaje=' . $id . ' atendido=' . $atendido);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$filas = db()->query(
    'SELECT id, nombre, email, telefono, mensaje, atendido, creado_en, atendido_en
       FROM mensajes_contacto ORDER BY creado_en DESC, id DESC LIMIT ' . MAX_FILAS_MENSAJES
)->fetchAll();
echo json_encode(['mensajes' => array_map(static fn (array $m): array => [
    'id'          => (int) $m['id'],
    'nombre'      => $m['nombre'],
    'email'       => $m['email'],
    'telefono'    => $m['telefono'] ?? '',
    'mensaje'     => $m['mensaje'],
    'atendido'    => (int) $m['atendido'] === 1,
    'creado_en'   => $m['creado_en'],
    'atendido_en' => $m['atendido_en'] ?? '',
], $filas), 'max' => MAX_FILAS_MENSAJES], JSON_UNESCAPED_UNICODE);
