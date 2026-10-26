<?php
// includes/recepcion.php — the branch a receptionist is in charge of. Requires db.php.

// The assigned branch id, or null for other roles or a receptionist with none yet.
function sucursal_a_cargo(array $usuario): ?int
{
    if (($usuario['rol'] ?? '') !== 'recepcionista') {
        return null;
    }
    $stmt = db()->prepare('SELECT sucursal_id FROM recepcionistas WHERE id = ?');
    $stmt->execute([$usuario['ref_id']]);
    $v = $stmt->fetchColumn();
    return ($v === false || $v === null) ? null : (int) $v;
}

// Branch id to scope queries by: null = no restriction (non-receptionist); for a
// receptionist it is their branch, or 0 (matches nothing) when none is assigned yet.
function filtro_sucursal_recepcion(array $usuario): ?int
{
    if (($usuario['rol'] ?? '') !== 'recepcionista') {
        return null;
    }
    return sucursal_a_cargo($usuario) ?? 0;
}
