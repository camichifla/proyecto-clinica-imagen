<?php
require_once '../api/auth.php';
requireRole('admin');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración — Clínica Imagen</title>
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
                <span class="badge-admin">Módulo Interno</span>
            </div>
            <nav class="main-nav" aria-label="Información de sesión">
                <span>Rol: Recepción / Técnico (Sucursal Central)</span>
            </nav>
        </div>
    </header>
    <main class="admin-grid">

        <!-- Búsqueda de historial clínico por cédula -->
        <section class="search-bar-section" aria-label="Búsqueda de paciente">
            <form class="search-form">
                <label for="cedula-search">
                    Buscar Historial Clínico por Cédula de Identidad:
                </label>
                <div class="search-group">
                    <input
                        type="text"
                        id="cedula-search"
                        placeholder="Ej: 12345678"
                        required
                        autocomplete="off"
                        inputmode="numeric"
                    >
                    <button type="submit" class="btn-primary">Buscar Paciente</button>
                </div>
            </form>
        </section>

        <section class="admin-panel-block">
            <h2>Bandeja de Solicitudes Pendientes</h2>

            <div class="card-solicitud">
                <div class="solicitud-info">
                    <p><strong>Paciente:</strong> Juan Pérez</p>
                    <p><strong>Estudio solicitado:</strong> Consulta para Tomografía (Referenciado por Dr. Rodríguez)</p>
                    <p><strong>Horario propuesto:</strong> 04/06/2026 a las 14:30 hs</p>
                </div>
                <div class="solicitud-actions">
                    <button class="btn-success">Confirmar y Validar Cita</button>
                    <button class="btn-danger">Modificar / Cancelar</button>
                </div>
            </div>
        </section>

        <!-- Historial clínico del paciente seleccionado -->
        <section class="admin-panel-block">
            <h2>Historial Clínico Activo: Juan Pérez</h2>

            <div class="clinical-history-box">

                <!-- Entrada existente de evolución -->
                <div class="history-entry">
                    <h5>15/11/2025 — Evolución de Tratamiento</h5>
                    <p>
                        <em>Nota del Técnico:</em> Se observa correcta osificación en el área de la pieza 21
                        tras comparar la radiografía panorámica actual con la de hace 6 meses.
                    </p>
                </div>

                <!-- Formulario para agregar nueva observación al historial -->
                <form class="entry-form">
                    <label for="nuevas-obs">Añadir nuevas observaciones del estudio actual:</label>
                    <textarea
                        id="nuevas-obs"
                        rows="3"
                        placeholder="Escriba aquí el progreso del tratamiento o el plan a seguir..."
                    ></textarea>
                    <button type="submit" class="btn-small">Guardar Entrada en Historial</button>
                </form>

            </div>
        </section>

    </main>

</body>
</html>