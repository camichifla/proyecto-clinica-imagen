<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

$usuario = requerir_rol_json(['administrador', 'recepcionista']);

const MAX_FILAS_MENSAJES = 200;
const MAX_RESPUESTA = 2000;

function respuesta_mensajes(int $codigo, array $cuerpo): never
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

// POST: an attended message is deleted. With a non-empty 'respuesta' it is first
// emailed to the visitor; without one it was answered elsewhere (e.g. by phone).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $respuesta = trim((string) ($_POST['respuesta'] ?? ''));
    if ($id === false || $id < 1) {
        respuesta_mensajes(400, ['ok' => false, 'error' => 'Mensaje inválido.']);
    }
    if (mb_strlen($respuesta) > MAX_RESPUESTA) {
        respuesta_mensajes(422, ['ok' => false, 'error' => 'La respuesta no puede superar los ' . MAX_RESPUESTA . ' caracteres.']);
    }

    $db = db();
    $db->beginTransaction();
    // Row lock: two staff answering the same message can't both send the email.
    $stmt = $db->prepare('SELECT nombre, email, mensaje FROM mensajes_contacto WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $m = $stmt->fetch();
    if (!$m) {
        $db->rollBack();
        respuesta_mensajes(404, ['ok' => false, 'error' => 'El mensaje ya fue atendido o no existe.']);
    }
    if ($respuesta !== '') {
        $cuerpo = "Hola {$m['nombre']},\n\n{$respuesta}\n\n---\nTu mensaje:\n{$m['mensaje']}";
        if (!enviar_correo($m['email'], 'Respuesta a tu consulta - Clínica Imagen', $cuerpo)) {
            $db->rollBack();
            respuesta_mensajes(502, ['ok' => false, 'error' => 'No se pudo enviar el correo. El mensaje no se borró.']);
        }
    }
    $db->prepare('DELETE FROM mensajes_contacto WHERE id = ?')->execute([$id]);
    $db->commit();

    registrar_evento('contacto_atendido', 'mensaje=' . $id . ' respondido=' . ($respuesta !== '' ? 1 : 0));
    respuesta_mensajes(200, ['ok' => true]);
}

$filas = db()->query(
    'SELECT id, nombre, email, telefono, mensaje, creado_en
       FROM mensajes_contacto ORDER BY creado_en DESC, id DESC LIMIT ' . MAX_FILAS_MENSAJES
)->fetchAll();
echo json_encode(['mensajes' => array_map(static fn (array $m): array => [
    'id'        => (int) $m['id'],
    'nombre'    => $m['nombre'],
    'email'     => $m['email'],
    'telefono'  => $m['telefono'] ?? '',
    'mensaje'   => $m['mensaje'],
    'creado_en' => $m['creado_en'],
], $filas), 'max' => MAX_FILAS_MENSAJES, 'maxRespuesta' => MAX_RESPUESTA], JSON_UNESCAPED_UNICODE);
