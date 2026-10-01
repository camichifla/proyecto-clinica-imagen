<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
$usuario = requerir_rol(['profesional']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../includes/agenda_render.php';

// --- Vista (mes/semana) y fecha de referencia, ambas re-validadas: un
// valor invalido o ausente cae siempre a "mes" / "hoy", nunca a un error. ---
$vista = ($_GET['vista'] ?? 'mes') === 'semana' ? 'semana' : 'mes';

$fechaRefTexto = (string) ($_GET['fecha'] ?? '');
$fechaRef = DateTime::createFromFormat('Y-m-d', $fechaRefTexto) ?: null;
if (!$fechaRef || $fechaRef->format('Y-m-d') !== $fechaRefTexto) {
    $fechaRef = new DateTime('today');
}
$fechaRef->setTime(0, 0, 0);

$rango = calcular_rango_agenda($vista, $fechaRef);

$stmt = db()->prepare(
    "SELECT c.*,
            p.nombre  AS paciente_nombre,  p.apellido  AS paciente_apellido,
            pr.nombre AS profesional_nombre, pr.apellido AS profesional_apellido,
            s.nombre  AS sucursal_nombre
     FROM citas c
     LEFT JOIN pacientes p ON p.id = c.paciente_id
     JOIN profesionales pr ON pr.id = c.profesional_id
     LEFT JOIN sucursales s ON s.id = c.sucursal_id
     WHERE c.profesional_id = ? AND DATE(c.fecha_hora_solicitada) BETWEEN ? AND ?
     ORDER BY c.fecha_hora_solicitada ASC"
);
$stmt->execute([$usuario['ref_id'], $rango['rangoInicio']->format('Y-m-d'), $rango['rangoFin']->format('Y-m-d')]);
$citasPorDia = agrupar_citas_por_dia($stmt->fetchAll());

/**
 * Preserves vista/fecha on every nav link (prev/next, toggle, day
 * drill-down). No sucursal filter here: this is the profesional's own
 * schedule, already scoped by profesional_id in the query above.
 */
function url_agenda(string $vista, string $fecha): string
{
    return url_agenda_generico('ver-agenda.php', $vista, $fecha);
}

// Ver assets/js/agenda-ajax.js / agenda.php: mismo fragmento-only response
// para que mes/semana/prev/next no recarguen el sitio entero.
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
    render_calendario_agenda($vista, $fechaRef, $rango, $citasPorDia, 'url_agenda');
    exit;
}
require __DIR__ . '/../views/ver-agenda.php';
