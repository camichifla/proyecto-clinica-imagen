<?php
const MENU_POR_ROL = [
    'paciente' => [
        ['href' => 'modificar-usuario.html',       'label' => 'Modificar Usuario'],
        ['href' => 'agendar-cita.html',            'label' => 'Agendar Cita'],
        ['href' => 'ver-resultados.html',          'label' => 'Ver Resultados'],
    ],
    'administrador' => [
        ['href' => 'modificar-usuario.html',       'label' => 'Modificar Usuario'],
        ['href' => 'manejar-usuarios.html',        'label' => 'Manejar Usuarios'],
        ['href' => 'solicitudes-cita.html',        'label' => 'Solicitudes de Cita'],
        ['href' => 'agenda.html',                  'label' => 'Agenda'],
        ['href' => 'enviar-resultados.html',       'label' => 'Enviar Resultados'],
        ['href' => 'buscar-resultados.html',       'label' => 'Buscar Resultados'],
    ],
    'medico' => [
        ['href' => 'modificar-usuario.html',   'label' => 'Modificar Usuario'],
        ['href' => 'agendar-cita-medico.html', 'label' => 'Agendar Cita para Paciente'],
        ['href' => 'resultados.html',          'label' => 'Resultados'],
    ],
    'profesional' => [
        ['href' => 'modificar-usuario.html',      'label' => 'Modificar Cuenta'],
        ['href' => 'ver-agenda.html',              'label' => 'Ver Agenda'],
        ['href' => 'historial-pacientes.html',    'label' => 'Historial de Pacientes'],
        ['href' => 'cargar-resultados.html',      'label' => 'Cargar Observaciones y Resultados'],
    ],
];
