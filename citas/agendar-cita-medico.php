<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
$usuario = requerir_rol(['medico']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../includes/horarios.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/validar_reserva.php';
require_once __DIR__ . '/../includes/pacientes_medico.php';

// agendar-cita-medico.php — a medico books a cita ON BEHALF OF a paciente
// (medico picks the paciente; unlike agendar-cita.php's own patient booking
// for themselves) and attaches a detailed clinical order (imaging studies
// only — the aligner planilla lives in ORDEN_CAMPOS_ALINEADORES/alineadores/,
// not on this screen) to that booking. The sucursal -> estudio -> profesional
// -> fecha -> hora cascading engine below mirrors agendar-cita.php exactly
// (same ids, same JS, same 5-step server re-validation) — see that file for
// the canonical version; only the paciente_id step and the orden clinica are
// new here.

// Whether the already-validated estudio requires a clinical order — always
// mandatory here (agendar-cita-medico.php only), never optional. This screen
// only offers ESTUDIOS_BASE (placa/radiografia; no 'alineadores'), so the
// order is always the imaging-study one.
function tipo_orden_requerido(string $estudio): ?string
{
    return isset(ESTUDIOS_BASE[$estudio]) ? 'estudio' : null;
}

const RANGO_MESES = 3;

// --- FDI tooth numbering used by the clinical order's tooth-selection
// grids (radio_intra and tomo_cone_beam), both offering the full 52 teeth
// (permanent + primary), as the live form does. ---
const DIENTES_COMPLETO = [
    '55', '54', '53', '52', '51', '61', '62', '63', '64', '65',
    '18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28',
    '48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38',
    '85', '84', '83', '82', '81', '71', '72', '73', '74', '75',
];

// --- Clinical-order field whitelists. This is the SINGLE source of truth
// for both what gets rendered (every render call below pulls its options
// straight from here) and what a POST is allowed to write into
// `orden_estudio` / `orden_estudio_selecciones` (see recolectar_campos_orden()) — a field can never be
// collected without also being one the form can render, and the exact
// option/value sets can never drift between the two.
//
// Every key below is one name="..." of the live order form at
// https://clinicaimagen.uy/ordenes/orden.html, in its own source order, and
// every option is that input's value="" verbatim — NOT its visible <label>.
// The two diverge often ("ATM" vs "ATM (Boca abierta y cerrada)
// (LAMINOGRAFÍA)", "DAM" vs "Dispositivo avance mandibular (DAM)",
// "Diagnóstico bolton" vs "Diagnóstico Bolton"), and the value is what the
// clinic's own mail.php receives, so the value is what is reproduced here;
// orden_checkbox_grupo() then renders each option string as both the
// submitted value and the visible text, which is why a few read tersely.
// The only two deliberate departures from the source values are flagged
// inline below (ecografias and otros_servicios), both because the live form
// reuses one value for two different answers and would lose information.
//
// No "profesional
// solicitante" identity fields here on purpose — the requesting profesional
// is always the logged-in medico, already recorded as `ordenes.creado_por`;
// re-asking for their name/contact in the order itself would be redundant.
// Same reasoning for the patient's own name/contact: they're already
// identified up front by the modo_paciente picker (existente select or
// nuevo nombre/apellido/email), so asking again inside the order itself
// would just be the same data typed twice. The live form's medio[] (how the
// study is delivered: Impreso / Imagen Cloud / Mail / Medcloud) is left out
// too — `ordenes` has no such concept. ---
// define() instead of const: 'tomo_cone_beam' below needs array_merge() to
// build its option list (the live form puts the two whole-arch regions and
// all 52 teeth under one single name="tomo-cone-beam[]"), and PHP 8.0 — the
// version XAMPP actually serves this app with — allows neither function
// calls nor array unpacking inside a `const` expression. Everything else
// about the constant is unchanged: same name, same shape, same usage.
define('ORDEN_CAMPOS_ESTUDIO', [
    // --- Radiografías Intrabucales ---
    'radio_intra'                 => ['tipo' => 'checkbox_array', 'opciones' => DIENTES_COMPLETO],
    'radio_intra_tipo'            => ['tipo' => 'checkbox_array', 'opciones' => ['Con conductimetría', 'Periapical', 'Relevamiento periapical completo', 'Bitewing', 'Oclusal']],

    // --- Otros estudios extraorales ---
    'otros_estudios_extraorales'  => ['tipo' => 'checkbox_array', 'opciones' => ['ATM', 'Sub mentón Vertex', 'Mano y puño']],

    // --- Documentación para ortodoncia: radiografías extraorales.
    // Three separate names on the live form, not one: the three studies
    // themselves (radio-extraoral[]), the perfil sub-options
    // (telerradio-perfil-tipo[]) and the frontal one (a lone checkbox,
    // name without [], hence tipo 'radio' — same "scalar that must match an
    // allowed option, else ''" contract). ---
    'radio_extraoral'             => ['tipo' => 'checkbox_array', 'opciones' => ['Panorámica (OPT)', 'Telerradiografía Perfil', 'Telerradiografía Frontal']],
    'opt_indicacion'              => ['tipo' => 'text', 'max' => 200],
    'telerradio_perfil_tipo'      => ['tipo' => 'checkbox_array', 'opciones' => ['Plano de Frankfort', 'Horizontal verdadero', 'Labios en reposo', 'Labios en contacto']],
    'telerradio_frontal_analisis' => ['tipo' => 'radio', 'opciones' => ['Con analisis']],

    // --- Estudios cefalométricos computarizados ---
    'estudio_cefalo_compu'        => ['tipo' => 'checkbox_array', 'opciones' => ['Ricketts', 'Ricketts Resumido', 'Mcnamara', 'Fundación Gnathos', 'Roth Jarabak', 'Bjork Jarabak', 'Trevis', 'Steiner Tweed', 'VTO']],
    'estudio_cefalo_compu_otros'  => ['tipo' => 'text', 'max' => 120],

    // --- Fotografías. One single name on the live form for faciales,
    // bucales and "foto en dinámica" alike. The trailing spaces in four of
    // these values ('Rostro sonrisa ', '2 oclusales ', ...) are verbatim
    // from the source form's value="" and are what the browser submits, so
    // they must stay for array_intersect() to match. ---
    'fotografias'                 => ['tipo' => 'checkbox_array', 'opciones' => ['Todas las fotografías', 'Rostro frente', 'Perfil', 'Rostro sonrisa ', '3/4 perfil', '2 oclusales ', '2 llaves de oclusión ', '2 overjet/overbite ', '1 anterior en inoclusión', '1 oclusión frente', 'con contacto oclusales', 'foto en dinámica']],
    'fotografia_interes'          => ['tipo' => 'text', 'max' => 200],

    // --- Modelos digitales. Also one single name on the live form: its
    // "MODELO DIGITAL" / "MODELO IMPRESO" split is purely visual. ---
    'modelos_digitales'           => ['tipo' => 'checkbox_array', 'opciones' => ['Ortodoncia', 'Ortopedia', 'Diagnóstico bolton', 'Diagnóstico Moyers', 'Diagnóstico Medidas dentarias', 'Impresión de modelos zocalados', 'Impresión de modelos de trabajo', 'Mordida constructiva', 'Duplicado en yeso', 'Modelos articulados con bisagra posterior']],

    // --- Ortodoncia invisible (alineadores). Part of the ORDER form; the
    // separate ORDEN_CAMPOS_ALINEADORES planilla is a different document. ---
    'ortodoncia_marca'            => ['tipo' => 'text', 'max' => 120],
    'ortodoncia_tipo'             => ['tipo' => 'checkbox_array', 'opciones' => ['Bimaxilar', 'Maxilar', 'Mandíbula']],
    'ortodoncia_info_clinica'     => ['tipo' => 'textarea', 'max' => 2000],

    // --- Tomografía Cone Beam. tomo-cone-beam[] holds the two whole-arch
    // regions AND the 52-tooth grid under one name; the partial-region
    // modifiers are a second, separate name. ---
    'tomo_cone_beam'              => ['tipo' => 'checkbox_array', 'opciones' => array_merge(['Maxilar completo', 'Mandíbula completa'], DIENTES_COMPLETO)],
    'tomo_cone_beam_tipo'         => ['tipo' => 'checkbox_array', 'opciones' => ['Hemiarco', '1 a 3 piezas', 'Sin separación de tejidos blandos']],
    'tomo_elementos_sueltos'      => ['tipo' => 'text', 'max' => 200],
    'tomo_tipo_estudio'           => ['tipo' => 'checkbox_array', 'opciones' => ['Implante', 'Endodoncia', 'Cirugía', 'Ortodoncia', 'Periodoncia', 'ATM']],
    'interes_estudio_tomo'        => ['tipo' => 'textarea', 'max' => 2000],

    // --- Cirugía guiada para implantes: the services and the guide type
    // are two separate names on the live form, not one list of five. ---
    'guia_implantes_servicio'     => ['tipo' => 'checkbox_array', 'opciones' => ['Tomografía', 'Escaneo', 'Planeamiento']],
    'guia_implante_tipo'          => ['tipo' => 'checkbox_array', 'opciones' => ['Guía de precisión', 'Guía de fresa iniciadora']],
    'implante_marca'              => ['tipo' => 'text', 'max' => 120],
    'implante_ubicacion'          => ['tipo' => 'text', 'max' => 120],
    'implante_fecha_cirugia'      => ['tipo' => 'date'],

    // --- Realidad virtual M3DMIX ---
    'realidad_virtual_m3dmix'     => ['tipo' => 'checkbox_array', 'opciones' => ['Tercer molar', 'Patología', 'Nervio mandibular', 'Maxilares e implantes']],

    // --- Ecografías. The live form ships value="ATM" on BOTH of its two
    // checkboxes (the "Partes blandas" one included) — a copy/paste bug at
    // the source: submitting it makes the two indistinguishable. Kept as
    // 'Partes blandas' here, matching its visible label, so the two choices
    // actually survive the round trip. ---
    'ecografias'                  => ['tipo' => 'checkbox_array', 'opciones' => ['ATM', 'Partes blandas']],
    'eco_interes'                 => ['tipo' => 'text', 'max' => 200],

    // --- Otros servicios. Same deliberate departure as ecografias above:
    // the live form puts the arcada/grosor/forma sub-options of "Escaneo
    // final de ortodoncia" AND the arcada of "Placas de blanqueamiento"
    // under the same otros-servicios[] name as the services themselves, so
    // its value="SUPERIOR" appears twice and a submitted SUPERIOR can't be
    // traced back to which placa it belongs to. Split into four sub-groups
    // here so both placas keep their own answers. ---
    'otros_servicios'             => ['tipo' => 'checkbox_array', 'opciones' => ['Sólo escaneo', 'Placa neuromiorelajante/DOE', 'DAM', 'Diseño sonrisa e impresión en resina mockup', 'Escaneo final de ortodoncia + placas de contención.', 'Placas de blanqueamiento', 'Perioguide para cirugía gingival', 'Planeamiento cirugía ortognática', 'Endoguide', 'Protector bucal']],
    'solo_escaneo_interes'        => ['tipo' => 'text', 'max' => 200],
    'contencion_arcada'           => ['tipo' => 'checkbox_array', 'opciones' => ['SUPERIOR', 'INFERIOR']],
    'contencion_grosor'           => ['tipo' => 'checkbox_array', 'opciones' => ['0.75mm', '1mm', '1.5mm']],
    'contencion_forma'            => ['tipo' => 'checkbox_array', 'opciones' => ['RECTO', 'FESTONEADO']],
    'blanqueamiento_arcada'       => ['tipo' => 'checkbox_array', 'opciones' => ['SUPERIOR', 'INFERIOR']],
    'protector_bucal_color'       => ['tipo' => 'text', 'max' => 120],
]);

/**
 * Whitelists and sanitizes posted order fields against $config. Only keys
 * present in $config are ever read from $post — this is the single place
 * that decides what of $_POST can reach the orden_estudio* tables.
 * checkbox_array values are intersected against the allowed option set,
 * radio values must exactly match an allowed option (else stored as
 * ''), date values must be a real Y-m-d date (else ''), and text/textarea
 * values are trimmed and length-capped.
 *
 * @param array<string,array{tipo:string,opciones?:string[],max?:int}> $config
 * @return array<string,mixed>
 */
function recolectar_campos_orden(array $config, array $post): array
{
    $datos = [];
    foreach ($config as $campo => $definicion) {
        $tipo = $definicion['tipo'];
        if ($tipo === 'checkbox_array') {
            $enviados = $post[$campo] ?? [];
            if (!is_array($enviados)) {
                $enviados = [];
            }
            $datos[$campo] = array_values(array_intersect($enviados, $definicion['opciones']));
        } elseif ($tipo === 'radio') {
            $valor = (string) ($post[$campo] ?? '');
            $datos[$campo] = in_array($valor, $definicion['opciones'], true) ? $valor : '';
        } elseif ($tipo === 'date') {
            $valor = trim((string) ($post[$campo] ?? ''));
            $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
            $datos[$campo] = $fecha && $fecha->format('Y-m-d') === $valor ? $valor : '';
        } else {
            $valor  = trim((string) ($post[$campo] ?? ''));
            $maxLen = $definicion['max'] ?? 200;
            $datos[$campo] = mb_substr($valor, 0, $maxLen);
        }
    }
    return $datos;
}

// --- Render helpers for the order form. All read $ordenPost (raw posted
// values, UX-only redisplay after a validation error — never the source
// used for the actual insert, see recolectar_campos_orden() above) and
// always pull their option lists from ORDEN_CAMPOS_* so the UI can never
// offer a value the server-side whitelist would reject. ---
$ordenPost = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [];

function orden_texto(string $name, string $tipo = 'text'): void
{
    global $ordenPost;
    $valor = htmlspecialchars((string) ($ordenPost[$name] ?? ''));
    echo '<input type="' . htmlspecialchars($tipo) . '" name="' . htmlspecialchars($name) . '" value="' . $valor . '">';
}

function orden_textarea(string $name, int $rows = 3): void
{
    global $ordenPost;
    $valor = htmlspecialchars((string) ($ordenPost[$name] ?? ''));
    echo '<textarea name="' . htmlspecialchars($name) . '" rows="' . $rows . '">' . $valor . '</textarea>';
}

function orden_checkbox_grupo(string $name, array $opciones, bool $inline = false): void
{
    global $ordenPost;
    $seleccionados = $ordenPost[$name] ?? [];
    if (!is_array($seleccionados)) {
        $seleccionados = [];
    }
    echo '<div class="check-grid' . ($inline ? ' inline' : '') . '">';
    foreach ($opciones as $opcion) {
        $val     = htmlspecialchars($opcion);
        $checked = in_array($opcion, $seleccionados, true) ? ' checked' : '';
        echo '<label><input type="checkbox" name="' . htmlspecialchars($name) . '[]" value="' . $val . '"' . $checked . '> ' . $val . '</label>';
    }
    echo '</div>';
}

/**
 * One single checkbox of a checkbox_array group, rendered on its own so the
 * view can interleave a group's options with the text inputs and sub-option
 * blocks the live form nests between them (its "textogrupo" and
 * "padre"/"hijo" layouts) instead of emitting one flat grid. Same name[] and
 * same $ordenPost lookup as orden_checkbox_grupo(), so both may render parts
 * of the same field. $clase is the hook the small toggle script at the
 * bottom of the view keys off (orden-padre / orden-hijo / orden-check-texto).
 */
function orden_checkbox_item(string $name, string $opcion, string $clase = ''): void
{
    global $ordenPost;
    // Same invariant orden_checkbox_grupo() gets for free by taking its
    // whole option list from the config: an option the whitelist would
    // reject can never be rendered, so nothing a medico ticks is silently
    // dropped by recolectar_campos_orden(). A typo here makes the checkbox
    // disappear from the form rather than become unsavable.
    if (!in_array($opcion, ORDEN_CAMPOS_ESTUDIO[$name]['opciones'] ?? [], true)) {
        return;
    }
    $seleccionados = $ordenPost[$name] ?? [];
    if (!is_array($seleccionados)) {
        $seleccionados = [];
    }
    $val     = htmlspecialchars($opcion);
    $checked = in_array($opcion, $seleccionados, true) ? ' checked' : '';
    $atrClase = $clase !== '' ? ' class="' . htmlspecialchars($clase) . '"' : '';
    echo '<label><input type="checkbox"' . $atrClase . ' name="' . htmlspecialchars($name) . '[]" value="' . $val . '"' . $checked . '>' . $val . '</label>';
}

/**
 * A lone checkbox whose name carries no [] — it posts a scalar, so it is
 * declared as tipo 'radio' in the config (same "must match an allowed option
 * or be stored as ''" contract) and unchecking it simply omits the key.
 */
function orden_checkbox_solo(string $name, string $opcion, string $clase = ''): void
{
    global $ordenPost;
    if (!in_array($opcion, ORDEN_CAMPOS_ESTUDIO[$name]['opciones'] ?? [], true)) {
        return;
    }
    $val      = htmlspecialchars($opcion);
    $checked  = ((string) ($ordenPost[$name] ?? '')) === $opcion ? ' checked' : '';
    $atrClase = $clase !== '' ? ' class="' . htmlspecialchars($clase) . '"' : '';
    echo '<label><input type="checkbox"' . $atrClase . ' name="' . htmlspecialchars($name) . '" value="' . $val . '"' . $checked . '>' . $val . '</label>';
}

// Row lengths of DIENTES_COMPLETO's 4 FDI quadrant-pair rows (10/16/16/10 —
// see its own definition), used to split the flat array back into visual
// rows so the two 10-tooth rows render centered over the two 16-tooth ones,
// matching a real odontogram instead of one auto-wrapping flat grid.
const DIENTES_FILAS_LARGOS = [10, 16, 16, 10];

function orden_dientes_grid(string $name, array $dientes, bool $compact = false): void
{
    global $ordenPost;
    $seleccionados = $ordenPost[$name] ?? [];
    if (!is_array($seleccionados)) {
        $seleccionados = [];
    }
    echo '<div class="teeth-grid' . ($compact ? ' compact' : '') . '">';
    $offset = 0;
    foreach (DIENTES_FILAS_LARGOS as $largo) {
        echo '<div class="teeth-row">';
        foreach (array_slice($dientes, $offset, $largo) as $diente) {
            $checked = in_array($diente, $seleccionados, true) ? ' checked' : '';
            echo '<label><input type="checkbox" name="' . htmlspecialchars($name) . '[]" value="' . $diente . '"' . $checked . '>' . $diente . '</label>';
        }
        echo '</div>';
        $offset += $largo;
    }
    echo '</div>';
}

$hoy      = new DateTimeImmutable('today');
$fechaMin = $hoy->format('Y-m-d');
$fechaMax = $hoy->modify('+' . RANGO_MESES . ' months')->format('Y-m-d');

$errores = [];
$valores = [
    'modo_paciente'          => 'existente',
    'paciente_id'            => '',
    'paciente_nombre_nuevo'  => '',
    'paciente_apellido_nuevo' => '',
    'paciente_email_nuevo'   => '',
    'sucursal_id'            => '',
    'estudio'                => '',
    'profesional_id'         => '',
    'fecha'                  => '',
    'hora'                   => '',
];

$profesionales = db()->query('SELECT id, nombre, apellido FROM profesionales ORDER BY apellido, nombre')->fetchAll();

// Pacientes "conectados" a este medico — ver includes/pacientes_medico.php.
// Nunca al reves — ver includes/auth_guard.php para la forma de $usuario.
$pacientesConectados = pacientes_de_medico($usuario['cuenta_id'], $usuario['ref_id']);
$idsPacientesConectados = array_map('intval', array_column($pacientesConectados, 'id'));

// Especializaciones are now a many-to-many relation (profesional_especializacion,
// replaces the old profesionales.especializacion column), reshaped here into
// $especializacionesPorProfesional[$profesionalId] = ['placa', ...] — mirrors
// how $datosSucursales['profesionales'] below is built from profesional_sucursal,
// and matches agendar-cita.php's own copy of this reshape.
$profesionalEspecializacionFilas = db()->query('SELECT profesional_id, especializacion FROM profesional_especializacion')->fetchAll();
$especializacionesPorProfesional = [];
foreach ($profesionalEspecializacionFilas as $fila) {
    $especializacionesPorProfesional[(int) $fila['profesional_id']][] = $fila['especializacion'];
}

// Four flat reads, reshaped into one blob keyed by sucursal_id — mirrors
// agendar-cita.php's own $datosSucursales exactly (see that file's
// design.md Decision 5 comment).
$sucursalesFilas = db()->query('SELECT id, nombre, direccion FROM sucursales ORDER BY nombre')->fetchAll();
$horariosFilas   = db()->query(
    "SELECT sucursal_id, dia_semana,
            TIME_FORMAT(hora_apertura, '%H:%i') AS apertura,
            TIME_FORMAT(hora_cierre,   '%H:%i') AS cierre
       FROM sucursal_horarios
      ORDER BY sucursal_id, dia_semana, hora_apertura"
)->fetchAll();
$estudioFilas             = db()->query('SELECT sucursal_id, estudio FROM sucursal_estudio')->fetchAll();
$profesionalSucursalFilas = db()->query('SELECT sucursal_id, profesional_id FROM profesional_sucursal')->fetchAll();

$datosSucursales = [];
foreach ($sucursalesFilas as $sucursal) {
    $datosSucursales[(string) $sucursal['id']] = [
        'nombre'        => $sucursal['nombre'],
        'direccion'     => $sucursal['direccion'],
        'estudios'      => [],
        'horarios'      => [],
        'profesionales' => [],
    ];
}
foreach ($horariosFilas as $turno) {
    $clave = (string) $turno['sucursal_id'];
    $datosSucursales[$clave]['horarios'][(string) $turno['dia_semana']][] = [$turno['apertura'], $turno['cierre']];
}
foreach ($estudioFilas as $restriccion) {
    $datosSucursales[(string) $restriccion['sucursal_id']]['estudios'][] = $restriccion['estudio'];
}
foreach ($profesionalSucursalFilas as $asignacion) {
    $datosSucursales[(string) $asignacion['sucursal_id']]['profesionales'][] = (int) $asignacion['profesional_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);

    $valores['modo_paciente']           = ($_POST['modo_paciente'] ?? '') === 'nuevo' ? 'nuevo' : 'existente';
    $valores['paciente_id']             = (string) ($_POST['paciente_id'] ?? '');
    $valores['paciente_nombre_nuevo']   = trim((string) ($_POST['paciente_nombre_nuevo'] ?? ''));
    $valores['paciente_apellido_nuevo'] = trim((string) ($_POST['paciente_apellido_nuevo'] ?? ''));
    $valores['paciente_email_nuevo']    = trim((string) ($_POST['paciente_email_nuevo'] ?? ''));
    $valores['sucursal_id']             = (string) ($_POST['sucursal_id'] ?? '');
    $valores['estudio']                 = (string) ($_POST['estudio'] ?? '');
    $valores['profesional_id']          = (string) ($_POST['profesional_id'] ?? '');
    $valores['fecha']                   = (string) ($_POST['fecha'] ?? '');
    $valores['hora']                    = (string) ($_POST['hora'] ?? '');

    // Step 0: paciente, dual-mode. citas.paciente_id below is ALWAYS the
    // value resolved here (or NULL), never $usuario['ref_id'] (the
    // medico's own ref_id points to `medicos`, not `pacientes`). Only the
    // fields belonging to the submitted mode are validated — mirrors how
    // this file already validates only the estudio-derived orden panel.
    $pacienteId        = null;
    $emailPendiente    = null;
    $nombrePendiente   = null;
    $apellidoPendiente = null;

    if ($valores['modo_paciente'] === 'existente') {
        // Never trust the client-submitted id: it must actually belong to
        // this medico's own connected-patient set (same UNION query used
        // to render the <select>), not merely exist as a row in pacientes.
        // Non-disclosure: same generic message whether the id doesn't
        // exist at all or simply isn't this medico's.
        $idCandidato = filter_var($valores['paciente_id'], FILTER_VALIDATE_INT);
        if (!$idCandidato || !in_array($idCandidato, $idsPacientesConectados, true)) {
            $errores['paciente_id'] = 'Elegi un paciente valido.';
        } else {
            $pacienteId = $idCandidato;
        }
    } else {
        $erroresPacienteNuevo = validar(
            [
                'paciente_nombre_nuevo'   => REGLAS_PACIENTE['nombre'],
                'paciente_apellido_nuevo' => REGLAS_PACIENTE['apellido'],
            ],
            $_POST
        );
        $errores = array_merge($errores, $erroresPacienteNuevo);

        // Trimmed only, matching register.php's own normalization of
        // cuentas.email (it doesn't lowercase either) — the `= ?`
        // comparison is case-insensitive anyway thanks to
        // utf8mb4_unicode_ci, so this stays consistent with how the rest
        // of the app already stores/compares emails.
        if (!filter_var($valores['paciente_email_nuevo'], FILTER_VALIDATE_EMAIL)) {
            $errores['paciente_email_nuevo'] = 'Ingresa un email valido.';
        } else {
            $stmt = db()->prepare("SELECT ref_id FROM cuentas WHERE rol = 'paciente' AND email = ?");
            $stmt->execute([$valores['paciente_email_nuevo']]);
            $refId = $stmt->fetchColumn();
            if ($refId !== false) {
                // A real account already exists for this email. Never
                // auto-attach to it here: that would let any medico assign
                // orders to any patient just by knowing/guessing their
                // email, bypassing the $idsPacientesConectados check that
                // guards the 'existente' flow above.
                $errores['paciente_email_nuevo'] = 'Ya existe una cuenta con ese email. Elegi el paciente desde "Paciente existente".';
            } else {
                $emailPendiente    = $valores['paciente_email_nuevo'];
                $nombrePendiente   = $valores['paciente_nombre_nuevo'];
                $apellidoPendiente = $valores['paciente_apellido_nuevo'];
            }
        }
    }

    // Mismos 5 pasos y mismo ESTUDIOS_BASE que agendar-cita.php, via el
    // helper compartido — la planilla de alineadores se maneja aparte
    // (alineadores/), asi que esta pantalla ya no ofrece ese estudio.
    [$erroresReserva, $sucursalId, $profesionalId, $marcaTiempo, $estudio] =
        validar_reserva($valores, ESTUDIOS_BASE, $fechaMin, $fechaMax, RANGO_MESES);
    $errores = array_merge($errores, $erroresReserva);

    if (!$errores) {
        try {
            db()->beginTransaction();

            $stmt = db()->prepare(
                "INSERT INTO citas (paciente_id, email_pendiente, nombre_pendiente, apellido_pendiente, profesional_id, sucursal_id, fecha_hora_solicitada, estudio, estado)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')"
            );
            $stmt->execute([
                $pacienteId,
                $emailPendiente,
                $nombrePendiente,
                $apellidoPendiente,
                $profesionalId,
                $sucursalId,
                $marcaTiempo->format('Y-m-d H:i:s'),
                $valores['estudio'],
            ]);
            $citaId = (int) db()->lastInsertId();

            // The clinical order is always mandatory here (never optional),
            // gated only by whether the already-validated estudio needs one
            // — never by a client-submitted field. A cita can never exist
            // without its required order: both inserts share this one
            // transaction, so either both land or neither does.
            $tipoOrdenRequerido = tipo_orden_requerido($valores['estudio']);
            if ($tipoOrdenRequerido !== null) {
                $datosOrden = recolectar_campos_orden(ORDEN_CAMPOS_ESTUDIO, $_POST);

                $stmtOrden = db()->prepare(
                    'INSERT INTO ordenes (paciente_id, cita_id, creado_por, tipo) VALUES (?, ?, ?, ?)'
                );
                $stmtOrden->execute([
                    $pacienteId,
                    $citaId,
                    $usuario['cuenta_id'],
                    $tipoOrdenRequerido,
                ]);
                $ordenId = (int) db()->lastInsertId();

                // Single-valued fields go to orden_estudio (one column each),
                // checkbox groups to orden_estudio_selecciones (one row per
                // checked option). Column names come from the
                // ORDEN_CAMPOS_ESTUDIO keys, never from the client.
                $stmtSeleccion = db()->prepare(
                    'INSERT INTO orden_estudio_selecciones (orden_id, campo, opcion) VALUES (?, ?, ?)'
                );
                $columnas = ['orden_id' => $ordenId];
                foreach (ORDEN_CAMPOS_ESTUDIO as $campo => $definicion) {
                    $valor = $datosOrden[$campo];
                    if ($definicion['tipo'] === 'checkbox_array') {
                        foreach ($valor as $opcion) {
                            $stmtSeleccion->execute([$ordenId, $campo, $opcion]);
                        }
                    } elseif ($definicion['tipo'] === 'radio') {
                        $columnas[$campo] = $valor !== '' ? 1 : 0;
                    } else {
                        $columnas[$campo] = $valor !== '' ? $valor : null;
                    }
                }
                db()->prepare(
                    'INSERT INTO orden_estudio (' . implode(', ', array_keys($columnas)) . ')
                     VALUES (' . implode(', ', array_fill(0, count($columnas), '?')) . ')'
                )->execute(array_values($columnas));
            }

            db()->commit();
            header('Location: agendar-cita-medico.php?ok=' . ($emailPendiente !== null ? 'pendiente' : '1'));
            exit;
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $errores['general'] = 'No se pudo guardar la cita. Intenta nuevamente.';
        }
    }
}

// For rendering: which order panel to show on this page load (GET default,
// or a POST that round-tripped with a validation error). Purely a display
// concern — the server-side insert above always recomputes this itself
// rather than trusting whatever was visible on the client.
$tipoOrdenActual = tipo_orden_requerido($valores['estudio']);
require __DIR__ . '/../views/agendar-cita-medico.php';
