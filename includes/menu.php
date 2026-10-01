<?php
// includes/menu.php — role-based dropdown menu items, shared by index.php
// and every logged-in page so navigation stays consistent across pages.

const MENU_POR_ROL = [
    'paciente' => [
        ['href' => 'modificar-usuario.php',       'label' => 'Modificar Usuario'],
        ['href' => 'agendar-cita.php',             'label' => 'Agendar Cita'],
        ['href' => 'ver-resultados.php',           'label' => 'Ver Resultados'],
    ],
    'administrador' => [
        ['href' => 'modificar-usuario.php',       'label' => 'Modificar Usuario'],
        ['href' => 'manejar-usuarios.php',        'label' => 'Manejar Usuarios'],
        ['href' => 'solicitudes-cita.php',         'label' => 'Solicitudes de Cita'],
        ['href' => 'agenda.php',                   'label' => 'Agenda'],
        ['href' => 'enviar-resultados.php',        'label' => 'Enviar Resultados'],
        ['href' => 'buscar-resultados.php',        'label' => 'Buscar Resultados'],
    ],
    'medico' => [
        ['href' => 'modificar-usuario.php',    'label' => 'Modificar Usuario'],
        ['href' => 'agendar-cita-medico.php',  'label' => 'Agendar Cita para Paciente'],
        ['href' => 'resultados.php',           'label' => 'Resultados'],
    ],
    'profesional' => [
        ['href' => 'modificar-usuario.php',      'label' => 'Modificar Cuenta'],
        ['href' => 'ver-agenda.php',              'label' => 'Ver Agenda'],
        ['href' => 'historial-pacientes.php',     'label' => 'Historial de Pacientes'],
        ['href' => 'cargar-resultados.php',       'label' => 'Cargar Observaciones y Resultados'],
    ],
];

/**
 * Echoes the role-based menu items (as a list of links) for the
 * authenticated user's role. Caller is responsible for the surrounding
 * dropdown toggle markup and the shared "Cerrar sesion" form — see
 * index.php's `.user` block for the reference wiring.
 *
 * @param array{cuenta_id:int,rol:string,ref_id:int,nombre:string} $usuario
 */
function render_menu_usuario(array $usuario): void
{
    $items = MENU_POR_ROL[$usuario['rol']] ?? [];
    ?>
    <ul class="menu-usuario-lista">
      <?php foreach ($items as $item): ?>
        <li><a href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <?php
}
