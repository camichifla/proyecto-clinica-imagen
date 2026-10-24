<?php
// Menu per role. The list stays flat (sesion.js also uses it as the page allowlist);
// consecutive items sharing a 'grupo' are rendered as one collapsible section.
const MENU_POR_ROL = [
    'paciente' => [
        ['href' => 'agendar-cita.html',            'label' => 'Agendar Cita'],
        ['href' => 'ver-resultados.html',          'label' => 'Ver Resultados'],
        ['href' => 'modificar-usuario.html',       'label' => 'Modificar Usuario',         'grupo' => 'Mi cuenta'],
    ],
    'administrador' => [
        ['href' => 'panel.html',                   'label' => 'Panel'],
        ['href' => 'agenda.html',                  'label' => 'Agenda',                    'grupo' => 'Agenda y citas'],
        ['href' => 'turnos.html',                  'label' => 'Turnos',                    'grupo' => 'Agenda y citas'],
        ['href' => 'solicitudes-cita.html',        'label' => 'Solicitudes de Cita',       'grupo' => 'Agenda y citas'],
        ['href' => 'cargar-resultados.html',       'label' => 'Enviar Resultados',         'grupo' => 'Resultados'],
        ['href' => 'resultados.html',              'label' => 'Buscar Resultados',         'grupo' => 'Resultados'],
        ['href' => 'gestion-pacientes.html',       'label' => 'Pacientes',                 'grupo' => 'Personas'],
        ['href' => 'gestion-profesionales.html',   'label' => 'Profesionales',             'grupo' => 'Personas'],
        ['href' => 'manejar-usuarios.html',        'label' => 'Usuarios del Sistema',      'grupo' => 'Personas'],
        ['href' => 'gestion-estudios.html',        'label' => 'Estudios',                  'grupo' => 'Clínica'],
        ['href' => 'gestion-sucursales.html',      'label' => 'Sucursales',                'grupo' => 'Clínica'],
        ['href' => 'mensajes-contacto.html',       'label' => 'Mensajes de Contacto',      'grupo' => 'Comunicación y seguridad'],
        ['href' => 'eventos.html',                 'label' => 'Eventos de Seguridad',      'grupo' => 'Comunicación y seguridad'],
        ['href' => 'modificar-usuario.html',       'label' => 'Modificar Usuario',         'grupo' => 'Mi cuenta'],
    ],
    'recepcionista' => [
        ['href' => 'panel.html',                   'label' => 'Panel'],
        ['href' => 'agenda.html',                  'label' => 'Agenda',                    'grupo' => 'Agenda y citas'],
        ['href' => 'turnos.html',                  'label' => 'Turnos',                    'grupo' => 'Agenda y citas'],
        ['href' => 'solicitudes-cita.html',        'label' => 'Solicitudes de Cita',       'grupo' => 'Agenda y citas'],
        ['href' => 'gestion-pacientes.html',       'label' => 'Pacientes'],
        ['href' => 'mensajes-contacto.html',       'label' => 'Mensajes de Contacto'],
        ['href' => 'modificar-usuario.html',       'label' => 'Modificar Cuenta',          'grupo' => 'Mi cuenta'],
    ],
    'medico' => [
        ['href' => 'agendar-cita-medico.html',     'label' => 'Agendar Cita para Paciente'],
        ['href' => 'resultados.html',              'label' => 'Resultados'],
        ['href' => 'modificar-usuario.html',       'label' => 'Modificar Usuario',         'grupo' => 'Mi cuenta'],
    ],
    'profesional' => [
        ['href' => 'agenda.html',                  'label' => 'Ver Agenda'],
        ['href' => 'resultados.html',              'label' => 'Historial de Pacientes'],
        ['href' => 'cargar-resultados.html',       'label' => 'Cargar Observaciones y Resultados'],
        ['href' => 'modificar-usuario.html',       'label' => 'Modificar Cuenta',          'grupo' => 'Mi cuenta'],
    ],
];
