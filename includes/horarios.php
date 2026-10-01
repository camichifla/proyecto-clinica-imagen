<?php
// includes/horarios.php — the 45-minute slot rule, server-side authority.
// The JS copy in agendar-cita.php (generarHoras) renders the same list for
// the UI; this one decides what a forged POST is allowed to submit.

const PASO_MINUTOS = 45;

/**
 * @param array $turnos Rows shaped like ['hora_apertura' => '08:30:00', 'hora_cierre' => '12:30:00'], ...]
 * @return string[] Flat, ordered list of 'HH:MM' slot starts, one list per shift concatenated.
 */
function slots_de_turnos(array $turnos): array
{
    $slots = [];
    foreach ($turnos as $turno) {
        $inicio = (int) substr($turno['hora_apertura'], 0, 2) * 60 + (int) substr($turno['hora_apertura'], 3, 2);
        $fin    = (int) substr($turno['hora_cierre'],   0, 2) * 60 + (int) substr($turno['hora_cierre'],   3, 2);
        for ($m = $inicio; $m <= $fin; $m += PASO_MINUTOS) {
            $slots[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }
    }
    return $slots;
}
