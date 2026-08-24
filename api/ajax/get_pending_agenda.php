<?php
// Devuelve la lista de citas en estado 'pendiente', junto con datos
// básicos del paciente para mostrarlos en la bandeja del admin.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
// Solo personal con rol 'admin' puede acceder a esta información.
requireRole('admin');

// Tomamos los datos de la tabla agenda y sumamos el nombre del paciente
$sql = "SELECT a.id, a.paciente_ci, a.estudio, a.medico, a.sucursal, a.fecha_hora, a.estado,
               u.name AS paciente_nombre, u.surname AS paciente_apellido, u.email AS paciente_email, u.phone AS paciente_telefono
        FROM agenda a
        LEFT JOIN users u ON u.CI = a.paciente_ci
        WHERE a.estado = 'pendiente'
        ORDER BY a.fecha_hora ASC";

$items = $conn->query($sql)->fetchAll();

echo json_encode(['success' => true, 'data' => $items]);
