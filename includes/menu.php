<?php
const MENU_POR_ROL = [
    'paciente' => [
        ['href' => 'modificar-usuario.html',       'label' => 'Modificar Usuario'],
        ['href' => 'agendar-cita.html',            'label' => 'Agendar Cita'],
        ['href' => 'ver-resultados.html',          'label' => 'Ver Resultados'],
    ],
    'administrador' => [
        ['href' => 'modificar-usuario.html',       'label' => 'Modificar Usuario'],
        ['href' => 'panel.html',                   'label' => 'Panel'],
        ['href' => 'turnos.html',                  'label' => 'Turnos'],
        ['href' => 'manejar-usuarios.html',        'label' => 'Manejar Usuarios'],
        ['href' => 'gestion-pacientes.html',       'label' => 'Pacientes'],
        ['href' => 'gestion-profesionales.html',   'label' => 'Profesionales'],
        ['href' => 'gestion-estudios.html',        'label' => 'Estudios'],
        ['href' => 'gestion-sucursales.html',      'label' => 'Sucursales'],
        ['href' => 'eventos.html',                 'label' => 'Eventos de Seguridad'],
        ['href' => 'mensajes-contacto.html',       'label' => 'Mensajes de Contacto'],
        ['href' => 'solicitudes-cita.html',        'label' => 'Solicitudes de Cita'],
        ['href' => 'agenda.html',                  'label' => 'Agenda'],
        ['href' => 'cargar-resultados.html',       'label' => 'Enviar Resultados'],
        ['href' => 'resultados.html',              'label' => 'Buscar Resultados'],
    ],
    'recepcionista' => [
        ['href' => 'modificar-usuario.html',       'label' => 'Modificar Cuenta'],
        ['href' => 'panel.html',                   'label' => 'Panel'],
        ['href' => 'turnos.html',                  'label' => 'Turnos'],
        ['href' => 'gestion-pacientes.html',       'label' => 'Pacientes'],
        ['href' => 'mensajes-contacto.html',       'label' => 'Mensajes de Contacto'],
        ['href' => 'solicitudes-cita.html',        'label' => 'Solicitudes de Cita'],
        ['href' => 'agenda.html',                  'label' => 'Agenda'],
    ],
    'medico' => [
        ['href' => 'modificar-usuario.html',   'label' => 'Modificar Usuario'],
        ['href' => 'agendar-cita-medico.html', 'label' => 'Agendar Cita para Paciente'],
        ['href' => 'resultados.html',          'label' => 'Resultados'],
    ],
    'profesional' => [
        ['href' => 'modificar-usuario.html',      'label' => 'Modificar Cuenta'],
        ['href' => 'agenda.html',                  'label' => 'Ver Agenda'],
        ['href' => 'resultados.html',             'label' => 'Historial de Pacientes'],
        ['href' => 'cargar-resultados.html',      'label' => 'Cargar Observaciones y Resultados'],
    ],
];
