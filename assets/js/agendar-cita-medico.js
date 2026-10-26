(() => {
    const form = document.querySelector('form[data-api]');
    const selectSucursal = document.getElementById('sucursal');
    const selectProfesional = document.getElementById('profesional_id');
    const inputFecha = document.getElementById('fecha');
    if (!form || !selectSucursal || !selectProfesional || !inputFecha) {
        return;
    }

    function avisoDeUrl() {
        const params = new URL(window.location.href).searchParams;
        if (params.get('ok') === 'pendiente') {
            mostrarAviso('ok-pendiente');
        } else if (params.has('ok')) {
            mostrarAviso('ok');
        }
    }

    // Read-only: the cita belongs to the patient; cancelling goes through reception, with a reason.
    function renderFilaCita(cita) {
        const tr = document.createElement('tr');
        tr.appendChild(celda('Código', cita.codigo));
        tr.appendChild(celda('Fecha y hora de la cita', fechaHora(cita.fechaHoraSolicitada)));
        tr.appendChild(celda('Paciente', cita.pacienteLabel));
        tr.appendChild(celda('Estudio', cita.estudioLabel));
        tr.appendChild(celda('Sede', cita.sucursalNombre || '-'));
        tr.appendChild(celdaEstado(cita.estado, cita.estadoLabel));
        tr.appendChild(celda('Motivo', cita.motivo || '-'));
        return tr;
    }

    function renderMisCitas(citas) {
        const contenedor = document.getElementById('mis-citas');
        if (!citas.length) {
            contenedor.appendChild(parrafo('No hay citas agendadas.'));
            return;
        }
        contenedor.appendChild(tabla(
            ['Código', 'Fecha y hora de la cita', 'Paciente', 'Estudio', 'Sede', 'Estado', 'Motivo'],
            citas.map(renderFilaCita)
        ));
    }

    function poblarPacientesConectados(pacientes) {
        poblarPacientes(document.getElementById('paciente_id_lista'), pacientes);
        if (!pacientes.length) {
            document.querySelector('[data-mensaje-sin-pacientes]').hidden = false;
        }
    }

    const DIENTES_FILAS_LARGOS = [10, 16, 16, 10];

    function renderGridDientes(contenedor, name, dientes) {
        if (!contenedor) {
            return;
        }
        let offset = 0;
        DIENTES_FILAS_LARGOS.forEach((largo) => {
            const fila = document.createElement('div');
            fila.className = 'teeth-row';
            dientes.slice(offset, offset + largo).forEach((diente) => {
                const label = document.createElement('label');
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.name = name + '[]';
                input.value = diente;
                label.appendChild(input);
                label.append(diente);
                fila.appendChild(label);
            });
            contenedor.appendChild(fila);
            offset += largo;
        });
    }

    function initToggleModoPaciente() {
        const radios = document.querySelectorAll('input[name="modo_paciente"]');
        const panelExistente = document.getElementById('panel-paciente-existente');
        const panelNuevo = document.getElementById('panel-paciente-nuevo');
        if (!radios.length || !panelExistente || !panelNuevo) {
            return;
        }
        function aplicar() {
            const seleccionado = document.querySelector('input[name="modo_paciente"]:checked');
            const esNuevo = !!seleccionado && seleccionado.value === 'nuevo';
            panelNuevo.hidden = !esNuevo;
            panelExistente.hidden = esNuevo;
        }
        radios.forEach((radio) => radio.addEventListener('change', aplicar));
        aplicar();
    }

    function initToggleTipoOrden() {
        const estudioSelect = document.getElementById('estudio');
        const panelEstudio = document.getElementById('orden-panel-estudio');
        if (!estudioSelect || !panelEstudio) {
            return;
        }
        function aplicar() {
            const est = estudioSelect.value;
            panelEstudio.hidden = est === '';
            // Show only the cards and subsections that serve the chosen study (data-estudios); AVISO has none, so it always shows.
            panelEstudio.querySelectorAll('[data-estudios]').forEach((el) => {
                el.hidden = est === '' || !el.dataset.estudios.split(/\s+/).filter(Boolean).includes(est);
            });
        }
        estudioSelect.addEventListener('change', aplicar);
        aplicar();
    }

    function initAutoTildado() {
        document.querySelectorAll('.orden-grupo').forEach((grupo) => {
            const padre = grupo.querySelector('.orden-padre');
            const hijos = Array.from(grupo.querySelectorAll('input[type="checkbox"]')).filter((input) => input !== padre);
            if (!padre || !hijos.length) {
                return;
            }
            hijos.forEach((hijo) => hijo.addEventListener('change', () => {
                if (hijo.checked) {
                    padre.checked = true;
                }
            }));
        });

        document.querySelectorAll('.orden-textogrupo').forEach((grupo) => {
            const check = grupo.querySelector('.orden-check-texto');
            const texto = grupo.querySelector('input[type="text"]');
            if (!check || !texto) {
                return;
            }
            texto.addEventListener('input', () => {
                if (texto.value.trim() !== '') {
                    check.checked = true;
                }
            });
        });
    }

    async function init() {
        avisoDeUrl();

        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }

        const data = await apiJson('api/agendar-cita-medico.php');
        if (!data) {
            return;
        }

        inputFecha.min = data.fechaMin;
        inputFecha.max = data.fechaMax;

        poblarSucursales(selectSucursal, data.sucursalesOrden);
        poblarEstudios(document.getElementById('estudio'), data.estudios);
        poblarProfesionales(selectProfesional, data.profesionales, '', data.etiquetasEstudio, true);
        poblarPacientesConectados(data.pacientesConectados);
        renderMisCitas(data.citas);
        renderGridDientes(document.getElementById('teeth-radio-intra'), 'radio_intra', data.dientes);
        renderGridDientes(document.getElementById('teeth-tomo-cone-beam'), 'tomo_cone_beam', data.dientes);

        initToggleModoPaciente();
        initToggleTipoOrden();
        initAutoTildado();

        window.SUCURSALES = data.sucursales;
        window.HORA_ELEGIDA = '';

        await cargarScripts('assets/js/agendado-cascada.js', 'assets/js/selector-combo.js', 'assets/js/buscador-combo.js');
    }

    init();
})();
