(() => {
    const contenedor = document.getElementById('resultados-busqueda');
    const form = document.querySelector('.form-buscar-paciente');
    const inputBuscar = document.getElementById('paciente_id_buscar');
    const inputHidden = document.getElementById('paciente_id');
    const datalist = document.getElementById('paciente_id_lista');
    const boton = document.getElementById('btn-buscar');
    if (!contenedor || !form) {
        return;
    }

    const MENSAJE_VACIO = 'Este paciente todavia no tiene resultados cargados.';

    function renderCitas(destino, citas) {
        const h3 = document.createElement('h3');
        h3.textContent = 'Historial de citas';
        destino.appendChild(h3);

        if (!citas.length) {
            const p = document.createElement('p');
            p.textContent = 'No hay citas registradas con este paciente.';
            destino.appendChild(p);
            return;
        }

        const filas = citas.map((cita) => {
            const tr = document.createElement('tr');
            tr.appendChild(celda('Fecha y hora solicitada', cita.fecha_hora_solicitada));
            tr.appendChild(celda('Sede', cita.sucursal_nombre || '-'));
            tr.appendChild(celda('Estudio', cita.estudio_label));

            const tdEstado = document.createElement('td');
            tdEstado.dataset.label = 'Estado';
            const span = document.createElement('span');
            span.className = 'estado-badge estado-' + cita.estado;
            span.textContent = cita.estado_label;
            tdEstado.appendChild(span);
            tr.appendChild(tdEstado);

            tr.appendChild(celda('Confirmacion', cita.fecha_hora_confirmada || '-'));
            tr.appendChild(celda('Notas', cita.notas_admin || '-'));
            return tr;
        });
        destino.appendChild(tabla(['Fecha y hora solicitada', 'Sede', 'Estudio', 'Estado', 'Confirmacion', 'Notas'], filas));
    }

    function renderResultadosBusqueda(data, encontrado) {
        contenedor.innerHTML = '';

        if (!encontrado) {
            const section = document.createElement('section');
            section.className = 'seccion-panel';
            const p = document.createElement('p');
            p.className = 'aviso-error';
            p.textContent = 'Paciente no encontrado.';
            section.appendChild(p);
            contenedor.appendChild(section);
            return;
        }

        const section = document.createElement('section');
        section.className = 'seccion-panel';
        const h2 = document.createElement('h2');
        h2.textContent = `${data.paciente.apellido}, ${data.paciente.nombre}`;
        section.appendChild(h2);

        if (data.citas) {
            renderCitas(section, data.citas);
            const h3 = document.createElement('h3');
            h3.textContent = 'Resultados';
            section.appendChild(h3);
        }

        renderListaResultados(section, data.resultados, MENSAJE_VACIO);
        contenedor.appendChild(section);
    }

    async function buscarResultados(pacienteId) {
        const data = await apiJson(`api/resultados-paciente.php?paciente_id=${encodeURIComponent(pacienteId)}`);
        if (!data) {
            return;
        }
        renderResultadosBusqueda(data, !!data.paciente);
    }

    function actualizarUrl(pacienteId) {
        const url = new URL(window.location.href);
        url.searchParams.set('paciente_id', pacienteId);
        history.pushState(null, '', url);
    }

    form.addEventListener('submit', (evento) => {
        evento.preventDefault();
        const pacienteId = inputHidden.value;
        if (!pacienteId) {
            return;
        }
        actualizarUrl(pacienteId);
        buscarResultados(pacienteId);
    });

    window.addEventListener('popstate', () => {
        const pacienteId = new URL(window.location.href).searchParams.get('paciente_id');
        if (pacienteId) {
            buscarResultados(pacienteId);
        } else {
            contenedor.innerHTML = '';
        }
    });

    async function init() {
        const data = await apiJson('api/pacientes.php');
        if (!data) {
            cargarScript('assets/js/buscador-combo.js');
            return;
        }

        poblarPacientes(datalist, data.pacientes, inputBuscar);

        const pacienteIdUrl = new URL(window.location.href).searchParams.get('paciente_id');
        if (pacienteIdUrl) {
            const opcionElegida = Array.from(datalist.querySelectorAll('option'))
                .find((opcion) => opcion.dataset.id === pacienteIdUrl);
            if (opcionElegida) {
                inputBuscar.value = opcionElegida.value;
                inputHidden.value = pacienteIdUrl;
                boton.disabled = false;
            }
        }

        cargarScript('assets/js/buscador-combo.js');

        if (inputHidden.value) {
            buscarResultados(inputHidden.value);
        }
    }

    init();
})();
