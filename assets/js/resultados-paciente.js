(() => {
    const contenedor = document.getElementById('resultados-busqueda');
    const form = document.querySelector('.form-buscar-paciente');
    const inputBuscar = document.getElementById('paciente_id_buscar');
    const inputHidden = document.getElementById('paciente_id');
    const datalist = document.getElementById('paciente_id_lista');
    if (!contenedor || !form) {
        return;
    }

    const MENSAJE_VACIO = 'Este paciente todavía no tiene resultados cargados.';

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

            tr.appendChild(celda('Confirmación', cita.fecha_hora_confirmada || '-'));
            tr.appendChild(celda('Notas', cita.notas_admin || '-'));
            return tr;
        });
        destino.appendChild(tabla(['Fecha y hora solicitada', 'Sede', 'Estudio', 'Estado', 'Confirmación', 'Notas'], filas));
    }

    const ROLES = { medico: 'Médico', profesional: 'Profesional' };

    function renderNotas(destino, notas, pacienteId) {
        const h3 = document.createElement('h3');
        h3.textContent = 'Historia clínica';
        destino.appendChild(h3);

        const form = document.createElement('form');
        form.className = 'form-nota';
        const campo = document.createElement('div');
        campo.className = 'campo';
        const label = document.createElement('label');
        label.htmlFor = 'nota-texto';
        label.textContent = 'Nueva nota (no se puede editar ni borrar)';
        const area = document.createElement('textarea');
        area.id = 'nota-texto';
        area.name = 'texto';
        area.rows = 3;
        area.maxLength = 2000;
        campo.append(label, area);
        const error = document.createElement('p');
        error.className = 'campo-error';
        error.hidden = true;
        const boton = document.createElement('button');
        boton.type = 'submit';
        boton.className = 'btn-primary';
        boton.textContent = 'Agregar nota';
        form.append(campo, error, boton);
        form.addEventListener('submit', async (evento) => {
            evento.preventDefault();
            const sesion = await window.sesionLista;
            error.hidden = true;
            boton.disabled = true;
            const data = await apiJson('api/notas-clinicas.php', {
                method: 'POST',
                body: new URLSearchParams({ csrf: sesion.csrf, paciente_id: pacienteId, texto: area.value }),
            });
            boton.disabled = false;
            if (data && data.ok) {
                buscarResultados(pacienteId);
                return;
            }
            error.textContent = (data && ((data.errores && data.errores.texto) || data.error)) || 'No se pudo guardar la nota.';
            error.hidden = false;
        });
        destino.appendChild(form);

        if (!notas.length) {
            const p = document.createElement('p');
            p.textContent = 'Todavía no hay notas para este paciente.';
            destino.appendChild(p);
            return;
        }
        const lista = document.createElement('div');
        lista.className = 'lista-notas';
        notas.forEach((nota) => {
            const item = document.createElement('article');
            item.className = 'nota-clinica';
            const meta = document.createElement('p');
            meta.className = 'nota-clinica-meta';
            meta.textContent = `${nota.creado_en} - ${nota.autor} (${ROLES[nota.rol] || nota.rol})`;
            const texto = document.createElement('p');
            texto.className = 'nota-clinica-texto';
            texto.textContent = nota.texto;
            item.append(meta, texto);
            lista.appendChild(item);
        });
        destino.appendChild(lista);
    }

    function renderResultadosBusqueda(data, encontrado) {
        contenedor.innerHTML = '';

        // Details open inside the search box, under a divider (same as the agenda's day details).
        if (!encontrado) {
            const section = document.createElement('div');
            section.className = 'detalle-expandido';
            const p = document.createElement('p');
            p.className = 'aviso-error';
            p.textContent = 'Paciente no encontrado.';
            section.appendChild(p);
            contenedor.appendChild(section);
            return;
        }

        const section = document.createElement('div');
        section.className = 'detalle-expandido';
        const h2 = document.createElement('h2');
        h2.textContent = `${data.paciente.apellido}, ${data.paciente.nombre}`;
        section.appendChild(h2);

        if (data.citas) {
            renderCitas(section, data.citas);
        }
        if (data.notas) {
            renderNotas(section, data.notas, data.paciente.id);
        }
        if (data.citas || data.notas) {
            const h3 = document.createElement('h3');
            h3.textContent = 'Resultados';
            section.appendChild(h3);
        }

        renderListaResultados(section, data.resultados, MENSAJE_VACIO, true);
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
    filtrarAlCambiar(form);

    window.addEventListener('popstate', () => {
        const pacienteId = new URL(window.location.href).searchParams.get('paciente_id');
        if (pacienteId) {
            buscarResultados(pacienteId);
        } else {
            contenedor.innerHTML = '';
        }
    });

    // El rol cambia el titulo y el mensaje de lista vacia; la lista de pacientes
    // y los datos que se ven los decide la API.
    const TEXTOS_ROL = {
        administrador: { titulo: 'Buscar Resultados' },
        profesional: { titulo: 'Historial de Pacientes', sinPacientes: 'Todavía no tenés pacientes con citas asignadas.' },
    };

    async function init() {
        const sesion = await window.sesionLista;
        const textos = (sesion && TEXTOS_ROL[sesion.rol]) || null;
        if (textos) {
            document.getElementById('titulo-vista').textContent = textos.titulo;
            document.title = textos.titulo + ' - Clínica Imagen';
            if (textos.sinPacientes) {
                inputBuscar.dataset.mensajeSinPacientes = textos.sinPacientes;
            }
        }

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
            }
        }

        cargarScript('assets/js/buscador-combo.js');

        if (inputHidden.value) {
            buscarResultados(inputHidden.value);
        }
    }

    init();
})();
