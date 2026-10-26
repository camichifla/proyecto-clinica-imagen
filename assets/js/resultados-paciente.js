(() => {
    const contenedor = document.getElementById('resultados-busqueda');
    const form = document.querySelector('.form-buscar-paciente');
    const inputBuscar = document.getElementById('paciente_id_buscar');
    const inputHidden = document.getElementById('paciente_id');
    const datalist = document.getElementById('paciente_id_lista');
    if (!contenedor || !form) {
        return;
    }

    const MENSAJE_VACIO = 'No hay resultados cargados para este paciente.';

    function renderCitas(destino, citas) {
        const h3 = document.createElement('h3');
        h3.textContent = 'Historial de citas';
        destino.appendChild(h3);

        if (!citas.length) {
            destino.appendChild(parrafo('No hay citas con este paciente.'));
            return;
        }

        const filas = citas.map((cita) => {
            const tr = document.createElement('tr');
            tr.appendChild(celda('Fecha y hora solicitada', fechaHora(cita.fecha_hora_solicitada)));
            tr.appendChild(celda('Sede', cita.sucursal_nombre || '-'));
            tr.appendChild(celda('Estudio', cita.estudio_label));

            const tdEstado = document.createElement('td');
            tdEstado.dataset.label = 'Estado';
            const span = document.createElement('span');
            span.className = 'estado-badge estado-' + cita.estado;
            span.textContent = cita.estado_label;
            tdEstado.appendChild(span);
            tr.appendChild(tdEstado);

            tr.appendChild(celda('Confirmación', fechaHora(cita.fecha_hora_confirmada) || '-'));
            tr.appendChild(celda('Notas', cita.notas_admin || '-'));
            return tr;
        });
        destino.appendChild(tabla(['Fecha y hora solicitada', 'Sede', 'Estudio', 'Estado', 'Confirmación', 'Notas'], filas));
    }

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
        label.textContent = 'Nueva nota';
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
            error.hidden = true;
            boton.disabled = true;
            const data = await enviarPost('api/notas-clinicas.php', { paciente_id: pacienteId, texto: area.value });
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
            destino.appendChild(parrafo('No hay notas para este paciente.'));
            return;
        }
        const lista = document.createElement('div');
        lista.className = 'lista-notas';
        notas.forEach((nota) => {
            const item = document.createElement('article');
            item.className = 'nota-clinica';
            const meta = document.createElement('p');
            meta.className = 'nota-clinica-meta';
            meta.textContent = `${fechaHora(nota.creado_en)} - ${nota.autor} (${ROLES_ETIQUETA[nota.rol] || nota.rol})`;
            const texto = document.createElement('p');
            texto.className = 'nota-clinica-texto';
            texto.textContent = nota.texto;
            item.append(meta, texto);
            // Only the author can delete their own note (hard delete, with confirmation).
            if (nota.propia) {
                const borrar = document.createElement('button');
                borrar.type = 'button';
                borrar.className = 'btn-peligro-suave nota-clinica-borrar';
                borrar.textContent = 'Borrar';
                borrar.addEventListener('click', async () => {
                    if (!window.confirm('¿Borrar esta nota? No se puede deshacer.')) {
                        return;
                    }
                    borrar.disabled = true;
                    const data = await enviarPost('api/notas-clinicas.php', { accion: 'borrar', nota_id: String(nota.id) });
                    if (data && data.ok) {
                        buscarResultados(pacienteId);
                    } else {
                        borrar.disabled = false;
                        window.alert((data && data.error) || 'No se pudo borrar la nota.');
                    }
                });
                item.append(borrar);
            }
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
            p.setAttribute('role', 'alert');
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

    // The role changes the empty-list message; the patient list and the visible data are decided by the API.
    const TEXTOS_ROL = {
        profesional: { sinPacientes: 'No hay pacientes con citas asignadas.' },
    };

    async function init() {
        const sesion = await window.sesionLista;
        const textos = (sesion && TEXTOS_ROL[sesion.rol]) || null;
        if (textos) {
            inputBuscar.dataset.mensajeSinPacientes = textos.sinPacientes;
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
