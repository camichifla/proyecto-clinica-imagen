document.addEventListener('DOMContentLoaded', async function () {
    const ordenActiva = document.getElementById('ordenActiva');
    const citasTable = document.getElementById('citasTable');

    try {
        const data = await jsonFetch('/clinica-imagen/api/ajax/get_patient_dashboard.php');
        const user = data.user || {};
        const agendas = (Array.isArray(data.agendas) ? data.agendas : [])
            .filter((agenda) => ['pendiente', 'confirmada'].includes(agenda.estado));
        const greeting = document.getElementById('userGreeting');

        if (greeting && user.name) {
            greeting.textContent = `Hola, ${user.name}`;
        }

        const activeAgenda = agendas.find((agenda) => ['pendiente', 'confirmada'].includes(agenda.estado));
        if (activeAgenda) {
            ordenActiva.innerHTML = `
                <h3><span class="dot-active" aria-hidden="true"></span> Orden Médica Activa</h3>
                <p><strong>Profesional:</strong> ${escapeDashboardHtml(activeAgenda.medico)}</p>
                <p><strong>Estudio Autorizado:</strong> ${escapeDashboardHtml(activeAgenda.estudio)}</p>
                <p><strong>Sucursal:</strong> ${escapeDashboardHtml(activeAgenda.sucursal)}</p>
                <p class="text-muted">Estado: ${escapeDashboardHtml(capitalizeDashboard(activeAgenda.estado))} - ${formatDashboardDate(activeAgenda.fecha_hora)}</p>
            `;
        } else {
            ordenActiva.innerHTML = '<h3><span class="dot-inactive" aria-hidden="true"></span> Sin ordenes activas</h3><p class="text-muted">No hay citas agendadas actualmente.</p>';
        }

        if (agendas.length === 0) {
            citasTable.innerHTML = '<p class="text-muted">No hay citas programadas aún.</p>';
            return;
        }

        citasTable.innerHTML = `
            <table class="data-table">
                <thead><tr><th>Estudio</th><th>Sucursal</th><th>Fecha y Hora</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>${agendas.map((agenda) => `
                    <tr>
                        <td>${escapeDashboardHtml(agenda.estudio)}</td>
                        <td>${escapeDashboardHtml(agenda.sucursal)}</td>
                        <td>${formatDashboardDate(agenda.fecha_hora)}</td>
                        <td><span class="status-badge ${agenda.estado === 'confirmada' ? 'confirmada' : 'pendiente'}">${escapeDashboardHtml(capitalizeDashboard(agenda.estado))}</span></td>
                        <td>${['pendiente', 'confirmada'].includes(agenda.estado) ? `<button type="button" class="btn-small btn-cancelar-cita" data-cita-id="${Number(agenda.id)}">Cancelar cita</button>` : ''}</td>
                    </tr>
                `).join('')}</tbody>
            </table>
        `;

        document.querySelectorAll('.btn-cancelar-cita').forEach((button) => {
            button.addEventListener('click', async function () {
                if (!window.confirm('¿Deseas cancelar esta cita?')) return;
                try {
                    const response = await fetch('/clinica-imagen/api/cancelar_cita.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({cita_id: Number(this.dataset.citaId)})
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message || 'No se pudo cancelar la cita.');
                    window.location.reload();
                } catch (error) {
                    window.alert(error.message);
                }
            });
        });
    } catch (error) {
        ordenActiva.innerHTML = '<p class="text-muted">No se pudo cargar la información.</p>';
        citasTable.innerHTML = '<p class="text-muted">No se pudieron cargar las citas.</p>';
        console.error(error);
    }
});

function formatDashboardDate(value) {
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? escapeDashboardHtml(value) : date.toLocaleString('es-ES');
}

function capitalizeDashboard(value) {
    const text = String(value || '');
    return text.charAt(0).toUpperCase() + text.slice(1);
}

function escapeDashboardHtml(value) {
    return String(value || '').replace(/[&<>"']/g, (character) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character]));
}
