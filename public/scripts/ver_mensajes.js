// Renderiza la tabla de mensajes recibidos
function renderMessages(data) {
    const container = document.getElementById('messagesContainer');
    const statsBox = document.getElementById('statsBox');
    statsBox.innerHTML = `
        <div class="stat"><h3>${data.total}</h3><p>Total de mensajes</p></div>
        <div class="stat"><h3>${data.new}</h3><p>Mensajes nuevos</p></div>
    `;

    if (!data.data || data.data.length === 0) {
        container.innerHTML = '<div class="sin-mensajes"><p>No hay mensajes de contacto todavía.</p></div>';
        return;
    }

    const rows = data.data.map(fila => `
        <tr>
            <td>#${fila.id}</td>
            <td>${escapeHtml(fila.nombre)}</td>
            <td>${escapeHtml(fila.email)}</td>
            <td>${escapeHtml((fila.asunto||'').slice(0,40))}...</td>
            <td>${new Date(fila.fecha_creacion).toLocaleString('es-ES')}</td>
            <td><span class="estado ${fila.estado}">${escapeHtml(capitalize(fila.estado))}</span></td>
            <td><div class="acciones"><button class="btn btn-ver" data-id="${fila.id}" data-row='${encodeURIComponent(JSON.stringify(fila))}'>Ver</button><button class="btn btn-eliminar" data-id="${fila.id}">Eliminar</button></div></td>
        </tr>
    `).join('');

    container.innerHTML = `<table><thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Asunto</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>${rows}</tbody></table>`;

    // handlers
    container.querySelectorAll('.btn-ver').forEach(btn => btn.addEventListener('click', (e) => {
        const row = JSON.parse(decodeURIComponent(e.currentTarget.dataset.row));
        verMensaje(row);
    }));
    container.querySelectorAll('.btn-eliminar').forEach(btn => btn.addEventListener('click', (e) => {
        const id = e.currentTarget.dataset.id;
        eliminarMensaje(id);
    }));
}

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

function cerrarModal() { document.getElementById('modalMensaje').style.display = 'none'; }

function eliminarMensaje(id) {
    if (confirm('¿Estás seguro de que deseas eliminar este mensaje?')) {
        alert('Eliminación no implementada. Se necesita un endpoint para borrar.');
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('modalMensaje');
    if (event.target == modal) { modal.style.display = 'none'; }
}

function escapeHtml(s) { return String(s||'').replace(/[&<>"]+/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
function capitalize(s){ if(!s) return ''; return s.charAt(0).toUpperCase()+s.slice(1); }

// Inicializar: pedir mensajes al servidor
document.addEventListener('DOMContentLoaded', async function(){
    try {
        const res = await fetch('/clinica-imagen/api/ajax/get_contactos.php');
        const data = await res.json();
        if (!data.success) throw new Error(data.message||'Error');
        renderMessages(data);
    } catch (err) {
        document.getElementById('messagesContainer').innerHTML = '<p>Error cargando mensajes.</p>';
        console.error(err);
    }
});
