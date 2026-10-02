(() => {
    const form = document.querySelector('form[data-api]');
    const datalist = document.getElementById('paciente_id_lista');
    const inputBuscar = document.getElementById('paciente_id_buscar');
    const selectCita = document.getElementById('cita_id');
    const inputFecha = document.getElementById('fecha_estudio');
    if (!form || !datalist || !selectCita) {
        return;
    }

    function cargarScript(src) {
        return new Promise((resolve) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            document.body.appendChild(script);
        });
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

        let respuesta;
        try {
            respuesta = await fetch(form.dataset.api);
        } catch {
            return;
        }
        if (respuesta.status === 401 || respuesta.status === 403 || respuesta.redirected) {
            window.location.href = 'login.html';
            return;
        }
        const data = await respuesta.json();

        data.pacientes.forEach((paciente) => {
            const opcion = document.createElement('option');
            opcion.dataset.id = paciente.id;
            opcion.value = `${paciente.apellido}, ${paciente.nombre} (CI ${paciente.ci})`;
            datalist.appendChild(opcion);
        });
        if (!data.pacientes.length && inputBuscar) {
            const mensaje = document.createElement('p');
            mensaje.className = 'campo-error';
            mensaje.textContent = inputBuscar.dataset.mensajeSinPacientes || 'Todavia no hay pacientes registrados.';
            inputBuscar.closest('.campo').appendChild(mensaje);
        }

        window.CITAS_POR_PACIENTE = data.citas_por_paciente;
        window.CITA_ELEGIDA = '';
        window.MENSAJE_SIN_CITAS = selectCita.dataset.mensajeSinCitas || 'Este paciente no tiene citas confirmadas.';

        await cargarScript('assets/js/buscador-combo.js');
        await cargarScript('assets/js/citas-por-paciente.js');
        cargarScript('assets/js/selector-combo.js');
    }

    init();
})();
