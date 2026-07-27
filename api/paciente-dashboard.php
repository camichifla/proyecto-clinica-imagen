<?php
require_once __DIR__ . '/auth.php';
requireRole('patient');

$usuario = false;
$ordenActiva = null;
$citasProgramadas = [];

$host = '127.0.0.1';
$db   = 'users_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $id_usuario = getUserId();

    $stmt = $pdo->prepare('SELECT name, surname, CI FROM users WHERE CI = ?');
    $stmt->execute([$id_usuario]);
    $usuario = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT * FROM agenda WHERE paciente_ci = ? AND estado IN ('pendiente','confirmada') ORDER BY fecha_hora ASC LIMIT 1");
    $stmt->execute([$id_usuario]);
    $ordenActiva = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT * FROM agenda WHERE paciente_ci = ? AND estado IN ('pendiente','confirmada') ORDER BY fecha_hora ASC");
    $stmt->execute([$id_usuario]);
    $citasProgramadas = $stmt->fetchAll();
} catch (\PDOException $e) {
    error_log("Error de conexión: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal del Paciente — Clínica Imagen</title>
    <link rel="stylesheet" href="../public/css/normalize.css">
    <link rel="stylesheet" href="../public/css/variables.css">
    <link rel="stylesheet" href="../public/css/styles.css">
</head>
<body>

    <header class="main-header shadow">
        <div class="container header-flex">
            <div class="logo">
                <a href="../public/index.html">
                    <img src="../public/images/logo.png" alt="Clínica Imagen">
                </a>
                <span class="badge-portal">Portal Paciente</span>
            </div>
            <nav class="main-nav" aria-label="Opciones de sesión">
                <span class="user-name">
                    <?php if ($usuario): ?>
                        Hola, <?php echo htmlspecialchars($usuario['name'] . ' ' . $usuario['surname']); ?> (C.I: <?php echo htmlspecialchars($usuario['CI']); ?>)
                    <?php else: ?>
                        Invitado (Inicia sesión)
                    <?php endif; ?>
                </span>
                <a href="../api/logout.php" class="btn-logout">Cerrar Sesión</a>
                <button id="agendaToggle" class="btn-primary">Agenda</button>
            </nav>
        </div>
    </header>

    <div id="agendaModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="agendaTitle">
        <div class="modal-content agenda-modal-content">
            <div class="agenda-header">
                <div>
                    <h2 id="agendaTitle">Crear cita</h2>
                    <p class="text-muted">Selecciona estudio, médico, sucursal y día.</p>
                </div>
                <button id="agendaClose" class="btn-small outline" type="button">Cerrar</button>
            </div>
            <form id="agendaForm" class="agenda-form">
                <div class="form-group">
                    <label for="selectEstudio">Seleccionar Estudio</label>
                    <select id="selectEstudio" name="estudio" required>
                        <option value="">Elige un estudio</option>
                        <option value="Tomografía Dental">Tomografía Dental</option>
                        <option value="Rayos X Panorámico">Rayos X Panorámico</option>
                        <option value="Resonancia Magnética Facial">Resonancia Magnética Facial</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="selectMedico">Seleccionar Médico</label>
                    <select id="selectMedico" name="medico" required>
                        <option value="">Elige un médico</option>
                        <option value="Dr. Javier Rouss">Dr. Javier Rouss</option>
                        <option value="Dra. Carla Méndez">Dra. Carla Méndez</option>
                        <option value="Dr. Matías Ferrari">Dr. Matías Ferrari</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="selectSucursal">Seleccionar Sucursal</label>
                    <select id="selectSucursal" name="sucursal" required>
                        <option value="">Elige una sucursal</option>
                        <option value="Sucursal Durazno">Sucursal Durazno</option>
                        <option value="Sucursal Libertad">Sucursal Libertad</option>
                        <option value="Sucursal Colonia">Sucursal Colonia</option>
                        <option value="Sucursal Las Piedras">Sucursal Las Piedras</option>
                        <option value="Sucursal Punta del Este">Sucursal Punta del Este</option>
                        <option value="Sucursal Caudillos">Sucursal Caudillos</option>
                        <option value="Sucursal Nuevo Centro">Sucursal Nuevo Centro</option>
                        <option value="Sucursal Montevideo Shopping">Sucursal Montevideo Shopping</option>
                        <option value="Sucursal Barra de Carrasco">Sucursal Barra de Carrasco</option>
                        <option value="Sucursal Atlantida">Sucursal Atlantida</option>
                        <option value="Sucursal Lagomar">Sucursal Lagomar</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="selectFechaHora">Fecha y Hora</label>
                    <input id="selectFechaHora" name="fecha_hora" type="datetime-local" required>
                </div>
                <div class="agenda-error-text" id="agendaError"></div>
                <div class="agenda-actions">
                    <button type="submit" class="btn-primary">Guardar cita</button>
                    <button id="agendaCancel" type="button" class="btn-danger">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <main class="container dashboard-layout">
        <aside class="sidebar-referencia" aria-label="Orden médica activa">
            <div class="card-odontologo">
                <?php if (!empty($ordenActiva)): ?>
                    <h3><span class="dot-active" aria-hidden="true"></span> Orden Médica Activa</h3>
                    <p><strong>Profesional:</strong> <?php echo htmlspecialchars($ordenActiva['medico']); ?></p>
                    <p><strong>Estudio Autorizado:</strong> <?php echo htmlspecialchars($ordenActiva['estudio']); ?></p>
                    <p><strong>Sucursal:</strong> <?php echo htmlspecialchars($ordenActiva['sucursal']); ?></p>
                    <p class="text-muted">Estado: <?php echo htmlspecialchars(ucfirst($ordenActiva['estado'])); ?> - <?php echo date('d/m/Y H:i', strtotime($ordenActiva['fecha_hora'])); ?></p>
                <?php else: ?>
                    <h3><span class="dot-inactive" aria-hidden="true"></span> Sin ordenes activas</h3>
                    <p class="text-muted">No hay citas agendadas actualmente.</p>
                <?php endif; ?>
            </div>
        </aside>

        <section class="main-panel">
            <div class="panel-section">
                <h2>Mis Citas Programadas</h2>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Estudio</th>
                                <th scope="col">Sucursal</th>
                                <th scope="col">Fecha y Hora</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($citasProgramadas) > 0): ?>
                                <?php foreach ($citasProgramadas as $cita): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cita['estudio']); ?></td>
                                        <td><?php echo htmlspecialchars($cita['sucursal']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($cita['fecha_hora'])); ?></td>
                                        <td>
                                            <?php
                                                $badgeClass = $cita['estado'] === 'confirmada' ? 'confirmada' : 'pendiente';
                                                $badgeText = $cita['estado'] === 'confirmada' ? 'Confirmada' : 'Pendiente de Validación';
                                            ?>
                                            <span class="status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($badgeText); ?></span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn-small btn-cancelar-cita" data-cita-id="<?php echo (int) $cita['id']; ?>">Cancelar cita</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-muted">No hay citas programadas aún.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel-section">
                <h2>Mis Estudios Ejecutados</h2>
                <div class="grid-estudios">
                    <div class="card-estudio">
                        <h4>Tomografía Dental Axial</h4>
                        <p class="text-muted">Fecha: 15/05/2026 | Técnico: Tec. Alana Gómez</p>

                        <div class="visor-simulado" aria-label="Visor de imagenología">
                            <p>[ Visor Imagenología Integrado ]</p>
                        </div>

                        <div class="actions-estudio">
                            <button class="btn-small">Ver en Visor</button>
                            <a href="#" class="btn-small outline">Descargar Informe PDF</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <script src="../public/scripts/paciente-agenda.js"></script>
</body>
</html>
