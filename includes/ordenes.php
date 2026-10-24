<?php
// Field keys match ORDEN_CAMPOS_ESTUDIO in api/agendar-cita-medico.php.
require_once __DIR__ . '/estudios.php';

// campo => readable label, in display order. Applies to the checkbox
// selections (orden_estudio_selecciones) and to the orden_estudio columns.
const ORDEN_ETIQUETAS = [
    'radio_intra'                 => 'Radiografias intrabucales (piezas)',
    'radio_intra_tipo'            => 'Tipo de radiografía intrabucal',
    'otros_estudios_extraorales'  => 'Otros estudios extraorales',
    'radio_extraoral'             => 'Radiografias extraorales',
    'opt_indicacion'              => 'Indicacion de panoramica',
    'telerradio_perfil_tipo'      => 'Telerradiografia de perfil',
    'telerradio_frontal_analisis' => 'Telerradiografia frontal con analisis',
    'estudio_cefalo_compu'        => 'Estudios cefalometricos computarizados',
    'estudio_cefalo_compu_otros'  => 'Otros estudios cefalometricos',
    'fotografias'                 => 'Fotografias',
    'fotografia_interes'          => 'Interes de las fotografias',
    'modelos_digitales'           => 'Modelos digitales',
    'ortodoncia_marca'            => 'Marca de ortodoncia',
    'ortodoncia_tipo'             => 'Tipo de ortodoncia',
    'ortodoncia_info_clinica'     => 'Información clínica de ortodoncia',
    'tomo_cone_beam'              => 'Tomografía Cone Beam (zona)',
    'tomo_cone_beam_tipo'         => 'Tipo de tomografía',
    'tomo_elementos_sueltos'      => 'Elementos sueltos',
    'tomo_tipo_estudio'           => 'Tomografía indicada para',
    'interes_estudio_tomo'        => 'Interés del estudio de tomografía',
    'guia_implantes_servicio'     => 'Cirugia guiada: servicio',
    'guia_implante_tipo'          => 'Tipo de guia de implante',
    'implante_marca'              => 'Marca del implante',
    'implante_ubicacion'          => 'Ubicación del implante',
    'implante_fecha_cirugia'      => 'Fecha de cirugia',
    'realidad_virtual_m3dmix'     => 'Realidad virtual M3DMIX',
    'ecografias'                  => 'Ecografias',
    'eco_interes'                 => 'Interes de la ecografia',
    'otros_servicios'             => 'Otros servicios',
    'solo_escaneo_interes'        => 'Interés del escaneo',
    'contencion_arcada'           => 'Contencion: arcada',
    'contencion_grosor'           => 'Contencion: grosor',
    'contencion_forma'            => 'Contencion: forma',
    'blanqueamiento_arcada'       => 'Blanqueamiento: arcada',
    'protector_bucal_color'       => 'Color del protector bucal',
];

/**
 * Newest first. $pacienteId must come from the session.
 *
 * @return array<int,array<string,mixed>>
 */
function ordenes_de_paciente(PDO $db, int $pacienteId): array
{
    $stmt = $db->prepare(
        'SELECT o.id, o.tipo, o.creado_en, c.estudio AS cita_estudio, c.fecha_hora_solicitada,
                m.nombre AS medico_nombre, m.apellido AS medico_apellido, u.email AS medico_email
           FROM ordenes o
           LEFT JOIN citas c   ON c.id = o.cita_id
           LEFT JOIN cuentas u ON u.id = o.creado_por AND u.rol = \'medico\'
           LEFT JOIN medicos m ON m.id = u.ref_id
          WHERE o.paciente_id = ?
          ORDER BY o.creado_en DESC, o.id DESC'
    );
    $stmt->execute([$pacienteId]);
    $ordenes = $stmt->fetchAll();
    if (!$ordenes) {
        return [];
    }

    $ids = array_map('intval', array_column($ordenes, 'id'));
    $marcadores = implode(',', array_fill(0, count($ids), '?'));

    $selecciones = [];
    $stmt = $db->prepare("SELECT orden_id, campo, opcion FROM orden_estudio_selecciones WHERE orden_id IN ({$marcadores}) ORDER BY opcion");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $f) {
        $selecciones[(int) $f['orden_id']][$f['campo']][] = $f['opcion'];
    }

    $columnas = [];
    $stmt = $db->prepare("SELECT * FROM orden_estudio WHERE orden_id IN ({$marcadores})");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $f) {
        $columnas[(int) $f['orden_id']] = $f;
    }

    $etiquetasEstudio = estudios_etiquetas();
    return array_map(static function (array $o) use ($selecciones, $columnas, $etiquetasEstudio): array {
        $id = (int) $o['id'];
        $detalle = [];
        foreach (ORDEN_ETIQUETAS as $campo => $etiqueta) {
            if (isset($selecciones[$id][$campo])) {
                $valor = implode(', ', $selecciones[$id][$campo]);
            } else {
                $v = $columnas[$id][$campo] ?? null;
                $valor = $campo === 'telerradio_frontal_analisis' ? ($v ? 'Sí' : '') : (string) ($v ?? '');
            }
            if ($valor !== '') {
                $detalle[] = ['etiqueta' => $etiqueta, 'valor' => $valor];
            }
        }
        return [
            'id'           => $id,
            'fecha'        => $o['creado_en'],
            'tipo'         => $o['tipo'],
            'tipo_label'   => $o['tipo'] === 'alineadores' ? 'Alineadores' : 'Orden de estudio',
            'cita_estudio' => $o['cita_estudio'] !== null ? ($etiquetasEstudio[$o['cita_estudio']] ?? $o['cita_estudio']) : null,
            'cita_fecha'   => $o['fecha_hora_solicitada'],
            'medico'       => $o['medico_nombre'] !== null ? $o['medico_apellido'] . ', ' . $o['medico_nombre'] : null,
            'medico_email' => $o['medico_nombre'] !== null ? $o['medico_email'] : null,
            'detalle'      => $detalle,
        ];
    }, $ordenes);
}
