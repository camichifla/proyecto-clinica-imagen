document.addEventListener('DOMContentLoaded', function () {
    const agendaToggle = document.getElementById('agendaToggle');
    const agendaModal = document.getElementById('agendaModal');
    const agendaClose = document.getElementById('agendaClose');
    const agendaCancel = document.getElementById('agendaCancel');
    const agendaForm = document.getElementById('agendaForm');
    let occupiedHours = []; // lista de HH:MM ocupadas para la sucursal+fecha seleccionada

    document.querySelectorAll('.btn-cancelar-cita').forEach(function (button) {
        button.addEventListener('click', async function () {
            const citaId = this.dataset.citaId;
            if (!citaId) {
                return;
            }

            const confirmar = window.confirm('¿Deseas cancelar esta cita?');
            if (!confirmar) {
                return;
            }

            const textoOriginal = this.textContent;
            this.disabled = true;
            this.textContent = 'Cancelando...';

            try {
                const response = await fetch('cancelar_cita.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ cita_id: Number(citaId) }),
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'No se pudo cancelar la cita.');
                }

                window.location.reload();
            } catch (error) {
                this.disabled = false;
                this.textContent = textoOriginal;
                window.alert(error.message || 'No se pudo cancelar la cita.');
            }
        });
    });

    const toggleModal = (visible) => {
        agendaModal.classList.toggle('hidden', !visible);
        if (visible) {
            document.body.style.overflow = 'hidden';
            agendaModal.querySelector('select, input').focus();
        } else {
            document.body.style.overflow = '';
        }
    };

    // Solicita al servidor las horas ocupadas para la sucursal y fecha dada (YYYY-MM-DD)
    async function fetchOccupiedHours(sucursal, date) {
        occupiedHours = [];
        if (!sucursal || !date) return;
        try {
            const url = `/clinica-imagen/api/ajax/get_occupied_slots.php?sucursal=${encodeURIComponent(sucursal)}&date=${encodeURIComponent(date)}`;
            const res = await fetch(url);
            const json = await res.json();
            if (json.success && Array.isArray(json.occupied)) occupiedHours = json.occupied;
        } catch (err) {
            console.error('No se pudieron cargar horas ocupadas', err);
            occupiedHours = [];
        }
    }

    // Marca en rojo el input de fecha/hora si la hora elegida ya está ocupada
    function markOccupiedState(fechaHoraInput, agendaError) {
        if (!fechaHoraInput.value) {
            fechaHoraInput.classList.remove('input-ocupado');
            return false;
        }

        const horaHHMM = fechaHoraInput.value.split('T')[1]
            ? fechaHoraInput.value.split('T')[1].slice(0, 5)
            : '';

        const ocupado = occupiedHours.includes(horaHHMM);

        fechaHoraInput.classList.toggle('input-ocupado', ocupado);

        if (agendaError) {
            agendaError.textContent = ocupado
                ? 'La hora seleccionada ya está ocupada en esa sucursal. Por favor elige otra.'
                : '';
        }

        return ocupado;
    }

    agendaToggle.addEventListener('click', function () {
        toggleModal(true);
    });

    agendaClose.addEventListener('click', function () {
        toggleModal(false);
    });

    agendaCancel.addEventListener('click', function () {
        toggleModal(false);
    });

    // Antes de enviar, validamos que la hora seleccionada no esté ocupada
    agendaForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        const estudio = agendaForm.estudio.value.trim();
        const medico = agendaForm.medico.value.trim();
        const sucursal = agendaForm.sucursal.value.trim();
        const fechaHora = agendaForm.fecha_hora.value.trim();
        const agendaError = document.getElementById('agendaError');
        const fechaHoraInput = document.getElementById('selectFechaHora') || agendaForm.fecha_hora;

        agendaError.textContent = '';

        if (!estudio || !medico || !sucursal || !fechaHora) {
            agendaError.textContent = 'Todos los campos son obligatorios.';
            return;
        }

        const fechaSeleccionada = new Date(fechaHora);
        if (Number.isNaN(fechaSeleccionada.getTime())) {
            agendaError.textContent = 'La fecha y hora no son válidas.';
            return;
        }

        if (fechaSeleccionada <= new Date()) {
            agendaError.textContent = 'La fecha y hora deben ser posteriores al momento actual.';
            return;
        }

        // Extraemos fecha y hora en formato YYYY-MM-DD y HH:MM
        const fechaISO = fechaHora.split('T')[0];

        // Si no cargamos las horas ocupadas aún, pedimos al servidor
        await fetchOccupiedHours(sucursal, fechaISO);

        if (markOccupiedState(fechaHoraInput, agendaError)) {
            return;
        }

        const formData = new FormData(agendaForm);

        try {
            const response = await fetch('/clinica-imagen/api/guardar_agenda.php', {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                agendaError.textContent = data.message || 'No se pudo guardar la cita. Intenta nuevamente.';
                return;
            }

            toggleModal(false);
            window.location.reload();
        } catch (error) {
            agendaError.textContent = 'Error de conexión. Intenta nuevamente.';
            console.error(error);
        }
    });

    agendaModal.addEventListener('click', function (event) {
        if (event.target === agendaModal) {
            toggleModal(false);
        }
    });

    // Re-fetch occupied hours cuando cambie sucursal o fecha (si se usa date/datetime-local)
    const sucursalInput = document.getElementById('selectSucursal');
    const fechaInput = document.getElementById('selectFechaHora');
    const agendaErrorEl = document.getElementById('agendaError');

    if (sucursalInput && fechaInput) {
        sucursalInput.addEventListener('change', async () => {
            const fechaISO = (fechaInput.value || '').split('T')[0];
            await fetchOccupiedHours(sucursalInput.value, fechaISO);
            markOccupiedState(fechaInput, agendaErrorEl);
        });

        fechaInput.addEventListener('change', async () => {
            const fechaISO = (fechaInput.value || '').split('T')[0];
            await fetchOccupiedHours(sucursalInput.value, fechaISO);
            markOccupiedState(fechaInput, agendaErrorEl);
        });

        // Revisa también mientras el usuario ajusta la hora manualmente (sin disparar 'change')
        fechaInput.addEventListener('input', () => {
            markOccupiedState(fechaInput, agendaErrorEl);
        });
    }
});