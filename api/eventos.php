<?php
// GET only, admin only: last events (audit log) plus brute-force suspects.
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';

requerir_rol_json(['administrador']);

const MAX_FILAS_EVENTOS = 200;
const FALLOS_SOSPECHOSOS = 5;

$where  = '1';
$params = [];
$tipo = trim((string) ($_GET['tipo'] ?? ''));
if ($tipo !== '') {
    if (!preg_match('/^[a-z_]{1,40}$/', $tipo)) {
        http_response_code(422);
        echo json_encode(['error' => 'Tipo invalido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $where   .= ' AND tipo = ?';
    $params[] = $tipo;
}
foreach (['desde' => '>= ?', 'hasta' => '< ? + INTERVAL 1 DAY'] as $campo => $op) {
    $fecha = trim((string) ($_GET[$campo] ?? ''));
    if ($fecha === '') {
        continue;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    if (!$d || $d->format('Y-m-d') !== $fecha) {
        http_response_code(422);
        echo json_encode(['error' => 'Fecha invalida.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $where   .= " AND creado_en {$op}";
    $params[] = $fecha;
}

$stmt = db()->prepare(
    "SELECT id, tipo, cuenta_id, email, ip, detalle, creado_en
       FROM eventos WHERE {$where} ORDER BY creado_en DESC, id DESC LIMIT " . MAX_FILAS_EVENTOS
);
$stmt->execute($params);
$eventos = $stmt->fetchAll();

$sospechosos = [];
foreach (['ip', 'email'] as $columna) {
    $stmt = db()->prepare(
        "SELECT {$columna} AS valor, COUNT(*) AS cantidad FROM eventos
          WHERE tipo = 'login_fallo' AND {$columna} IS NOT NULL AND creado_en >= NOW() - INTERVAL 24 HOUR
          GROUP BY {$columna} HAVING COUNT(*) >= ? ORDER BY cantidad DESC"
    );
    $stmt->execute([FALLOS_SOSPECHOSOS]);
    foreach ($stmt->fetchAll() as $f) {
        $sospechosos[] = ['campo' => $columna, 'valor' => $f['valor'], 'cantidad' => (int) $f['cantidad']];
    }
}

$tipos = db()->query('SELECT DISTINCT tipo FROM eventos ORDER BY tipo')->fetchAll(PDO::FETCH_COLUMN);

echo json_encode([
    'eventos'     => $eventos,
    'sospechosos' => $sospechosos,
    'tipos'       => $tipos,
], JSON_UNESCAPED_UNICODE);
