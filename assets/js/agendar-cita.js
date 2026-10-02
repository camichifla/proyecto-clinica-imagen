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

    function poblarSucursales(sucursalesOrden, seleccionada) {
        sucursalesOrden.forEach((sucursal) => {
            const opcion = document.createElement('option');
            opcion.value = String(sucursal.id);
            opcion.textContent = sucursal.direccion ? sucursal.nombre + ' — ' + sucursal.direccion : sucursal.nombre;
            if (String(sucursal.id) === seleccionada) {
                opcion.selected = true;
            }
            selectSucursal.appendChild(opcion);
        });
    }

    const ETIQUETAS_ESTUDIO = { placa: 'Placa', radiografia: 'Radiografia' };

    function poblarProfesionales(profesionales, seleccionado) {
        profesionales.forEach((profesional) => {
            const opcion = document.createElement('option');
            opcion.value = String(profesional.id);
            opcion.dataset.especializaciones = JSON.stringify(profesional.especializaciones);
            const etiquetas = profesional.especializaciones.map((e) => ETIQUETAS_ESTUDIO[e] || e).join(', ');
            opcion.textContent = profesional.apellido + ', ' + profesional.nombre + ' — ' + etiquetas;
            if (String(profesional.id) === seleccionado) {
                opcion.selected = true;
            }
            selectProfesional.appendChild(opcion);
        });
        if (!profesionales.length) {
            document.querySelector('[data-mensaje-sin-profesionales]').hidden = false;
        }
    }

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function aVisible(iso) {
        const [anio, mes, dia] = iso.split('-');
        return dia + '/' + mes + '/' + anio;
    }

    function celda(etiqueta, texto) {
        const td = document.createElement('td');
        td.dataset.label = etiqueta;
        td.textContent = texto;
        return td;
    }

    async function cancelarCita(cita, csrf, notasAdmin) {
        const body = new URLSearchParams({ csrf, accion: 'cancelar', cita_id: String(cita.id), notas_admin: notasAdmin });
        let respuesta;
        try {
            respuesta = await fetch('api/agendar-cita.php', { method: 'POST', body });
        } catch {
            return;
        }
        if (respuesta.status === 401 || respuesta.redirected) {
            window.location.href = 'login.html';
            return;
        }
        const data = await respuesta.json();
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
        const scroll = document.createElement('div');
        scroll.className = 'tabla-scroll';
        const tabla = document.createElement('table');
        tabla.className = 'tabla-datos';

        const thead = document.createElement('thead');
        const trHead = document.createElement('tr');
        ['Fecha y hora de la cita', 'Sede', 'Estudio', 'Estado', ''].forEach((texto) => {
            const th = document.createElement('th');
            th.scope = 'col';
            th.textContent = texto;
            trHead.appendChild(th);
        });
        thead.appendChild(trHead);
        tabla.appendChild(thead);

        const tbody = document.createElement('tbody');
        citas.forEach((cita) => tbody.appendChild(renderFilaCita(cita, notasAdminMax, csrf)));
        tabla.appendChild(tbody);

        scroll.appendChild(tabla);
        misCitas.appendChild(scroll);
    }

    async function init() {
        mostrarAviso();

        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }

        const editarId = new URL(window.location.href).searchParams.get('editar');
        const qs = editarId ? '?editar=' + encodeURIComponent(editarId) : '';
        let respuesta;
        try {
            respuesta = await fetch('api/agendar-cita.php' + qs);
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

        const editar = data.editar;
        poblarSucursales(data.sucursalesOrden, editar ? editar.sucursalId : '');
        poblarProfesionales(data.profesionales, editar ? editar.profesionalId : '');

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

        await cargarScript('assets/js/agendado-cascada.js');
        await cargarScript('assets/js/selector-combo.js');
    }

    init();
})();
