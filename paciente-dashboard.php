<?php require_once 'auth.php';
requireRole('patient');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal del Paciente — Clínica Imagen</title>
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>

    <!-- ── Encabezado ────────────────────────────────────── -->
    <header class="main-header shadow">
        <div class="container header-flex">
            <div class="logo">
                <a href="index.html">
                    <img src="./images/logo.png" alt="Clínica Imagen">
                </a>
                <span class="badge-portal">Portal Paciente</span>
            </div>
            <nav class="main-nav" aria-label="Opciones de sesión">
                <!-- Nombre e identificación del paciente activo -->
                <span class="user-name">Hola, Juan Pérez (C.I: 1.234.567-8)</span>
                <a href="index.html" class="btn-logout">Cerrar Sesión</a>
            </nav>
        </div>
    </header>

    <!-- ── Dashboard del paciente ────────────────────────── -->
    <main class="container dashboard-layout">

        <!-- Sidebar: orden médica activa referenciada por el odontólogo -->
        <aside class="sidebar-referencia" aria-label="Orden médica activa">
            <div class="card-odontologo">
                <h3><span class="dot-active" aria-hidden="true"></span> Orden Médica Activa</h3>
                <p><strong>Profesional:</strong> Dr. Carlos Rodríguez (Ortodoncista)</p>
                <p><strong>Estudio Autorizado:</strong> Tomografía Dental + Modelado 3D (DAM)</p>
                <p class="text-muted">Estado: Listo para agendar</p>
            </div>
        </aside>

        <!-- Panel principal: citas y estudios del paciente -->
        <section class="main-panel">

            <!-- Citas programadas -->
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
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Consulta de Estudio (Tomografía)</td>
                                <td>Sucursal Central</td>
                                <td>04/06/2026 — 14:30 hs</td>
                                <td>
                                    <span class="status-badge pendiente">Pendiente de Validación</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Estudios ya realizados con visor integrado -->
            <div class="panel-section">
                <h2>Mis Estudios Ejecutados</h2>
                <div class="grid-estudios">
                    <div class="card-estudio">
                        <h4>Tomografía Dental Axial</h4>
                        <p class="text-muted">Fecha: 15/05/2026 | Técnico: Tec. Alana Gómez</p>

                        <!-- Placeholder del visor de imagenología -->
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

</body>
</html>