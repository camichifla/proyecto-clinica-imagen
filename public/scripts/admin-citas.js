// Código cliente para admin-citas (se separa del PHP)

async function loadPending() {
    try {
        const res = await fetch('/clinica-imagen/api/ajax/get_pending_agenda.php');
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Error');
        const container = document.getElementById('pendingList');
        if (data.data.length === 0) {
            container.innerHTML = '<p>No hay solicitudes pendientes.</p>';
            return;
        }
        container.innerHTML = data.data.map(item => `
            <div class="card-solicitud" data-ci="${item.paciente_ci}" data-id="${item.id}">
                <div class="solicitud-info">
                    <p><strong>Paciente:</strong> ${escapeHtml(item.paciente_nombre)} ${escapeHtml(item.paciente_apellido)}</p>
                    <p><strong>CI:</strong> ${escapeHtml(item.paciente_ci)}</p>
                    <p><strong>Correo:</strong> ${escapeHtml(item.paciente_email || 'No registrado')}</p>
                    <p><strong>Teléfono:</strong> ${escapeHtml(item.paciente_telefono || 'No registrado')}</p>
                    <p><strong>Estudio solicitado:</strong> ${escapeHtml(item.estudio)}</p>
                    <p><strong>Profesional:</strong> ${escapeHtml(item.medico || 'Por asignar')}</p>
                    <p><strong>Sucursal:</strong> ${escapeHtml(item.sucursal || 'Por asignar')}</p>
                    <p><strong>Horario propuesto:</strong> ${escapeHtml(item.sucursal === 'Por asignar' ? 'Por asignar' : item.fecha_hora)}</p>
                </div>
                <div class="solicitud-actions">
                    <button class="btn-success" data-action="confirm">Validar y enviar a profesional</button>
                    <button class="btn-danger" data-action="cancel">Denegar solicitud</button>
                </div>
            </div>
        `).join('');

        document.querySelectorAll('#pendingList .card-solicitud').forEach(card => {
            card.querySelector('[data-action="confirm"]').addEventListener('click', async (e) => {
                await updateAgendaState(card.dataset.id, 'confirmada');
            });
            card.querySelector('[data-action="cancel"]').addEventListener('click', async () => {
                openAdminCancelModal(card.dataset.id);
            });
            card.addEventListener('click', (e) => {
                if (e.target.closest('.solicitud-info')) {
                    selectPatient(card.dataset.ci);
                }
            });
        });
    } catch (err) {
        document.getElementById('pendingList').innerHTML = '<p>Error cargando solicitudes.</p>';
        console.error(err);
    }
}

async function updateAgendaState(id, estado, motivoCancelacion = '', schedule = {}) {
    try {
        const res = await fetch('/clinica-imagen/api/ajax/update_agenda_state.php', {
            method: 'POST',
            credentials: 'include',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ cita_id: Number(id), estado: estado, motivo_cancelacion: motivoCancelacion, sucursal: schedule.sucursal || '', fecha_hora: schedule.fechaHora || '' })
        });
        const data = await res.json().catch(() => ({}));
        if (!data.success) throw new Error(data.message||'Error');
        await loadPending();
    } catch (err) {
        console.error(err);
        alert(err.message || 'No se pudo actualizar la cita');
    }
}

async function selectPatient(ci) {
    document.getElementById('historyTitle').textContent = 'Historial Clínico: ' + ci;
    document.getElementById('paciente_ci').value = ci;
    document.getElementById('historyForm').style.display = '';
    const requestsContainer = document.getElementById('patientRequests');
    const downloadLink = document.getElementById('downloadHistory');
    try {
        const res = await fetch('/clinica-imagen/api/ajax/get_user_history.php?ci=' + encodeURIComponent(ci));
        const data = await res.json();
        if (!data.success) throw new Error(data.message||'Error');
        const container = document.getElementById('clinicalHistory');
        if (!data.patient) {
            container.innerHTML = '<p>No hay datos registrados.</p>';
            requestsContainer.innerHTML = '';
            downloadLink.style.display = 'none';
            return;
        }

        document.getElementById('historyTitle').textContent = `Historial Clínico: ${data.patient.name} ${data.patient.surname}`;
        const studies = Array.isArray(data.estudios) ? data.estudios : [];
        requestsContainer.innerHTML = `
            <h3>Solicitudes</h3>
            ${data.pending.length === 0 ? '<p>No hay datos registrados.</p>' : data.pending.map(request => {
                const completed = studies.some(study => study.estudio === request.estudio);
                const statusClass = request.estado === 'cancelada' ? 'cancelada' : (completed ? 'realizada' : 'pendiente');
                const statusText = request.estado === 'cancelada' ? 'Cancelada' : (completed ? 'Realizado' : request.estado);
                return `
                <div class="history-entry">
                    <p><strong>${escapeHtml(request.estudio)}</strong> — Estado: <span class="request-status ${statusClass}">${escapeHtml(statusText)}</span></p>
                    <p>${escapeHtml(request.sucursal)} · ${escapeHtml(request.fecha_hora)}</p>
                    ${request.estado === 'cancelada' ? `<p class="cancellation-reason"><strong>Cancelada por:</strong> ${escapeHtml(request.cancelada_por || 'No indicado')}<br><strong>Motivo:</strong> ${escapeHtml(request.motivo_cancelacion || 'No indicado')}</p>` : ''}
                </div>
            `;
            }).join('')}
        `;

        if (data.data.length === 0 && studies.length === 0) {
            container.innerHTML = '<p>No hay datos registrados.</p>';
            downloadLink.style.display = 'none';
        } else {
            container.innerHTML = data.data.map(h => `
            <div class="history-entry">
                <h5>${escapeHtml(h.creado_en)} — ${escapeHtml(h.tipo)}</h5>
                <p><em>Nota del ${escapeHtml(h.tecnico)}:</em> ${escapeHtml(h.nota)}</p>
            </div>
            `).join('') + studies.map(study => `
            <div class="history-entry study-history-entry">
                <h5>${escapeHtml(study.fecha_estudio)} — Estudio realizado</h5>
                <p><strong>${escapeHtml(study.estudio)}</strong></p>
                <p><em>Técnico:</em> ${escapeHtml(study.tecnico)}</p>
            </div>
            `).join('');
            if (data.data.length > 0) {
                downloadLink.href = '/clinica-imagen/api/ajax/download_historial.php?ci=' + encodeURIComponent(ci);
                downloadLink.style.display = '';
            } else {
                downloadLink.style.display = 'none';
            }
        }
    } catch (err) {
        document.getElementById('clinicalHistory').innerHTML = '<p>No se pudieron cargar los datos.</p>';
        document.getElementById('patientRequests').innerHTML = '';
        document.getElementById('downloadHistory').style.display = 'none';
        console.error(err);
    }
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character]));
}

document.addEventListener('DOMContentLoaded', function () {
    const validateModal = document.getElementById('validateModal');
    const closeValidation = () => { validateModal.classList.add('hidden'); document.body.style.overflow = ''; };
    document.getElementById('validateCancel').addEventListener('click', closeValidation);
    document.getElementById('validateSucursal').addEventListener('change', refreshValidationSlots);
    document.getElementById('validateDate').addEventListener('change', refreshValidationSlots);
    document.getElementById('validateForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const error = document.getElementById('validateError');
        const branch = document.getElementById('validateSucursal').value;
        const dateTime = document.getElementById('validateDateTime').value;
        if (!branch || !dateTime) { error.textContent = 'Selecciona sucursal, fecha y una hora libre.'; return; }
        if (await updateAgendaState(document.getElementById('validateCitaId').value, 'confirmada', '', {sucursal: branch, fechaHora: dateTime})) closeValidation();
    });

    const adminCancelModal = document.getElementById('adminCancelModal');
    const closeAdminCancel = () => { adminCancelModal.classList.add('hidden'); document.body.style.overflow = ''; };
    document.getElementById('adminCancelClose').addEventListener('click', closeAdminCancel);
    document.getElementById('adminCancelForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const reason = document.getElementById('adminCancelReason').value.trim();
        const error = document.getElementById('adminCancelError');
        if (!reason) { error.textContent = 'Escribe el motivo de cancelacion.'; return; }
        if (await updateAgendaState(document.getElementById('adminCancelCitaId').value, 'cancelada', reason)) closeAdminCancel();
    });

    // Form historial
    const historyForm = document.getElementById('historyForm');
    if (historyForm) {
        historyForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const fd = new FormData(historyForm);
            try {
                const res = await fetch('/clinica-imagen/api/ajax/add_history_entry.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (!data.success) throw new Error(data.message||'Error');
                selectPatient(document.getElementById('paciente_ci').value);
                historyForm.reset();
            } catch (err) { alert('Error guardando entrada.'); console.error(err); }
        });
    }

    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const ci = document.getElementById('cedula-search').value.trim();
            if (ci) selectPatient(ci);
        });
    }

    loadPending();
});

function openValidationModal(id) {
    document.getElementById('validateCitaId').value = id;
    document.getElementById('validateSucursal').value = '';
    document.getElementById('validateDate').value = '';
    document.getElementById('validateDateTime').value = '';
    document.getElementById('validateSlots').innerHTML = '<p class="text-muted">Elige sucursal y fecha.</p>';
    document.getElementById('validateError').textContent = '';
    document.getElementById('validateModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function openAdminCancelModal(id) {
    document.getElementById('adminCancelCitaId').value = id;
    document.getElementById('adminCancelReason').value = '';
    document.getElementById('adminCancelError').textContent = '';
    document.getElementById('adminCancelModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    document.getElementById('adminCancelReason').focus();
}

async function refreshValidationSlots() {
    const branch = document.getElementById('validateSucursal').value;
    const date = document.getElementById('validateDate').value;
    document.getElementById('validateDateTime').value = '';
    if (!branch || !date) { document.getElementById('validateSlots').innerHTML = '<p class="text-muted">Elige sucursal y fecha.</p>'; return; }
    const response = await fetch(`/clinica-imagen/api/ajax/get_occupied_slots.php?sucursal=${encodeURIComponent(branch)}&date=${encodeURIComponent(date)}`);
    const data = await response.json();
    renderAdminSlots(date, data.success ? data.occupied : []);
}

function renderAdminSlots(date, occupied) {
    const container = document.getElementById('validateSlots');
    container.innerHTML = buildAdminTimeSlots().map((time) => {
        const busy = occupied.includes(time);
        return `<button type="button" class="time-slot ${busy ? 'occupied' : 'available'}" ${busy ? 'disabled' : ''} data-time="${time}">${time}<span>${busy ? 'Ocupado' : 'Libre'}</span></button>`;
    }).join('');
    container.querySelectorAll('.time-slot.available').forEach((button) => button.addEventListener('click', () => {
        container.querySelectorAll('.selected').forEach((item) => item.classList.remove('selected'));
        button.classList.add('selected');
        document.getElementById('validateDateTime').value = `${date}T${button.dataset.time}`;
    }));
}

function buildAdminTimeSlots() {
    const slots = [];
    for (let minutes = 8 * 60; minutes <= 19 * 60 + 15; minutes += 45) {
        slots.push(`${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`);
    }
    return slots;
}
