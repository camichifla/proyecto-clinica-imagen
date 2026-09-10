document.addEventListener('DOMContentLoaded', () => {
    loadProfessionalUser();
    loadProfessionalPending();
    document.getElementById('professionalSearchForm').addEventListener('submit', (event) => {
        event.preventDefault();
        const ci = document.getElementById('professionalPatientCi').value.trim();
        if (ci) loadProfessionalPatient(ci);
    });
    document.getElementById('professionalHistoryForm').addEventListener('submit', saveProfessionalHistory);
    document.getElementById('professionalStudyForm').addEventListener('submit', saveProfessionalStudy);
});

async function loadProfessionalUser() {
    try {
        const response = await fetch('/clinica-imagen/api/ajax/get_session_user.php', {credentials: 'include'});
        const data = await response.json();
        if (data.success && data.user) {
            document.getElementById('professionalGreeting').textContent = `Bienvenido ${[data.user.name, data.user.surname].filter(Boolean).join(' ')}`;
        }
    } catch (error) { console.error(error); }
}

async function loadProfessionalPending() {
    const container = document.getElementById('professionalPendingList');
    try {
        const response = await fetch('/clinica-imagen/api/ajax/get_professional_studies.php', {credentials: 'include'});
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudieron cargar las solicitudes.');
        if (!data.data.length) {
            container.innerHTML = '<p>No hay estudios pendientes.</p>';
            return;
        }
        container.innerHTML = data.data.map((item) => `
            <article class="card-solicitud professional-request" data-id="${Number(item.id)}" data-ci="${escapeProfessional(item.paciente_ci)}">
                <div class="solicitud-info">
                    <p><strong>Paciente:</strong> ${escapeProfessional(item.paciente_nombre)} ${escapeProfessional(item.paciente_apellido)}</p>
                    <p><strong>CI:</strong> ${escapeProfessional(item.paciente_ci)}</p>
                    <p><strong>Correo:</strong> ${escapeProfessional(item.paciente_email || 'No registrado')}</p>
                    <p><strong>Telefono:</strong> ${escapeProfessional(item.paciente_telefono || 'No registrado')}</p>
                    <p><strong>Estudio a realizar:</strong> ${escapeProfessional(item.estudio)}</p>
                    <p><strong>Profesional:</strong> ${escapeProfessional(item.medico || 'Por asignar')}</p>
                    <p><strong>Sucursal:</strong> ${escapeProfessional(item.sucursal || 'Por asignar')}</p>
                    <p><strong>Fecha y hora:</strong> ${escapeProfessional(item.sucursal === 'Por asignar' ? 'Por asignar' : item.fecha_hora)}</p>
                    <p><strong>Estado:</strong> ${escapeProfessional(formatProfessionalStatus(item.estado))}</p>
                </div>
                <div class="solicitud-actions">
                    ${item.estado === 'pendiente' ? '<button class="btn-success" data-action="confirm">Validar solicitud</button><button class="btn-danger" data-action="cancel">Denegar solicitud</button>' : '<span class="status-badge confirmada">Cita confirmada</span>'}
                </div>
            </article>
        `).join('');
        container.querySelectorAll('.professional-request').forEach((card) => {
            card.querySelector('[data-action="confirm"]')?.addEventListener('click', () => changeProfessionalRequest(card.dataset.id, 'confirmada'));
            card.querySelector('[data-action="cancel"]')?.addEventListener('click', () => {
                const reason = window.prompt('Motivo de cancelacion:');
                if (reason && reason.trim()) changeProfessionalRequest(card.dataset.id, 'cancelada', reason.trim());
            });
            card.querySelector('.solicitud-info').addEventListener('click', () => loadProfessionalPatient(card.dataset.ci));
        });
    } catch (error) {
        container.innerHTML = '<p>No se pudieron cargar las solicitudes.</p>';
        console.error(error);
    }
}

async function changeProfessionalRequest(id, state, reason = '') {
    try {
        const response = await fetch('/clinica-imagen/api/ajax/update_agenda_state.php', {
            method: 'POST', credentials: 'include', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({cita_id: Number(id), estado: state, motivo_cancelacion: reason})
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo actualizar la solicitud.');
        loadProfessionalPending();
    } catch (error) { window.alert(error.message); }
}

async function loadProfessionalPatient(ci) {
    const history = document.getElementById('professionalClinicalHistory');
    try {
        const response = await fetch(`/clinica-imagen/api/ajax/get_user_history.php?ci=${encodeURIComponent(ci)}`, {credentials: 'include'});
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success || !data.patient) throw new Error(data.message || 'Paciente no encontrado.');
        document.getElementById('professionalHistoryTitle').textContent = `Historial Clinico: ${data.patient.name} ${data.patient.surname}`;
        document.getElementById('professionalPatientHidden').value = ci;
        document.getElementById('professionalHistoryForm').hidden = false;
        document.getElementById('professionalStudyPatient').value = ci;
        document.getElementById('professionalStudyForm').hidden = false;
        const download = document.getElementById('professionalDownloadHistory');
        download.href = `/clinica-imagen/api/ajax/download_historial.php?ci=${encodeURIComponent(ci)}`;
        download.hidden = data.data.length === 0;
        const requests = Array.isArray(data.pending) ? data.pending : [];
        const studies = Array.isArray(data.estudios) ? data.estudios : [];
        history.innerHTML = data.data.map((entry) => `<article class="history-entry"><h5>${escapeProfessional(entry.creado_en)} - ${escapeProfessional(entry.tipo)}</h5><p><em>Nota de ${escapeProfessional(entry.tecnico)}:</em> ${escapeProfessional(entry.nota)}</p></article>`).join('') + studies.map((study) => `<article class="history-entry study-history-entry"><h5>${escapeProfessional(study.fecha_estudio)} - Estudio realizado</h5><p><strong>${escapeProfessional(study.estudio)}</strong></p><p><em>Técnico:</em> ${escapeProfessional(study.tecnico)}</p>${study.visor_url ? `<a href="${escapeProfessional(study.visor_url)}" target="_blank" rel="noopener">Ver imagen</a>` : ''}${study.pdf_url ? ` <a href="${escapeProfessional(study.pdf_url)}" target="_blank" rel="noopener">Ver informe</a>` : ''}</article>`).join('') || '<p>No hay datos registrados.</p>';
        document.getElementById('professionalPatientRequests').innerHTML = requests.length ? requests.map((request) => {
            const completed = studies.some((study) => study.estudio === request.estudio);
            const statusClass = request.estado === 'cancelada' ? 'cancelada' : (completed ? 'realizada' : 'pendiente');
            const statusText = request.estado === 'cancelada' ? 'Cancelada' : (completed ? 'Realizado' : request.estado);
            return `<div class="history-entry"><p><strong>${escapeProfessional(request.estudio)}</strong> - Estado: <span class="request-status ${statusClass}">${escapeProfessional(statusText)}</span></p><p>${escapeProfessional(request.sucursal)} - ${escapeProfessional(request.fecha_hora)}</p>${request.estado === 'cancelada' ? `<p class="cancellation-reason"><strong>Cancelada por:</strong> ${escapeProfessional(request.cancelada_por || 'No indicado')}<br><strong>Motivo:</strong> ${escapeProfessional(request.motivo_cancelacion || 'No indicado')}</p>` : ''}</div>`;
        }).join('') : '<p>No hay solicitudes registradas.</p>';
    } catch (error) {
        history.innerHTML = `<p>${escapeProfessional(error.message)}</p>`;
    }
}

async function saveProfessionalHistory(event) {
    event.preventDefault();
    const form = event.currentTarget;
    try {
        const response = await fetch('/clinica-imagen/api/ajax/add_history_entry.php', {method: 'POST', body: new FormData(form)});
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo guardar la entrada.');
        const ci = document.getElementById('professionalPatientHidden').value;
        form.reset();
        document.getElementById('professionalPatientHidden').value = ci;
        loadProfessionalPatient(ci);
    } catch (error) { window.alert(error.message); }
}

async function saveProfessionalStudy(event) {
    event.preventDefault();
    const form = event.currentTarget;
    try {
        const response = await fetch('/clinica-imagen/api/ajax/add_study.php', {method: 'POST', body: new FormData(form), credentials: 'include'});
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo guardar el estudio.');
        const ci = document.getElementById('professionalStudyPatient').value;
        form.reset();
        document.getElementById('professionalStudyPatient').value = ci;
        loadProfessionalPatient(ci);
    } catch (error) { window.alert(error.message); }
}

function escapeProfessional(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character]));
}

function formatProfessionalStatus(value) {
    return {pendiente: 'Pendiente', confirmada: 'Confirmada'}[value] || String(value || 'Sin estado');
}
