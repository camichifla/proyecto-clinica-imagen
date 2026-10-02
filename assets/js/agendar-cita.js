(() => {
    const form = document.querySelector('form[data-api]');
    const selectSucursal = document.getElementById('sucursal');
    const selectProfesional = document.getElementById('profesional_id');
    const contenedorFecha = document.querySelector('.selector-fecha');
    const misCitas = document.getElementById('mis-citas');
    if (!form || !selectSucursal || !selectProfesional || !contenedorFecha || !misCitas) {
        return;
    }
    const formAccion = document.getElementById('form-accion');
    const formCitaId = document.getElementById('form-cita-id');
    const btnSubmit = document.getElementById('btn-agendar-submit');

    function mostrarAviso() {
        const params = new URL(window.location.href).searchParams;
        if (params.has('ok')) {
            document.querySelector('[data-aviso="ok"]').hidden = false;
        } else if (params.has('cancelado')) {
            document.querySelector('[data-aviso="cancelado"]').hidden = false;
        } else if (params.has('reprogramado')) {
            document.querySelector('[data-aviso="reprogramado"]').hidden = false;
        } else if (params.get('error') === 'reprogramar') {
            document.querySelector('[data-aviso="error-reprogramar"]').hidden = false;
        }
    }

    function aVisible(iso) {
        const [anio, mes, dia] = iso.split('-');
        return dia + '/' + mes + '/' + anio;
    }

    async function cancelarCita(cita, csrf, notasAdmin) {
        const body = new URLSearchParams({ csrf, accion: 'cancelar', cita_id: String(cita.id), notas_admin: notasAdmin });
        const data = await apiJson('api/agendar-cita.php', { method: 'POST', body });
        if (!data) {
            return;
        }
        if (data.redirect) {
            window.location.href = data.redirect;
        }
    }

    function renderFilaCita(cita, notasAdminMax, csrf) {
        const tr = document.createElement('tr');
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
        if (cita.editable) {
            const link = document.createElement('a');
            link.href = 'agendar-cita.html?editar=' + cita.id;
            link.className = 'btn-secundario';
            link.textContent = 'Reprogramar';
            tdAcciones.appendChild(link);

            const formCancelar = document.createElement('form');
            formCancelar.className = 'form-cancelar-cita';
            const labelMotivo = document.createElement('label');
            labelMotivo.htmlFor = 'motivo-cancelar-' + cita.id;
            labelMotivo.textContent = 'Motivo (opcional)';
            const textareaMotivo = document.createElement('textarea');
            textareaMotivo.id = 'motivo-cancelar-' + cita.id;
            textareaMotivo.name = 'notas_admin';
            textareaMotivo.maxLength = notasAdminMax;
            textareaMotivo.rows = 2;
            const btnCancelar = document.createElement('button');
            btnCancelar.type = 'submit';
            btnCancelar.className = 'btn-secundario';
            btnCancelar.textContent = 'Cancelar';
            formCancelar.append(labelMotivo, textareaMotivo, btnCancelar);
            formCancelar.addEventListener('submit', (evento) => {
                evento.preventDefault();
                cancelarCita(cita, csrf, textareaMotivo.value);
            });
            tdAcciones.appendChild(formCancelar);
        }
        tr.appendChild(tdAcciones);
        return tr;
    }

    function renderMisCitas(citas, notasAdminMax, csrf) {
        if (!citas.length) {
            const p = document.createElement('p');
            p.textContent = 'Todavia no solicitaste ninguna cita.';
            misCitas.appendChild(p);
            return;
        }
        misCitas.appendChild(tabla(
            ['Fecha y hora de la cita', 'Sede', 'Estudio', 'Estado', ''],
            citas.map((cita) => renderFilaCita(cita, notasAdminMax, csrf))
        ));
    }

    async function init() {
        mostrarAviso();

        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }

        const editarId = new URL(window.location.href).searchParams.get('editar');
        const qs = editarId ? '?editar=' + encodeURIComponent(editarId) : '';
        const data = await apiJson('api/agendar-cita.php' + qs);
        if (!data) {
            return;
        }

        contenedorFecha.dataset.min = data.fechaMin;
        contenedorFecha.dataset.max = data.fechaMax;

        const editar = data.editar;
        poblarSucursales(selectSucursal, data.sucursalesOrden, editar ? editar.sucursalId : '');
        poblarProfesionales(selectProfesional, data.profesionales, editar ? editar.profesionalId : '');

        if (editar) {
            formAccion.value = 'reprogramar';
            formCitaId.value = editar.citaId;
            document.getElementById('estudio').value = editar.estudio;
            document.getElementById('fecha').value = editar.fecha;
            document.querySelector('.selector-fecha-texto').textContent = aVisible(editar.fecha);
            btnSubmit.textContent = 'Guardar cambios';
        }

        renderMisCitas(data.citas, data.notasAdminMax, sesion.csrf);

        window.SUCURSALES = data.sucursales;
        window.HORA_ELEGIDA = editar ? editar.hora : '';

        await cargarScripts('assets/js/agendado-cascada.js', 'assets/js/selector-combo.js');
    }

    init();
})();
