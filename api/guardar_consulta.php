<?php
header('Content-Type: application/json');

// 1. Configuración de la base de datos
$db_host     = 'localhost';
$db_usuario  = 'root';
$db_password = '';
$db_nombre   = 'users_db';

// Crear conexión
$conexion = new mysqli($db_host, $db_usuario, $db_password, $db_nombre);

// Verificar conexión
if ($conexion->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos: ' . $conexion->connect_error
    ]);
    exit();
}

// Establecer charset a UTF-8
$conexion->set_charset("utf8mb4");

// Verificar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit();
}

// Obtener y sanitizar datos recibidos (captura 'consulta' o 'mensaje' por retrocompatibilidad desde el formulario)
$nombre   = trim($_POST['nombre'] ?? '');
$email    = trim($_POST['correo'] ?? $_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? $_POST['Telefono'] ?? '');
$consulta = trim($_POST['consulta'] ?? $_POST['mensaje'] ?? '');

// Validaciones
$errores = [];

if (empty($nombre)) {
    $errores[] = 'El nombre es requerido';
} elseif (strlen($nombre) < 3) {
    $errores[] = 'El nombre debe tener al menos 3 caracteres';
} elseif (strlen($nombre) > 100) {
    $errores[] = 'El nombre no puede exceder 100 caracteres';
}

if (empty($email)) {
    $errores[] = 'El correo electrónico es requerido';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'El correo electrónico no es válido';
}

if (!empty($telefono) && !preg_match('/^[0-9\s\-\+\(\)]+$/', $telefono)) {
    $errores[] = 'El teléfono contiene caracteres inválidos';
}

if (empty($consulta)) {
    $errores[] = 'La consulta es requerida';
} elseif (strlen($consulta) < 10) {
    $errores[] = 'La consulta debe tener al menos 10 caracteres';
}

// Si hay errores, retornar respuesta en JSON
if (!empty($errores)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => implode(', ', $errores)
    ]);
    exit();
}

// 2. Consulta adaptada sin la columna 'mensaje'
$sql = "INSERT INTO consultas (nombre, email, telefono, consulta, fecha_creacion, ip_address, estado) 
        VALUES (?, ?, ?, ?, NOW(), ?, 'nuevo')";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al preparar la consulta: ' . $conexion->error
    ]);
    exit();
}

// Obtener IP del usuario
$ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

// Vincular 5 parámetros ("sssss"): nombre, email, telefono, consulta, ip
$stmt->bind_param(
    'sssss',
    $nombre,
    $email,
    $telefono,
    $consulta,
    $ip
);

// Ejecutar consulta
if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Tu consulta ha sido enviada correctamente. Nos pondremos en contacto pronto.',
        'id'      => $stmt->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar la consulta. Intenta de nuevo.'
    ]);
}

$stmt->close();
$conexion->close();
?>