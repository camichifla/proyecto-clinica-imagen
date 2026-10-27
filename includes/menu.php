<?php
// Menu per role. The list stays flat (sesion.js also uses it as the page allowlist);
// consecutive items sharing a 'grupo' are rendered as one collapsible section, and the
// 'cuenta' item opens from the user icon next to the name instead of the list.
const MENU_POR_ROL = [
    'paciente' => [
        ['href' => 'agendar-cita.html',            'label' => 'Agendar cita'],
        ['href' => 'ver-resultados.html',          'label' => 'Ver resultados'],
        ['href' => 'modificar-usuario.html',       'label' => 'Mi cuenta',         'cuenta' => true],
    ],
    'administrador' => [
        ['href' => 'panel.html',                   'label' => 'Panel'],
        ['href' => 'agenda.html',                  'label' => 'Agenda',                    'grupo' => 'Agenda y citas'],
        ['href' => 'turnos.html',                  'label' => 'Turnos',                    'grupo' => 'Agenda y citas'],
        ['href' => 'solicitudes-cita.html',        'label' => 'Solicitudes de cita',       'grupo' => 'Agenda y citas'],
        ['href' => 'nueva-cita.html',              'label' => 'Nueva cita',                'grupo' => 'Agenda y citas'],
        ['href' => 'cargar-resultados.html',       'label' => 'Cargar resultados',         'grupo' => 'Resultados'],
        ['href' => 'resultados.html',              'label' => 'Resultados',         'grupo' => 'Resultados'],
        ['href' => 'manejar-usuarios.html',        'label' => 'Usuarios del sistema',      'grupo' => 'Personas'],
        ['href' => 'gestion-estudios.html',        'label' => 'Estudios',                  'grupo' => 'Clínica'],
        ['href' => 'gestion-sucursales.html',      'label' => 'Sedes',                'grupo' => 'Clínica'],
        ['href' => 'mensajes-contacto.html',       'label' => 'Mensajes de contacto',      'grupo' => 'Comunicación y seguridad'],
        ['href' => 'eventos.html',                 'label' => 'Eventos de seguridad',      'grupo' => 'Comunicación y seguridad'],
        ['href' => 'modificar-usuario.html',       'label' => 'Mi cuenta',         'cuenta' => true],
    ],
    'recepcionista' => [
        ['href' => 'panel.html',                   'label' => 'Panel'],
        ['href' => 'agenda.html',                  'label' => 'Agenda',                    'grupo' => 'Agenda y citas'],
        ['href' => 'turnos.html',                  'label' => 'Turnos',                    'grupo' => 'Agenda y citas'],
        ['href' => 'solicitudes-cita.html',        'label' => 'Solicitudes de cita',       'grupo' => 'Agenda y citas'],
        ['href' => 'nueva-cita.html',              'label' => 'Nueva cita',                'grupo' => 'Agenda y citas'],
        ['href' => 'gestion-pacientes.html',       'label' => 'Pacientes'],
        ['href' => 'mensajes-contacto.html',       'label' => 'Mensajes de contacto'],
        ['href' => 'modificar-usuario.html',       'label' => 'Mi cuenta',          'cuenta' => true],
    ],
    'medico' => [
        ['href' => 'agendar-cita-medico.html',     'label' => 'Agendar cita para paciente'],
        ['href' => 'resultados.html',              'label' => 'Resultados'],
        ['href' => 'modificar-usuario.html',       'label' => 'Mi cuenta',         'cuenta' => true],
    ],
    'profesional' => [
        ['href' => 'agenda.html',                  'label' => 'Agenda'],
        ['href' => 'resultados.html',              'label' => 'Resultados'],
        ['href' => 'cargar-resultados.html',       'label' => 'Cargar resultados'],
        ['href' => 'modificar-usuario.html',       'label' => 'Mi cuenta',          'cuenta' => true],
    ],
];
