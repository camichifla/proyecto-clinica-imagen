<?php
// includes/estudios.php — shared citas.estudio / citas.estado label maps,
// used by every page that displays or validates a cita's estudio/estado.
//
// ESTUDIOS_BASE / ESTUDIOS split: patients can't self-book 'alineadores'
// (only a medico can, see agendar-cita-medico.php). agendar-cita.php uses
// ESTUDIOS_BASE (no alineadores); agendar-cita-medico.php and
// solicitudes-cita.php use the full ESTUDIOS.

const ESTUDIOS_BASE = [
    'placa'       => 'Placa',
    'radiografia' => 'Radiografia',
];

const ESTUDIOS = ESTUDIOS_BASE + [
    'alineadores' => 'Alineadores',
];

const NOTAS_ADMIN_MAX = 255;

const ESTADOS_CITA = [
    'pendiente'  => 'Pendiente',
    'confirmada' => 'Confirmada',
    'rechazada'  => 'Rechazada',
    'cancelada'  => 'Cancelada',
];
