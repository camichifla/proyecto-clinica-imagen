<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Usuario no autenticado']); exit; }

$ci = getUserId();

$stmt = $conn->prepare('SELECT CI, name, surname, email, phone FROM users WHERE CI = ?');
$stmt->execute([$ci]);
$userRes = $stmt->fetch();

$stmt2 = $conn->prepare("SELECT id, estudio, medico, sucursal, fecha_hora, estado FROM agenda WHERE paciente_ci = ? ORDER BY fecha_hora ASC");
$stmt2->execute([$ci]);
$agendas = $stmt2->fetchAll();

echo json_encode(['success'=>true,'user'=>$userRes,'agendas'=>$agendas]);
