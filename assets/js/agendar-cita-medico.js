(() => {
    const form = document.querySelector('form[data-api]');
    const selectSucursal = document.getElementById('sucursal');
    const selectProfesional = document.getElementById('profesional_id');
    const contenedorFecha = document.querySelector('.selector-fecha');
    if (!form || !selectSucursal || !selectProfesional || !contenedorFecha) {
        return;
    }

    function mostrarAviso() {
        const params = new URL(window.location.href).searchParams;
        if (params.get('ok') === 'pendiente') {
            document.querySelector('[data-aviso="ok-pendiente"]').hidden = false;
        } else if (params.has('ok')) {
            document.querySelector('[data-aviso="ok"]').hidden = false;
        }
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
            panelEstudio.hidden = estudioSelect.value === '';
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
        mostrarAviso();

        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }

        const data = await apiJson('api/agendar-cita-medico.php');
        if (!data) {
            return;
        }

        contenedorFecha.dataset.min = data.fechaMin;
        contenedorFecha.dataset.max = data.fechaMax;

        poblarSucursales(selectSucursal, data.sucursalesOrden);
        poblarProfesionales(selectProfesional, data.profesionales);
        poblarPacientesConectados(data.pacientesConectados);
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
