<?php
mysqli_report(MYSQLI_REPORT_OFF);

$host = '127.0.0.1';
$user = 'root';
$password = '';
$database = 'users_db';
$port = 3306;

$conn = new mysqli($host, $user, $password, $database, $port);

if ($conn->connect_error) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'No se pudo conectar a la base de datos. Revisá que MySQL esté corriendo en XAMPP.'
    ]);
    exit;
}

