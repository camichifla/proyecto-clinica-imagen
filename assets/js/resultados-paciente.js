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

    function celda(etiqueta, texto) {
        const td = document.createElement('td');
        td.dataset.label = etiqueta;
        td.textContent = texto;
        return td;
    }

    function crearTarjetaResultado(resultado) {
        const articulo = document.createElement('article');
        articulo.className = 'tarjeta-resultado';

        const header = document.createElement('div');
        header.className = 'tarjeta-resultado-header';
        const h2 = document.createElement('h2');
        h2.textContent = resultado.nombre_estudio;
        const fecha = document.createElement('span');
        fecha.className = 'tarjeta-resultado-fecha';
        fecha.textContent = resultado.fecha_estudio;
        header.append(h2, fecha);
        articulo.appendChild(header);

        if (resultado.observaciones) {
            const p = document.createElement('p');
            p.className = 'tarjeta-resultado-observaciones';
            resultado.observaciones.split('\n').forEach((linea, i) => {
                if (i > 0) {
                    p.appendChild(document.createElement('br'));
                }
                p.appendChild(document.createTextNode(linea));
            });
            articulo.appendChild(p);
        }

        if (resultado.imagenes.length) {
            const divImagenes = document.createElement('div');
            divImagenes.className = 'tarjeta-resultado-imagenes';
            resultado.imagenes.forEach((imagen) => {
                const img = document.createElement('img');
                img.src = imagen.url;
                img.alt = imagen.nombre_original;
                img.loading = 'lazy';
                divImagenes.appendChild(img);
            });
            articulo.appendChild(divImagenes);
        }

        return articulo;
    }

    function renderListaResultados(destino, resultados) {
        if (!resultados.length) {
            const p = document.createElement('p');
            p.textContent = MENSAJE_VACIO;
            destino.appendChild(p);
            return;
        }
        const lista = document.createElement('div');
        lista.className = 'lista-resultados';
        resultados.forEach((resultado) => lista.appendChild(crearTarjetaResultado(resultado)));
        destino.appendChild(lista);
    }

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

        const scroll = document.createElement('div');
        scroll.className = 'tabla-scroll';
        const tabla = document.createElement('table');
        tabla.className = 'tabla-datos';

        const thead = document.createElement('thead');
        const trHead = document.createElement('tr');
        ['Fecha y hora solicitada', 'Sede', 'Estudio', 'Estado', 'Confirmacion', 'Notas'].forEach((texto) => {
            const th = document.createElement('th');
            th.scope = 'col';
            th.textContent = texto;
            trHead.appendChild(th);
        });
        thead.appendChild(trHead);
        tabla.appendChild(thead);

        const tbody = document.createElement('tbody');
        citas.forEach((cita) => {
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
            tbody.appendChild(tr);
        });
        tabla.appendChild(tbody);
        scroll.appendChild(tabla);
        destino.appendChild(scroll);
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

        renderListaResultados(section, data.resultados);
        contenedor.appendChild(section);
    }

    async function buscarResultados(pacienteId) {
        let respuesta;
        try {
            respuesta = await fetch(`api/resultados-paciente.php?paciente_id=${encodeURIComponent(pacienteId)}`);
        } catch {
            return;
        }
        if (respuesta.status === 401 || respuesta.status === 403) {
            window.location.href = 'login.html';
            return;
        }
        const data = await respuesta.json();
        renderResultadosBusqueda(data, respuesta.ok);
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

    function cargarBuscadorCombo() {
        const script = document.createElement('script');
        script.src = 'assets/js/buscador-combo.js';
        document.body.appendChild(script);
    }

    async function init() {
        let respuesta;
        try {
            respuesta = await fetch('api/pacientes.php');
        } catch {
            cargarBuscadorCombo();
            return;
        }
        if (respuesta.status === 401 || respuesta.status === 403) {
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

        if (!data.pacientes.length) {
            const mensaje = document.createElement('p');
            mensaje.className = 'campo-error';
            mensaje.textContent = inputBuscar.dataset.mensajeSinPacientes || 'Todavia no hay pacientes registrados.';
            inputBuscar.closest('.campo').appendChild(mensaje);
        }

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

        cargarBuscadorCombo();

        if (inputHidden.value) {
            buscarResultados(inputHidden.value);
        }
    }

    init();
})();
