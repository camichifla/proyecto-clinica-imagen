<?php
require_once __DIR__ . '/db.php';

function estudios_catalogo(): array
{
    static $filas = null;
    if ($filas === null) {
        $filas = db()->query('SELECT clave, etiqueta, descripcion, autoagendable, activo FROM estudios ORDER BY etiqueta')->fetchAll();
    }
    return $filas;
}

function estudios_mapa(?callable $filtro = null): array
{
    $mapa = [];
    foreach (estudios_catalogo() as $e) {
        if ($filtro === null || $filtro($e)) {
            $mapa[$e['clave']] = $e['etiqueta'];
        }
    }
    return $mapa;
}

function estudios_etiquetas(): array
{
    return estudios_mapa();
}

function estudios_activos(): array
{
    return estudios_mapa(static fn (array $e): bool => (int) $e['activo'] === 1);
}

function estudios_autoagendables(): array
{
    return estudios_mapa(static fn (array $e): bool => (int) $e['activo'] === 1 && (int) $e['autoagendable'] === 1);
}

const NOTAS_ADMIN_MAX = 255;

const ESTADOS_CITA = [
    'pendiente'  => 'Pendiente',
    'confirmada' => 'Confirmada',
    'realizada'  => 'Realizada',
    'rechazada'  => 'Rechazada',
    'cancelada'  => 'Cancelada',
];

function codigo_cita(int $id): string
{
    return 'CI-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
}

function id_de_codigo(string $codigo): ?int
{
    if (!preg_match('/^(?:ci-)?(\d{1,9})$/i', trim($codigo), $m)) {
        return null;
    }
    return (int) $m[1] ?: null;
}
