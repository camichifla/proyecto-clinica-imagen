(() => {
    const form = document.querySelector('form[data-api]');
    const datalist = document.getElementById('paciente_id_lista');
    const inputBuscar = document.getElementById('paciente_id_buscar');
    const selectCita = document.getElementById('cita_id');
    const inputFecha = document.getElementById('fecha_estudio');
    if (!form || !datalist || !selectCita) {
        return;
    }

    function avisoDeUrl() {
        const id = new URL(window.location.href).searchParams.get('ok');
        if (id) {
            mostrarAviso('ok', `Resultado #${id} enviado correctamente.`);
        }
    }

    // The role changes the empty-list messages; permissions are decided by the API.
    const TEXTOS_PROFESIONAL = {
        sinPacientes: 'No hay pacientes con citas asignadas.',
        sinCitas: 'Este paciente no tiene citas confirmadas con vos',
    };

    async function init() {
        avisoDeUrl();

        const sesion = await window.sesionLista;
        if (sesion && sesion.rol === 'profesional') {
            inputBuscar.dataset.mensajeSinPacientes = TEXTOS_PROFESIONAL.sinPacientes;
            selectCita.dataset.mensajeSinCitas = TEXTOS_PROFESIONAL.sinCitas;
        }

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
