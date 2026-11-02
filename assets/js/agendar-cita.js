(() => {
    const form = document.querySelector('form[data-api]');
    const selectSucursal = document.getElementById('sucursal');
    const selectProfesional = document.getElementById('profesional_id');
    const inputFecha = document.getElementById('fecha');
    const misCitas = document.getElementById('mis-citas');
    if (!form || !selectSucursal || !selectProfesional || !inputFecha || !misCitas) {
        return;
    }

    function avisoDeUrl() {
        const params = new URL(window.location.href).searchParams;
        if (params.has('ok')) {
            const codigo = params.get('codigo') || '';
            if (/^CI-\d+$/.test(codigo)) {
                document.querySelector('[data-codigo-valor]').textContent = codigo;
                document.querySelector('[data-codigo]').hidden = false;
            }
            mostrarAviso('ok');
        } else if (params.has('cancelado')) {
            mostrarAviso('cancelado');
        }
    }

    async function cancelarCita(cita) {
        const data = await enviarPost('api/agendar-cita.php', { accion: 'cancelar', cita_id: String(cita.id) });
        if (!data) {
            return;
        }
        if (data.redirect) {
            window.location.href = data.redirect;
        } else {
            mostrarAviso('error', textoErrores(data));
        }
    }

    function renderFilaCita(cita) {
        const tr = document.createElement('tr');
        tr.appendChild(celda('Código', cita.codigo));
        tr.appendChild(celda('Fecha y hora de la cita', fechaHora(cita.fechaHoraSolicitada)));
        tr.appendChild(celda('Sede', cita.sucursalNombre || '-'));
        tr.appendChild(celda('Estudio', cita.estudioLabel));

        tr.appendChild(celdaEstado(cita.estado, cita.estadoLabel));
        tr.appendChild(celda('Motivo', cita.motivo || '-'));

        const tdAcciones = document.createElement('td');
        tdAcciones.dataset.label = '';
        if (cita.estado === 'rechazada' || cita.estado === 'cancelada') {
            tr.appendChild(tdAcciones);
            return tr;
        }
        const btnCancelar = document.createElement('button');
        btnCancelar.type = 'button';
        btnCancelar.className = 'btn-peligro';
        btnCancelar.textContent = 'Cancelar';
        btnCancelar.addEventListener('click', async () => {
            if (await confirmar('¿Cancelar la cita ' + cita.codigo + ' del ' + fechaHora(cita.fechaHoraSolicitada) + '?', { peligro: true })) {
                cancelarCita(cita);
            }
        });
        tdAcciones.appendChild(btnCancelar);
        tr.appendChild(tdAcciones);
        return tr;
    }

    function renderMisCitas(citas) {
        if (!citas.length) {
            misCitas.appendChild(vacio('No hay citas próximas ni recientes.'));
            return;
        }
        misCitas.appendChild(tabla(
            ['Código', 'Fecha y hora de la cita', 'Sede', 'Estudio', 'Estado', 'Motivo', ''],
            citas.map((cita) => renderFilaCita(cita))
        ));
    }

    async function init() {
        avisoDeUrl();

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
        poblarProfesionales(selectProfesional, data.profesionales, '', data.etiquetasEstudio, true);

        renderMisCitas(data.citas);

        window.SUCURSALES = data.sucursales;
        window.HORA_ELEGIDA = '';

        await cargarScripts('assets/js/agendado-cascada.js', 'assets/js/selector-combo.js');
    }

    init();
})();
