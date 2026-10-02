(() => {
    const form = document.querySelector('form[data-api]');
    const selectSucursal = document.getElementById('sucursal');
    const selectProfesional = document.getElementById('profesional_id');
    const contenedorFecha = document.querySelector('.selector-fecha');
    if (!form || !selectSucursal || !selectProfesional || !contenedorFecha) {
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
        const params = new URL(window.location.href).searchParams;
        if (params.get('ok') === 'pendiente') {
            document.querySelector('[data-aviso="ok-pendiente"]').hidden = false;
        } else if (params.has('ok')) {
            document.querySelector('[data-aviso="ok"]').hidden = false;
        }
    }

    function poblarSucursales(sucursalesOrden) {
        sucursalesOrden.forEach((sucursal) => {
            const opcion = document.createElement('option');
            opcion.value = String(sucursal.id);
            opcion.textContent = sucursal.direccion ? sucursal.nombre + ' — ' + sucursal.direccion : sucursal.nombre;
            selectSucursal.appendChild(opcion);
        });
    }

    const ETIQUETAS_ESTUDIO = { placa: 'Placa', radiografia: 'Radiografia' };

    function poblarProfesionales(profesionales) {
        profesionales.forEach((profesional) => {
            const opcion = document.createElement('option');
            opcion.value = String(profesional.id);
            opcion.dataset.especializaciones = JSON.stringify(profesional.especializaciones);
            const etiquetas = profesional.especializaciones.map((e) => ETIQUETAS_ESTUDIO[e] || e).join(', ');
            opcion.textContent = profesional.apellido + ', ' + profesional.nombre + ' — ' + etiquetas;
            selectProfesional.appendChild(opcion);
        });
        if (!profesionales.length) {
            document.querySelector('[data-mensaje-sin-profesionales]').hidden = false;
        }
    }

    function poblarPacientesConectados(pacientes) {
        const datalist = document.getElementById('paciente_id_lista');
        pacientes.forEach((paciente) => {
            const opcion = document.createElement('option');
            opcion.dataset.id = paciente.id;
            opcion.value = `${paciente.apellido}, ${paciente.nombre} (CI ${paciente.ci})`;
            datalist.appendChild(opcion);
        });
        if (!pacientes.length) {
            document.querySelector('[data-mensaje-sin-pacientes]').hidden = false;
        }
    }

    const DIENTES_COMPLETO = [
        '55', '54', '53', '52', '51', '61', '62', '63', '64', '65',
        '18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28',
        '48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38',
        '85', '84', '83', '82', '81', '71', '72', '73', '74', '75',
    ];
    const DIENTES_FILAS_LARGOS = [10, 16, 16, 10];

    function renderGridDientes(contenedor, name) {
        if (!contenedor) {
            return;
        }
        let offset = 0;
        DIENTES_FILAS_LARGOS.forEach((largo) => {
            const fila = document.createElement('div');
            fila.className = 'teeth-row';
            DIENTES_COMPLETO.slice(offset, offset + largo).forEach((diente) => {
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
        const tipoOrdenOculto = document.getElementById('tipo_orden');
        const panelEstudio = document.getElementById('orden-panel-estudio');
        if (!estudioSelect || !tipoOrdenOculto || !panelEstudio) {
            return;
        }
        function aplicar() {
            const hayEstudio = estudioSelect.value !== '';
            tipoOrdenOculto.value = hayEstudio ? 'estudio' : '';
            panelEstudio.hidden = !hayEstudio;
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

        let respuesta;
        try {
            respuesta = await fetch('api/agendar-cita-medico.php');
        } catch {
            return;
        }
        if (respuesta.status === 401 || respuesta.status === 403 || respuesta.redirected) {
            window.location.href = 'login.html';
            return;
        }
        const data = await respuesta.json();

        contenedorFecha.dataset.min = data.fechaMin;
        contenedorFecha.dataset.max = data.fechaMax;

        poblarSucursales(data.sucursalesOrden);
        poblarProfesionales(data.profesionales);
        poblarPacientesConectados(data.pacientesConectados);
        renderGridDientes(document.getElementById('teeth-radio-intra'), 'radio_intra');
        renderGridDientes(document.getElementById('teeth-tomo-cone-beam'), 'tomo_cone_beam');

        initToggleModoPaciente();
        initToggleTipoOrden();
        initAutoTildado();

        window.SUCURSALES = data.sucursales;
        window.HORA_ELEGIDA = '';

        await cargarScript('assets/js/agendado-cascada.js');
        await cargarScript('assets/js/selector-combo.js');
        await cargarScript('assets/js/buscador-combo.js');
    }

    init();
})();
