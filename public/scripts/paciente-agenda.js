document.addEventListener('DOMContentLoaded', function () {
    const agendaToggle = document.getElementById('agendaToggle');
    const agendaModal = document.getElementById('agendaModal');
    const agendaClose = document.getElementById('agendaClose');
    const agendaCancel = document.getElementById('agendaCancel');
    const agendaForm = document.getElementById('agendaForm');
    let occupiedHours = [];

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

    if (agendaClose) {
        agendaClose.addEventListener('click', function () {
            toggleModal(false);
        });
    }

    agendaCancel.addEventListener('click', function () {
        toggleModal(false);
    });

    // Antes de enviar, validamos que la hora seleccionada no esté ocupada
    agendaForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        const estudio = agendaForm.estudio ? agendaForm.estudio.value.trim() : '';
        const agendaError = document.getElementById('agendaError');
        const agendaSuccess = document.getElementById('agendaSuccess');

        agendaError.textContent = '';
        agendaSuccess.textContent = '';

        if (!estudio) {
            agendaError.textContent = 'Selecciona un estudio.';
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

            agendaSuccess.textContent = 'Gracias por solicitar un estudio con nosotros, a la brevedad se confirmara la solicitud.';
            agendaForm.reset();
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

});

function buildTimeSlots() {
    const slots = [];
    for (let minutes = 8 * 60; minutes <= 19 * 60 + 15; minutes += 45) {
        const hours = String(Math.floor(minutes / 60)).padStart(2, '0');
        const mins = String(minutes % 60).padStart(2, '0');
        slots.push(`${hours}:${mins}`);
    }
    return slots;
}

function renderTimeSlots(containerId, inputId, date, occupied) {
    const container = document.getElementById(containerId);
    const input = document.getElementById(inputId);
    container.innerHTML = buildTimeSlots().map((time) => {
        const isOccupied = occupied.includes(time);
        return `<button type="button" class="time-slot ${isOccupied ? 'occupied' : 'available'}" ${isOccupied ? 'disabled' : ''} data-time="${time}">${time}<span>${isOccupied ? 'Ocupado' : 'Libre'}</span></button>`;
    }).join('');
    container.querySelectorAll('.time-slot.available').forEach((button) => {
        button.addEventListener('click', () => {
            container.querySelectorAll('.time-slot.selected').forEach((selected) => selected.classList.remove('selected'));
            button.classList.add('selected');
            input.value = `${date}T${button.dataset.time}`;
        });
    });
}