document.addEventListener('DOMContentLoaded', async function () {
    const ordenActiva = document.getElementById('ordenActiva');
    const citasTable = document.getElementById('citasTable');
    const estudiosList = document.getElementById('estudiosList');

    try {
        const data = await jsonFetch('/clinica-imagen/api/ajax/get_patient_dashboard.php');
        const user = data.user || {};
        const agendas = Array.isArray(data.agendas) ? data.agendas : [];
        const estudios = Array.isArray(data.estudios) ? data.estudios : [];
        const greeting = document.getElementById('userGreeting');

        if (greeting && user.name) {
            const fullName = [user.name, user.surname].filter(Boolean).join(' ');
            greeting.textContent = `Bienvenido ${fullName}`;
        }

        const activeAgenda = agendas.find((agenda) => ['pendiente', 'confirmada'].includes(agenda.estado));
        if (activeAgenda) {
            ordenActiva.innerHTML = `
                <h3><span class="dot-active" aria-hidden="true"></span> Orden Médica Activa</h3>
                <p><strong>Profesional:</strong> ${escapeDashboardHtml(activeAgenda.medico)}</p>
                <p><strong>Estudio Autorizado:</strong> ${escapeDashboardHtml(activeAgenda.estudio)}</p>
                <p><strong>Sucursal:</strong> ${escapeDashboardHtml(activeAgenda.sucursal)}</p>
                <p class="text-muted">Estado: ${escapeDashboardHtml(formatAgendaStatus(activeAgenda.estado))} - ${activeAgenda.sucursal === 'Por asignar' ? 'Sucursal y turno pendientes' : formatDashboardDate(activeAgenda.fecha_hora)}</p>
            `;
        } else {
            ordenActiva.innerHTML = '<h3><span class="dot-inactive" aria-hidden="true"></span> Sin ordenes activas</h3><p class="text-muted">No hay citas agendadas actualmente.</p>';
        }

        if (agendas.length === 0) {
            citasTable.innerHTML = '<p class="text-muted">No hay citas programadas aún.</p>';
        } else {
            citasTable.innerHTML = `
                <table class="data-table">
                    <thead><tr><th>Estudio</th><th>Sucursal</th><th>Fecha y Hora</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>${agendas.map((agenda) => `
                        <tr>
                            <td>${escapeDashboardHtml(agenda.estudio)}</td>
                            <td>${agenda.estado === 'confirmada' ? renderScheduleBranch(agenda) : '<span class="schedule-pending">Por asignar</span>'}</td>
                            <td>${agenda.estado === 'confirmada' ? renderScheduleDate(agenda) : '<span class="schedule-pending">Por asignar</span>'}</td>
                            <td><span class="status-badge ${agenda.estado === 'confirmada' ? 'confirmada' : agenda.estado === 'cancelada' ? 'cancelada' : 'pendiente'}">${escapeDashboardHtml(formatAgendaStatus(agenda.estado))}</span>${agenda.estado === 'cancelada' && agenda.motivo_cancelacion ? `<p class="cancellation-reason"><strong>Cancelada por:</strong> ${escapeDashboardHtml(formatCanceller(agenda.cancelada_por))}<br><strong>Motivo:</strong> ${escapeDashboardHtml(agenda.motivo_cancelacion)}</p>` : ''}</td>
                            <td>${agenda.estado === 'confirmada' && (!agenda.sucursal || agenda.sucursal === 'Por asignar') ? `<button type="button" class="btn-small save-schedule" data-cita-id="${Number(agenda.id)}">Guardar turno</button>` : ''}${['pendiente', 'confirmada'].includes(agenda.estado) ? `<button type="button" class="btn-small btn-cancelar-cita" data-cita-id="${Number(agenda.id)}">Cancelar cita</button>` : ''}</td>
                        </tr>
                    `).join('')}</tbody>
                </table>
            `;
        }

        renderStudies(estudiosList, estudios);

        document.querySelectorAll('.btn-cancelar-cita').forEach((button) => {
            button.addEventListener('click', () => openPatientCancelModal(Number(button.dataset.citaId)));
        });
        initializeScheduleEditors();
    } catch (error) {
        ordenActiva.innerHTML = '<p class="text-muted">No se pudo cargar la información.</p>';
        citasTable.innerHTML = '<p class="text-muted">No se pudieron cargar las citas.</p>';
        estudiosList.innerHTML = '<p class="text-muted">No se pudieron cargar los estudios.</p>';
        console.error(error);
    }
});

const scheduleBranches = ['Sucursal Durazno', 'Sucursal Libertad', 'Sucursal Colonia', 'Sucursal Las Piedras', 'Sucursal Punta del Este', 'Sucursal Caudillos', 'Sucursal Nuevo Centro', 'Sucursal Montevideo Shopping', 'Sucursal Barra de Carrasco', 'Sucursal Atlantida', 'Sucursal Lagomar'];

function renderScheduleBranch(agenda) {
    if (agenda.sucursal && agenda.sucursal !== 'Por asignar') {
        return `<span class="schedule-fixed">${escapeDashboardHtml(agenda.sucursal)}</span>`;
    }
    return `<select class="schedule-branch" data-cita-id="${Number(agenda.id)}"><option value="">Elegir sucursal</option>${scheduleBranches.map((branch) => `<option ${branch === agenda.sucursal ? 'selected' : ''}>${branch}</option>`).join('')}</select>`;
}

function renderScheduleDate(agenda) {
    const hasSchedule = agenda.sucursal && agenda.sucursal !== 'Por asignar';
    if (hasSchedule) {
        return `<span class="schedule-fixed">${formatDashboardDate(agenda.fecha_hora)}</span>`;
    }
    const date = hasSchedule ? String(agenda.fecha_hora || '').slice(0, 10) : '';
    const time = hasSchedule ? String(agenda.fecha_hora || '').slice(11, 16) : '';
    return `<div class="schedule-editor" data-cita-id="${Number(agenda.id)}"><input class="schedule-date" type="date" value="${escapeDashboardHtml(date)}" min="${new Date().toISOString().slice(0, 10)}"><div class="schedule-slots" aria-live="polite"><p class="text-muted">Elige sucursal y fecha.</p></div><input class="schedule-time" type="hidden" value="${escapeDashboardHtml(time)}"></div>`;
}

async function initializeScheduleEditors() {
    document.querySelectorAll('.schedule-editor').forEach(async (editor) => {
        const dateInput = editor.querySelector('.schedule-date');
        const branch = document.querySelector(`.schedule-branch[data-cita-id="${editor.dataset.citaId}"]`);
        const refresh = async () => {
            editor.querySelector('.schedule-time').value = '';
            if (!branch.value || !dateInput.value) { editor.querySelector('.schedule-slots').innerHTML = '<p class="text-muted">Elige sucursal y fecha.</p>'; return; }
            const response = await fetch(`/clinica-imagen/api/ajax/get_occupied_slots.php?sucursal=${encodeURIComponent(branch.value)}&date=${encodeURIComponent(dateInput.value)}`);
            const data = await response.json();
            renderDashboardSlots(editor, dateInput.value, data.success ? data.occupied : []);
        };
        branch.addEventListener('change', refresh);
        dateInput.addEventListener('change', refresh);
        if (branch.value && dateInput.value) await refresh();
    });
    document.querySelectorAll('.save-schedule').forEach((button) => button.addEventListener('click', savePatientSchedule));
}

function renderDashboardSlots(editor, date, occupied) {
    const current = editor.querySelector('.schedule-time').value;
    const slots = buildDashboardTimeSlots();
    editor.querySelector('.schedule-slots').innerHTML = slots.map((time) => {
        const busy = occupied.includes(time) && time !== current;
        return `<button type="button" class="time-slot ${busy ? 'occupied' : 'available'} ${time === current ? 'selected' : ''}" ${busy ? 'disabled' : ''} data-time="${time}">${time}<span>${busy ? 'Ocupado' : 'Libre'}</span></button>`;
    }).join('');
    editor.querySelectorAll('.time-slot.available').forEach((slot) => slot.addEventListener('click', () => {
        editor.querySelectorAll('.time-slot.selected').forEach((item) => item.classList.remove('selected'));
        slot.classList.add('selected');
        editor.querySelector('.schedule-time').value = slot.dataset.time;
    }));
}

function buildDashboardTimeSlots() {
    const slots = [];
    for (let minutes = 8 * 60; minutes <= 19 * 60 + 15; minutes += 45) slots.push(`${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`);
    return slots;
}

async function savePatientSchedule(event) {
    const row = event.currentTarget.closest('tr');
    const editor = row.querySelector('.schedule-editor');
    const branch = row.querySelector('.schedule-branch').value;
    const date = editor.querySelector('.schedule-date').value;
    const time = editor.querySelector('.schedule-time').value;
    if (!branch || !date || !time) { window.alert('Selecciona sucursal, fecha y una hora libre.'); return; }
    const response = await fetch('/clinica-imagen/api/ajax/update_patient_schedule.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({cita_id: Number(event.currentTarget.dataset.citaId), sucursal: branch, fecha_hora: `${date}T${time}`})});
    const data = await response.json();
    if (!response.ok || !data.success) { window.alert(data.message || 'No se pudo guardar el turno.'); return; }
    window.location.reload();
}

function openPatientCancelModal(citaId) {
    const modal = document.getElementById('cancelModal');
    document.getElementById('cancelCitaId').value = citaId;
    document.getElementById('patientCancelReason').value = '';
    document.getElementById('cancelError').textContent = '';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    document.getElementById('patientCancelReason').focus();
}

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('cancelModal');
    const close = () => { modal.classList.add('hidden'); document.body.style.overflow = ''; };
    document.getElementById('cancelClose').addEventListener('click', close);
    document.getElementById('patientCancelForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const errorBox = document.getElementById('cancelError');
        const reason = document.getElementById('patientCancelReason').value.trim();
        if (!reason) { errorBox.textContent = 'Escribe el motivo de cancelacion.'; return; }
        try {
            const response = await fetch('/clinica-imagen/api/cancelar_cita.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ cita_id: Number(document.getElementById('cancelCitaId').value), motivo_cancelacion: reason }) });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'No se pudo cancelar la cita.');
            close();
            window.location.reload();
        } catch (error) { errorBox.textContent = error.message; }
    });
});

function renderStudies(container, studies) {
    if (studies.length === 0) {
        container.innerHTML = '<p class="text-muted">No hay estudios ejecutados registrados.</p>';
        return;
    }

    container.innerHTML = studies.map((study) => {
        const viewer = study.visor_url
            ? `<a class="btn-small" href="${escapeDashboardHtml(study.visor_url)}" target="_blank" rel="noopener">Ver en Visor</a>`
            : '<button class="btn-small" type="button" disabled>Visor no disponible</button>';
        const pdf = study.pdf_url
            ? `<a href="${escapeDashboardHtml(study.pdf_url)}" class="btn-small outline" download>Descargar Informe PDF</a>`
            : '<button class="btn-small outline" type="button" disabled>PDF no disponible</button>';
        const preview = study.visor_url
            ? `<img src="${escapeDashboardHtml(study.visor_url)}" alt="Imagen del estudio ${escapeDashboardHtml(study.estudio)}">`
            : '<p class="text-muted">No hay imagen disponible para este estudio.</p>';
        return `<article class="card-estudio"><h4>${escapeDashboardHtml(study.estudio)}</h4><p class="text-muted">Fecha: ${formatDashboardDate(study.fecha_estudio)} | Técnico: ${escapeDashboardHtml(study.tecnico)}</p><div class="visor-simulado" aria-label="Visor de imagenología">${preview}</div><div class="actions-estudio">${viewer}${pdf}</div></article>`;
    }).join('');
}

function formatDashboardDate(value) {
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? escapeDashboardHtml(value) : date.toLocaleString('es-ES');
}

function capitalizeDashboard(value) {
    const text = String(value || '');
    return text.charAt(0).toUpperCase() + text.slice(1);
}

function formatAgendaStatus(value) {
    return {pendiente: 'Pendiente', confirmada: 'Confirmada', cancelada: 'Cancelada'}[value] || capitalizeDashboard(value);
}

function formatCanceller(value) {
    return value === 'administrativo' ? 'Administrativo' : value === 'paciente' ? 'Paciente' : 'No indicado';
}

function escapeDashboardHtml(value) {
    return String(value || '').replace(/[&<>"']/g, (character) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character]));
}
