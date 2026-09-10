<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Usuario no autenticado']); exit; }

$ci = getUserId();

$stmt = $conn->prepare('SELECT CI, name, surname, email, phone FROM users WHERE CI = ?');
$stmt->execute([$ci]);
$userRes = $stmt->fetch();

$stmt2 = $conn->prepare("SELECT a.id, a.estudio, a.medico, a.sucursal, a.fecha_hora, a.estado, cc.motivo_cancelacion, cc.cancelada_por
						 FROM agenda a
						 LEFT JOIN citas_canceladas cc ON cc.cita_id = a.id
						 WHERE a.paciente_ci = ?
						 ORDER BY a.fecha_hora ASC");
$stmt2->execute([$ci]);
$agendas = $stmt2->fetchAll();

$estudios = [];
try {
	$stmt3 = $conn->prepare('SELECT id, estudio, tecnico, fecha_estudio, archivo_visor, archivo_pdf FROM estudios WHERE paciente_ci = ? ORDER BY fecha_estudio DESC, id DESC');
	$stmt3->execute([$ci]);
	$estudios = $stmt3->fetchAll();
} catch (PDOException $error) {
	// La tabla se crea al importar sql/users.sql; el resto del dashboard sigue disponible mientras tanto.
	error_log('Tabla de estudios no disponible: ' . $error->getMessage());
}

foreach ($estudios as &$estudio) {
	$estudio['visor_url'] = $estudio['archivo_visor'] ? '/clinica-imagen/api/ajax/view_study_file.php?id=' . (int) $estudio['id'] . '&tipo=visor' : null;
	$estudio['pdf_url'] = $estudio['archivo_pdf'] ? '/clinica-imagen/api/ajax/view_study_file.php?id=' . (int) $estudio['id'] . '&tipo=pdf' : null;
	unset($estudio['archivo_visor'], $estudio['archivo_pdf']);
}
unset($estudio);

echo json_encode(['success'=>true,'user'=>$userRes,'agendas'=>$agendas,'estudios'=>$estudios]);
