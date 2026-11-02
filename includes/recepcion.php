<?php

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

function filtro_sucursal_recepcion(array $usuario): ?int
{
    if (($usuario['rol'] ?? '') !== 'recepcionista') {
        return null;
    }
    return sucursal_a_cargo($usuario) ?? 0;
}
