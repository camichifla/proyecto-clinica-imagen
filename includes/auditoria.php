<?php

// Un fallo de auditoria nunca rompe la peticion.
function registrar_evento(string $tipo, ?string $detalle = null, ?string $email = null): void
{
    try {
        $cuentaId = isset($_SESSION['cuenta_id']) ? (int) $_SESSION['cuenta_id'] : null;
        $ip       = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($detalle !== null) {
            $detalle = mb_substr($detalle, 0, 255);
        }
        db()->prepare('INSERT INTO eventos (tipo, cuenta_id, email, ip, detalle) VALUES (?, ?, ?, ?, ?)')
            ->execute([$tipo, $cuentaId, $email, $ip, $detalle]);
        // ponytail: probabilistic prune of throttle rows only (audit rows are kept); make it a cron job if traffic grows.
        if (random_int(1, 100) === 1) {
            db()->exec("DELETE FROM eventos WHERE tipo IN ('login_fallo', 'reset_solicitud', 'contacto', 'verif_reenvio') AND creado_en < NOW() - INTERVAL 90 DAY");
        }
    } catch (Throwable $e) {
        error_log('registrar_evento: ' . $e->getMessage());
    }
}

// $ip opcional acota el conteo a ese origen (ej. fallos de un email desde una IP).
function demasiados_eventos(string $tipo, string $columna, string $valor, int $max, int $minutos, ?string $ip = null): bool
{
    if (!in_array($columna, ['email', 'ip'], true)) {
        throw new InvalidArgumentException('Columna invalida.');
    }
    $filtroIp = $ip === null ? '' : ' AND ip = ?';
    $stmt = db()->prepare("SELECT COUNT(*) FROM eventos WHERE tipo = ? AND {$columna} = ?{$filtroIp} AND creado_en >= NOW() - INTERVAL ? MINUTE");
    $params = $ip === null ? [$tipo, $valor] : [$tipo, $valor, $ip];
    foreach ($params as $i => $param) {
        $stmt->bindValue($i + 1, $param);
    }
    $stmt->bindValue(count($params) + 1, $minutos, PDO::PARAM_INT);
    $stmt->execute();
    return (int) $stmt->fetchColumn() >= $max;
}
