<?php
// Configuración de la base de datos
$db_host = 'localhost';
$db_usuario = 'root';
$db_password = '';
$db_nombre = 'users';  // ← CORREGIDO: Solo el nombre de la BD, sin .sql ni rutas

// Crear conexión
$conexion = new mysqli($db_host, $db_usuario, $db_password, $db_nombre);

if ($conexion->connect_error) {
    die('Error de conexión: ' . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");

// Obtener mensajes
$sql = "SELECT * FROM contactos ORDER BY fecha_creacion DESC";
$resultado = $conexion->query($sql);

// Contar mensajes nuevos
$sql_nuevos = "SELECT COUNT(*) as total FROM contactos WHERE estado = 'nuevo'";
$resultado_nuevos = $conexion->query($sql_nuevos);
$fila_nuevos = $resultado_nuevos->fetch_assoc();
$mensajes_nuevos = $fila_nuevos['total'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Mensajes de Contacto</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }

        .stat {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            text-align: center;
        }

        .stat h3 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .stat p {
            font-size: 12px;
            opacity: 0.9;
        }

        .tabla-responsive {
            overflow-x: auto;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #ddd;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .estado {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .estado.nuevo {
            background: #d4edda;
            color: #155724;
        }

        .estado.leido {
            background: #cfe2ff;
            color: #084298;
        }

        .estado.respondido {
            background: #d1ecf1;
            color: #0c5460;
        }

        .estado.archivado {
            background: #e2e3e5;
            color: #383d41;
        }

        .acciones {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-ver {
            background: #667eea;
            color: white;
        }

        .btn-ver:hover {
            background: #5568d3;
        }

        .btn-eliminar {
            background: #e74c3c;
            color: white;
        }

        .btn-eliminar:hover {
            background: #c0392b;
        }

        .sin-mensajes {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .modal h2 {
            margin-bottom: 15px;
            color: #333;
        }

        .modal-field {
            margin-bottom: 15px;
        }

        .modal-field strong {
            display: block;
            color: #667eea;
            margin-bottom: 5px;
        }

        .modal-field p {
            color: #666;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Gestión de Mensajes de Contacto</h1>
        
        <div class="stats">
            <div class="stat">
                <h3><?php echo $resultado->num_rows; ?></h3>
                <p>Total de mensajes</p>
            </div>
            <div class="stat">
                <h3><?php echo $mensajes_nuevos; ?></h3>
                <p>Mensajes nuevos</p>
            </div>
        </div>

        <div class="tabla-responsive">
            <?php if ($resultado->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Asunto</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($fila = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $fila['id']; ?></td>
                                <td><?php echo htmlspecialchars($fila['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($fila['email']); ?></td>
                                <td><?php echo htmlspecialchars(substr($fila['asunto'], 0, 40)); ?>...</td>
                                <td><?php echo date('d/m/Y H:i', strtotime($fila['fecha_creacion'])); ?></td>
                                <td>
                                    <span class="estado <?php echo $fila['estado']; ?>">
                                        <?php echo ucfirst($fila['estado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="acciones">
                                        <button class="btn btn-ver" onclick="verMensaje(<?php echo htmlspecialchars(json_encode($fila)); ?>)">Ver</button>
                                        <button class="btn btn-eliminar" onclick="eliminarMensaje(<?php echo $fila['id']; ?>)">Eliminar</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="sin-mensajes">
                    <p>No hay mensajes de contacto todavía.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para ver mensaje completo -->
    <div id="modalMensaje" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal()">&times;</span>
            <h2 id="modalAsunto"></h2>
            
            <div class="modal-field">
                <strong>De:</strong>
                <p id="modalNombre"></p>
            </div>
            
            <div class="modal-field">
                <strong>Email:</strong>
                <p id="modalEmail"></p>
            </div>
            
            <div class="modal-field">
                <strong>Teléfono:</strong>
                <p id="modalTelefono"></p>
            </div>
            
            <div class="modal-field">
                <strong>Fecha:</strong>
                <p id="modalFecha"></p>
            </div>
            
            <div class="modal-field">
                <strong>Mensaje:</strong>
                <p id="modalMensaje" style="white-space: pre-wrap;"></p>
            </div>
            
            <div class="modal-field">
                <strong>IP Address:</strong>
                <p id="modalIP"></p>
            </div>
        </div>
    </div>

    <script>
        function verMensaje(datos) {
            document.getElementById('modalAsunto').textContent = datos.asunto;
            document.getElementById('modalNombre').textContent = datos.nombre;
            document.getElementById('modalEmail').textContent = datos.email;
            document.getElementById('modalTelefono').textContent = datos.telefono || 'No proporcionado';
            document.getElementById('modalFecha').textContent = new Date(datos.fecha_creacion).toLocaleString('es-ES');
            document.getElementById('modalMensaje').textContent = datos.mensaje;
            document.getElementById('modalIP').textContent = datos.ip_address;
            
            document.getElementById('modalMensaje').style.display = 'block';
            document.getElementById('modalMensaje').parentElement.style.display = 'block';
        }

        function cerrarModal() {
            document.getElementById('modalMensaje').style.display = 'none';
        }

        function eliminarMensaje(id) {
            if (confirm('¿Estás seguro de que deseas eliminar este mensaje?')) {
                // Aquí iría una llamada AJAX para eliminar, pero por ahora es solo confirmación
                alert('Eliminación no implementada. Necesitarías crear un archivo PHP adicional para esto.');
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modalMensaje');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
<?php
$conexion->close();
?>