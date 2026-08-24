<?php
// Devuelve las horas ocupadas para una sucursal en una fecha dada.
// Parámetros esperados por GET: `sucursal` y `date` (YYYY-MM-DD).
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
// Este endpoint devuelve horas ocupadas y no requiere rol admin.
// Se deja público para que el formulario de reserva del paciente pueda consultarlo.
// Si se desea restringir en el futuro, cambiar por `requireRole('admin')` o `requireLogin()`.
// require_once __DIR__ . '/../auth.php';

$sucursal = trim($_GET['sucursal'] ?? '');
$date = trim($_GET['date'] ?? ''); // expected YYYY-MM-DD

if ($sucursal === '' || $date === '') {
    // Si faltan parámetros, respondemos con un error claro.
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parámetros faltantes']);
    exit;
}

// Pedimos solo la hora (HH:MM) de las citas que no fueron canceladas.
$stmt = $conn->prepare("SELECT DATE_FORMAT(fecha_hora, '%H:%i') as hora FROM agenda WHERE sucursal = ? AND DATE(fecha_hora) = ? AND estado <> 'cancelada'");
$stmt->execute([$sucursal, $date]);
$horas = array_column($stmt->fetchAll(), 'hora');

// Devolvemos la lista de horas ocupadas como un array simple.
echo json_encode(['success' => true, 'occupied' => $horas]);
