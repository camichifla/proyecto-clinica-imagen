<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
requireRole('professional');

$sql = "SELECT a.id, a.paciente_ci, a.estudio, a.medico, a.sucursal, a.fecha_hora, a.estado,
               u.name AS paciente_nombre, u.surname AS paciente_apellido,
               u.email AS paciente_email, u.phone AS paciente_telefono
        FROM agenda a
        INNER JOIN users u ON u.CI = a.paciente_ci
        WHERE a.estado IN ('pendiente', 'confirmada')
        ORDER BY a.fecha_hora ASC, a.id ASC";

$studies = $conn->query($sql)->fetchAll();
echo json_encode(['success' => true, 'data' => $studies]);
