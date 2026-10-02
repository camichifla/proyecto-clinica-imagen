<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/horarios.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/estudios.php';
require_once __DIR__ . '/../includes/validar_reserva.php';
require_once __DIR__ . '/../includes/pacientes_medico.php';

$usuario = requerir_rol_json(['medico']);

const DIENTES_COMPLETO = [
    '55', '54', '53', '52', '51', '61', '62', '63', '64', '65',
    '18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28',
    '48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38',
    '85', '84', '83', '82', '81', '71', '72', '73', '74', '75',
];

define('ORDEN_CAMPOS_ESTUDIO', [
    'radio_intra'                 => ['tipo' => 'checkbox_array', 'opciones' => DIENTES_COMPLETO],
    'radio_intra_tipo'            => ['tipo' => 'checkbox_array', 'opciones' => ['Con conductimetría', 'Periapical', 'Relevamiento periapical completo', 'Bitewing', 'Oclusal']],
    'otros_estudios_extraorales'  => ['tipo' => 'checkbox_array', 'opciones' => ['ATM', 'Sub mentón Vertex', 'Mano y puño']],
    'radio_extraoral'             => ['tipo' => 'checkbox_array', 'opciones' => ['Panorámica (OPT)', 'Telerradiografía Perfil', 'Telerradiografía Frontal']],
    'opt_indicacion'              => ['tipo' => 'text', 'max' => 200],
    'telerradio_perfil_tipo'      => ['tipo' => 'checkbox_array', 'opciones' => ['Plano de Frankfort', 'Horizontal verdadero', 'Labios en reposo', 'Labios en contacto']],
    'telerradio_frontal_analisis' => ['tipo' => 'radio', 'opciones' => ['Con analisis']],
    'estudio_cefalo_compu'        => ['tipo' => 'checkbox_array', 'opciones' => ['Ricketts', 'Ricketts Resumido', 'Mcnamara', 'Fundación Gnathos', 'Roth Jarabak', 'Bjork Jarabak', 'Trevis', 'Steiner Tweed', 'VTO']],
    'estudio_cefalo_compu_otros'  => ['tipo' => 'text', 'max' => 120],
    'fotografias'                 => ['tipo' => 'checkbox_array', 'opciones' => ['Todas las fotografías', 'Rostro frente', 'Perfil', 'Rostro sonrisa ', '3/4 perfil', '2 oclusales ', '2 llaves de oclusión ', '2 overjet/overbite ', '1 anterior en inoclusión', '1 oclusión frente', 'con contacto oclusales', 'foto en dinámica']],
    'fotografia_interes'          => ['tipo' => 'text', 'max' => 200],
    'modelos_digitales'           => ['tipo' => 'checkbox_array', 'opciones' => ['Ortodoncia', 'Ortopedia', 'Diagnóstico bolton', 'Diagnóstico Moyers', 'Diagnóstico Medidas dentarias', 'Impresión de modelos zocalados', 'Impresión de modelos de trabajo', 'Mordida constructiva', 'Duplicado en yeso', 'Modelos articulados con bisagra posterior']],
    'ortodoncia_marca'            => ['tipo' => 'text', 'max' => 120],
    'ortodoncia_tipo'             => ['tipo' => 'checkbox_array', 'opciones' => ['Bimaxilar', 'Maxilar', 'Mandíbula']],
    'ortodoncia_info_clinica'     => ['tipo' => 'textarea', 'max' => 2000],
    'tomo_cone_beam'              => ['tipo' => 'checkbox_array', 'opciones' => array_merge(['Maxilar completo', 'Mandíbula completa'], DIENTES_COMPLETO)],
    'tomo_cone_beam_tipo'         => ['tipo' => 'checkbox_array', 'opciones' => ['Hemiarco', '1 a 3 piezas', 'Sin separación de tejidos blandos']],
    'tomo_elementos_sueltos'      => ['tipo' => 'text', 'max' => 200],
    'tomo_tipo_estudio'           => ['tipo' => 'checkbox_array', 'opciones' => ['Implante', 'Endodoncia', 'Cirugía', 'Ortodoncia', 'Periodoncia', 'ATM']],
    'interes_estudio_tomo'        => ['tipo' => 'textarea', 'max' => 2000],
    'guia_implantes_servicio'     => ['tipo' => 'checkbox_array', 'opciones' => ['Tomografía', 'Escaneo', 'Planeamiento']],
    'guia_implante_tipo'          => ['tipo' => 'checkbox_array', 'opciones' => ['Guía de precisión', 'Guía de fresa iniciadora']],
    'implante_marca'              => ['tipo' => 'text', 'max' => 120],
    'implante_ubicacion'          => ['tipo' => 'text', 'max' => 120],
    'implante_fecha_cirugia'      => ['tipo' => 'date'],
    'realidad_virtual_m3dmix'     => ['tipo' => 'checkbox_array', 'opciones' => ['Tercer molar', 'Patología', 'Nervio mandibular', 'Maxilares e implantes']],
    'ecografias'                  => ['tipo' => 'checkbox_array', 'opciones' => ['ATM', 'Partes blandas']],
    'eco_interes'                 => ['tipo' => 'text', 'max' => 200],
    'otros_servicios'             => ['tipo' => 'checkbox_array', 'opciones' => ['Sólo escaneo', 'Placa neuromiorelajante/DOE', 'DAM', 'Diseño sonrisa e impresión en resina mockup', 'Escaneo final de ortodoncia + placas de contención.', 'Placas de blanqueamiento', 'Perioguide para cirugía gingival', 'Planeamiento cirugía ortognática', 'Endoguide', 'Protector bucal']],
    'solo_escaneo_interes'        => ['tipo' => 'text', 'max' => 200],
    'contencion_arcada'           => ['tipo' => 'checkbox_array', 'opciones' => ['SUPERIOR', 'INFERIOR']],
    'contencion_grosor'           => ['tipo' => 'checkbox_array', 'opciones' => ['0.75mm', '1mm', '1.5mm']],
    'contencion_forma'            => ['tipo' => 'checkbox_array', 'opciones' => ['RECTO', 'FESTONEADO']],
    'blanqueamiento_arcada'       => ['tipo' => 'checkbox_array', 'opciones' => ['SUPERIOR', 'INFERIOR']],
    'protector_bucal_color'       => ['tipo' => 'text', 'max' => 120],
]);

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

[$fechaMin, $fechaMax] = rango_reserva();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? null);

    $modoPaciente = ($_POST['modo_paciente'] ?? '') === 'nuevo' ? 'nuevo' : 'existente';
    $valores = [
        'sucursal_id'    => (string) ($_POST['sucursal_id'] ?? ''),
        'estudio'        => (string) ($_POST['estudio'] ?? ''),
        'profesional_id' => (string) ($_POST['profesional_id'] ?? ''),
        'fecha'          => (string) ($_POST['fecha'] ?? ''),
        'hora'           => (string) ($_POST['hora'] ?? ''),
    ];

    $errores = [];

    $pacientesConectados = pacientes_de_medico($usuario['cuenta_id'], $usuario['ref_id']);
    $idsPacientesConectados = array_map('intval', array_column($pacientesConectados, 'id'));

    $pacienteId        = null;
    $emailPendiente     = null;
    $nombrePendiente    = null;
    $apellidoPendiente  = null;

    if ($modoPaciente === 'existente') {

        $idCandidato = filter_var($_POST['paciente_id'] ?? '', FILTER_VALIDATE_INT);
        if (!$idCandidato || !in_array($idCandidato, $idsPacientesConectados, true)) {
            $errores['paciente_id'] = 'Elegi un paciente valido.';
        } else {
            $pacienteId = $idCandidato;
        }
    } else {
        $errores = validar(
            [
                'paciente_nombre_nuevo'   => REGLAS_PACIENTE['nombre'],
                'paciente_apellido_nuevo' => REGLAS_PACIENTE['apellido'],
            ],
            $_POST
        );

        $emailNuevo = trim((string) ($_POST['paciente_email_nuevo'] ?? ''));
        if (!filter_var($emailNuevo, FILTER_VALIDATE_EMAIL)) {
            $errores['paciente_email_nuevo'] = 'Ingresa un email valido.';
        } else {
            $stmt = db()->prepare("SELECT ref_id FROM cuentas WHERE rol = 'paciente' AND email = ?");
            $stmt->execute([$emailNuevo]);
            $refId = $stmt->fetchColumn();
            if ($refId !== false) {

                $errores['paciente_email_nuevo'] = 'Ya existe una cuenta con ese email. Elegi el paciente desde "Paciente existente".';
            } else {
                $emailPendiente    = $emailNuevo;
                $nombrePendiente   = trim((string) ($_POST['paciente_nombre_nuevo'] ?? ''));
                $apellidoPendiente = trim((string) ($_POST['paciente_apellido_nuevo'] ?? ''));
            }
        }
    }

    [$erroresReserva, $sucursalId, $profesionalId, $marcaTiempo, $estudio] =
        validar_reserva($valores, ESTUDIOS_BASE, $fechaMin, $fechaMax, RANGO_MESES);
    $errores = array_merge($errores, $erroresReserva);

    if ($errores) {
        echo json_encode(['ok' => false, 'errores' => $errores], JSON_UNESCAPED_UNICODE);
        exit;
    }

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

        $datosOrden = recolectar_campos_orden(ORDEN_CAMPOS_ESTUDIO, $_POST);

        $stmtOrden = db()->prepare(
            'INSERT INTO ordenes (paciente_id, cita_id, creado_por, tipo) VALUES (?, ?, ?, ?)'
        );
        $stmtOrden->execute([
            $pacienteId,
            $citaId,
            $usuario['cuenta_id'],
            'estudio',
        ]);
        $ordenId = (int) db()->lastInsertId();

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

        db()->commit();
        echo json_encode([
            'ok'       => true,
            'redirect' => 'agendar-cita-medico.html?ok=' . ($emailPendiente !== null ? 'pendiente' : '1'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        echo json_encode(['ok' => false, 'errores' => ['general' => 'No se pudo guardar la cita. Intenta nuevamente.']], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$pacientesConectados = pacientes_de_medico($usuario['cuenta_id'], $usuario['ref_id']);

echo json_encode([
    'fechaMin'            => $fechaMin,
    'fechaMax'            => $fechaMax,
    'dientes'             => DIENTES_COMPLETO,
    'pacientesConectados' => array_map(static fn (array $p): array => [
        'id'       => (int) $p['id'],
        'nombre'   => $p['nombre'],
        'apellido' => $p['apellido'],
        'ci'       => $p['ci'],
    ], $pacientesConectados),
] + catalogo_reserva(), JSON_UNESCAPED_UNICODE);
