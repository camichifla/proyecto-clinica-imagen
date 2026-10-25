(() => {
    const form = document.querySelector('form[data-api]');
    const selectSucursal = document.getElementById('sucursal');
    const selectProfesional = document.getElementById('profesional_id');
    const inputFecha = document.getElementById('fecha');
    const misCitas = document.getElementById('mis-citas');
    if (!form || !selectSucursal || !selectProfesional || !inputFecha || !misCitas) {
        return;
    }

    function mostrarAviso() {
        const params = new URL(window.location.href).searchParams;
        if (params.has('ok')) {
            const codigo = params.get('codigo') || '';
            if (/^CI-\d+$/.test(codigo)) {
                document.querySelector('[data-codigo-valor]').textContent = codigo;
                document.querySelector('[data-codigo]').hidden = false;
            }
            document.querySelector('[data-aviso="ok"]').hidden = false;
        } else if (params.has('cancelado')) {
            document.querySelector('[data-aviso="cancelado"]').hidden = false;
        }
    }

    async function cancelarCita(cita, csrf) {
        const body = new URLSearchParams({ csrf, accion: 'cancelar', cita_id: String(cita.id) });
        const data = await apiJson('api/agendar-cita.php', { method: 'POST', body });
        if (!data) {
            return;
        }
        if (data.redirect) {
            window.location.href = data.redirect;
        }
    }

    function renderFilaCita(cita, csrf) {
        const tr = document.createElement('tr');
        tr.appendChild(celda('Código', cita.codigo));
        tr.appendChild(celda('Fecha y hora de la cita', cita.fechaHoraSolicitada));
        tr.appendChild(celda('Sede', cita.sucursalNombre || '-'));
        tr.appendChild(celda('Estudio', cita.estudioLabel));

        const tdEstado = document.createElement('td');
        tdEstado.dataset.label = 'Estado';
        const badge = document.createElement('span');
        badge.className = 'estado-badge estado-' + cita.estado;
        badge.textContent = cita.estadoLabel;
        tdEstado.appendChild(badge);
        tr.appendChild(tdEstado);

        const tdAcciones = document.createElement('td');
        tdAcciones.dataset.label = '';
        // Every listed cita (upcoming, pending or confirmed) can be cancelled: confirmation only, no reason asked.
        // To change the date the patient cancels and books a new cita.
        const btnCancelar = document.createElement('button');
        btnCancelar.type = 'button';
        btnCancelar.className = 'btn-peligro';
        btnCancelar.textContent = 'Cancelar';
        btnCancelar.addEventListener('click', () => {
            if (window.confirm('¿Cancelar la cita ' + cita.codigo + ' del ' + cita.fechaHoraSolicitada + '?')) {
                cancelarCita(cita, csrf);
            }
        });
        tdAcciones.appendChild(btnCancelar);
        tr.appendChild(tdAcciones);
        return tr;
    }

    function renderMisCitas(citas, csrf) {
        if (!citas.length) {
            const p = document.createElement('p');
            p.textContent = 'No tenés citas próximas.';
            misCitas.appendChild(p);
            return;
        }
        misCitas.appendChild(tabla(
            ['Código', 'Fecha y hora de la cita', 'Sede', 'Estudio', 'Estado', ''],
            citas.map((cita) => renderFilaCita(cita, csrf))
        ));
    }

    async function init() {
        mostrarAviso();

        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }

        const data = await apiJson('api/agendar-cita.php');
        if (!data) {
            return;
        }

        inputFecha.min = data.fechaMin;
        inputFecha.max = data.fechaMax;

        poblarSucursales(selectSucursal, data.sucursalesOrden, '');
        poblarEstudios(document.getElementById('estudio'), data.estudios);
        poblarProfesionales(selectProfesional, data.profesionales, '', data.etiquetasEstudio);

        renderMisCitas(data.citas, sesion.csrf);

        window.SUCURSALES = data.sucursales;
        window.HORA_ELEGIDA = '';

        await cargarScripts('assets/js/agendado-cascada.js', 'assets/js/selector-combo.js');
    }

    init();
})();
