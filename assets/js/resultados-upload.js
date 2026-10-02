(() => {
    const form = document.querySelector('form[data-api]');
    const datalist = document.getElementById('paciente_id_lista');
    const inputBuscar = document.getElementById('paciente_id_buscar');
    const selectCita = document.getElementById('cita_id');
    const inputFecha = document.getElementById('fecha_estudio');
    if (!form || !datalist || !selectCita) {
        return;
    }

    function mostrarAviso() {
        const id = new URL(window.location.href).searchParams.get('ok');
        if (!id) {
            return;
        }
        const aviso = document.querySelector('[data-aviso="ok"]');
        if (aviso) {
            aviso.textContent = `Resultado #${id} guardado correctamente.`;
            aviso.hidden = false;
        }
    }

    async function init() {
        mostrarAviso();

        if (inputFecha) {
            inputFecha.max = new Date().toISOString().slice(0, 10);
        }

        const data = await apiJson(form.dataset.api);
        if (!data) {
            return;
        }

        poblarPacientes(datalist, data.pacientes, inputBuscar);

        window.CITAS_POR_PACIENTE = data.citas_por_paciente;
        window.CITA_ELEGIDA = '';
        window.MENSAJE_SIN_CITAS = selectCita.dataset.mensajeSinCitas || 'Este paciente no tiene citas confirmadas.';

        await cargarScripts('assets/js/buscador-combo.js', 'assets/js/citas-por-paciente.js');
        cargarScript('assets/js/selector-combo.js');
    }

    init();
})();
