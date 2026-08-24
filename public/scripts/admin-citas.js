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
                    <p><strong>Paciente:</strong> ${item.paciente_nombre} ${item.paciente_apellido} (CI: ${item.paciente_ci})</p>
                    <p><strong>Estudio solicitado:</strong> ${item.estudio} — ${item.medico}</p>
                    <p><strong>Horario propuesto:</strong> ${item.fecha_hora}</p>
                </div>
                <div class="solicitud-actions">
                    <button class="btn-success" data-action="confirm">Confirmar y Validar Cita</button>
                    <button class="btn-danger" data-action="modify">Modificar / Cancelar</button>
                </div>
            </div>
        `).join('');

        document.querySelectorAll('#pendingList .card-solicitud').forEach(card => {
            card.querySelector('[data-action="confirm"]').addEventListener('click', async (e) => {
                const id = card.dataset.id;
                await updateAgendaState(id, 'confirmada');
            });
            card.querySelector('[data-action="modify"]').addEventListener('click', (e) => {
                const ci = card.dataset.ci;
                selectPatient(ci);
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

async function updateAgendaState(id, estado) {
    try {
        const res = await fetch('/clinica-imagen/api/cancelar_cita.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ cita_id: Number(id), estado: estado })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message||'Error');
        await loadPending();
    } catch (err) { console.error(err); alert('No se pudo actualizar la cita'); }
}

async function selectPatient(ci) {
    document.getElementById('historyTitle').textContent = 'Historial Clínico: ' + ci;
    document.getElementById('paciente_ci').value = ci;
    document.getElementById('historyForm').style.display = '';
    try {
        const res = await fetch('/clinica-imagen/api/ajax/get_user_history.php?ci=' + encodeURIComponent(ci));
        const data = await res.json();
        if (!data.success) throw new Error(data.message||'Error');
        const container = document.getElementById('clinicalHistory');
        if (data.data.length === 0) container.innerHTML = '<p>No hay historial.</p>';
        else container.innerHTML = data.data.map(h => `
            <div class="history-entry">
                <h5>${h.creado_en} — ${h.tipo}</h5>
                <p><em>Nota del ${h.tecnico}:</em> ${h.nota}</p>
            </div>
        `).join('');
    } catch (err) { console.error(err); }
}

document.addEventListener('DOMContentLoaded', function () {
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
