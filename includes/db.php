<?php
// includes/db.php — single lazily-built PDO connection.
require_once __DIR__ . '/config.php';

// Profile table per cuentas.rol (cuentas.ref_id points at its id).
const TABLA_POR_ROL = [
    'paciente'      => 'pacientes',
    'medico'        => 'medicos',
    'profesional'   => 'profesionales',
    'administrador' => 'administradores',
];

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
