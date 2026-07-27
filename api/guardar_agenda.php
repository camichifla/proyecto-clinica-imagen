<?php
header('Content-Type: application/json');
require_once './auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

if (!isLoggedIn() || getUserType() !== 'patient') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

$estudio = trim($_POST['estudio'] ?? '');
$medico = trim($_POST['medico'] ?? '');
$sucursal = trim($_POST['sucursal'] ?? '');
$fechaHora = trim($_POST['fecha_hora'] ?? '');

$errores = [];

if ($estudio === '') {
    $errores[] = 'Selecciona un estudio';
}
if ($medico === '') {
    $errores[] = 'Selecciona un médico';
}
if ($sucursal === '') {
    $errores[] = 'Selecciona una sucursal';
}
if ($fechaHora === '') {
    $errores[] = 'Selecciona fecha y hora';
} elseif (!DateTime::createFromFormat('Y-m-d\TH:i', $fechaHora)) {
    $errores[] = 'Fecha y hora inválidas';
} else {
    $fecha = new DateTime($fechaHora);
    $ahora = new DateTime('now');
    if ($fecha <= $ahora) {
        $errores[] = 'Fecha y hora deben ser posteriores al momento actual';
    }
}

if (!empty($errores)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode('. ', $errores)]);
    exit;
}

$host = '127.0.0.1';
$db   = 'users_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->prepare('INSERT INTO agenda (paciente_ci, estudio, medico, sucursal, fecha_hora) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([getUserId(), $estudio, $medico, $sucursal, $fechaHora]);

    echo json_encode(['success' => true, 'message' => 'Cita agendada correctamente']);
} catch (PDOException $e) {
    error_log('Error guardar agenda: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno al guardar la agenda']);
}
?>