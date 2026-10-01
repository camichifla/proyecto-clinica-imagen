<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
$usuario = requerir_rol(['administrador']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../includes/agenda_render.php';

$sucursales = db()->query('SELECT id, nombre FROM sucursales ORDER BY nombre')->fetchAll();

// Unico filtro que sigue teniendo sentido con navegacion por calendario:
// profesional/estado/desde/hasta quedaban redundantes con mes/semana + los
// colores por estado ya visibles en cada cita (decision 2026-09-14).
$sucursalId = filter_input(INPUT_GET, 'sucursal_id', FILTER_VALIDATE_INT) ?: null;
if ($sucursalId !== null && !in_array($sucursalId, array_column($sucursales, 'id'), true)) {
    $sucursalId = null;
}

$condiciones = [];
$parametros  = [];

if ($sucursalId !== null) {
    $condiciones[] = 'c.sucursal_id = ?';
    $parametros[]  = $sucursalId;
}

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

$where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';
$condicionesRango = $condiciones
    ? $where . ' AND DATE(c.fecha_hora_solicitada) BETWEEN ? AND ?'
    : 'WHERE DATE(c.fecha_hora_solicitada) BETWEEN ? AND ?';

$stmt = db()->prepare(
    "SELECT c.*,
            p.nombre  AS paciente_nombre,  p.apellido  AS paciente_apellido,
            pr.nombre AS profesional_nombre, pr.apellido AS profesional_apellido,
            s.nombre  AS sucursal_nombre
     FROM citas c
     LEFT JOIN pacientes p ON p.id = c.paciente_id
     JOIN profesionales pr ON pr.id = c.profesional_id
     LEFT JOIN sucursales s ON s.id = c.sucursal_id
     {$condicionesRango}
     ORDER BY c.fecha_hora_solicitada ASC"
);
$stmt->execute([...$parametros, $rango['rangoInicio']->format('Y-m-d'), $rango['rangoFin']->format('Y-m-d')]);
$citasPorDia = agrupar_citas_por_dia($stmt->fetchAll());

/**
 * Preserves the sucursal filter on every nav link (prev/next, toggle, day
 * drill-down) so switching vista/fecha never silently drops it.
 */
function url_agenda(string $vista, string $fecha): string
{
    return url_agenda_generico('agenda.php', $vista, $fecha, ['sucursal_id' => $_GET['sucursal_id'] ?? '']);
}

// Navegacion mes/semana pide este mismo endpoint por fetch() (ver
// assets/js/agenda-ajax.js) para reemplazar solo #agenda-calendario en vez
// de recargar el sitio entero; en ese caso se devuelve unicamente el
// fragmento del calendario, sin header/menu/layout.
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
    render_calendario_agenda($vista, $fechaRef, $rango, $citasPorDia, 'url_agenda');
    exit;
}
require __DIR__ . '/../views/agenda.php';
