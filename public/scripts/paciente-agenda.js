document.addEventListener('DOMContentLoaded', function () {
    const agendaToggle = document.getElementById('agendaToggle');
    const agendaModal = document.getElementById('agendaModal');
    const agendaClose = document.getElementById('agendaClose');
    const agendaCancel = document.getElementById('agendaCancel');
    const agendaForm = document.getElementById('agendaForm');

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

    agendaToggle.addEventListener('click', function () {
        toggleModal(true);
    });

    agendaClose.addEventListener('click', function () {
        toggleModal(false);
    });

    agendaCancel.addEventListener('click', function () {
        toggleModal(false);
    });

    agendaForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        const estudio = agendaForm.estudio.value.trim();
        const medico = agendaForm.medico.value.trim();
        const sucursal = agendaForm.sucursal.value.trim();
        const fechaHora = agendaForm.fecha_hora.value.trim();
        const agendaError = document.getElementById('agendaError');

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

        const formData = new FormData(agendaForm);

        try {
            const response = await fetch('guardar_agenda.php', {
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
});