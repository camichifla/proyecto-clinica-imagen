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

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$citaId = isset($input['cita_id']) ? (int) $input['cita_id'] : 0;

if ($citaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se recibió una cita válida para cancelar.']);
    exit;
}

$host = '127.0.0.1';
$db   = 'users_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec("CREATE TABLE IF NOT EXISTS citas_canceladas (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        cita_id INT UNSIGNED NOT NULL,
        paciente_ci INT UNSIGNED NOT NULL,
        estudio VARCHAR(150) NOT NULL,
        medico VARCHAR(150) NOT NULL,
        sucursal VARCHAR(150) NOT NULL,
        fecha_hora DATETIME NOT NULL,
        estado_anterior ENUM('pendiente','confirmada','cancelada') NOT NULL DEFAULT 'pendiente',
        motivo_cancelacion VARCHAR(100) NOT NULL DEFAULT 'cancelada_por_paciente',
        cancelada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_paciente_ci (paciente_ci),
        KEY idx_cita_id (cita_id),
        KEY idx_cancelada_en (cancelada_en)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt = $pdo->prepare("SELECT id, paciente_ci, estudio, medico, sucursal, fecha_hora, estado FROM agenda WHERE id = ? AND paciente_ci = ? AND estado IN ('pendiente','confirmada')");
    $stmt->execute([$citaId, getUserId()]);
    $cita = $stmt->fetch();

    if (!$cita) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'La cita ya no está activa o no pertenece a tu usuario.']);
        exit;
    }

    $pdo->beginTransaction();

    $update = $pdo->prepare("UPDATE agenda SET estado = 'cancelada' WHERE id = ? AND paciente_ci = ? AND estado IN ('pendiente','confirmada')");
    $update->execute([$citaId, getUserId()]);

    if ($update->rowCount() !== 1) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'No se pudo cancelar la cita en este momento.']);
        exit;
    }

    $insert = $pdo->prepare("INSERT INTO citas_canceladas (cita_id, paciente_ci, estudio, medico, sucursal, fecha_hora, estado_anterior, motivo_cancelacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $insert->execute([
        $cita['id'],
        $cita['paciente_ci'],
        $cita['estudio'],
        $cita['medico'],
        $cita['sucursal'],
        $cita['fecha_hora'],
        $cita['estado'],
        'cancelada_por_paciente',
    ]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Cita cancelada correctamente.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error cancelar cita: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno al cancelar la cita.']);
}
?>
