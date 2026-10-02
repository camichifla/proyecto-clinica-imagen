(() => {
    const lista = document.getElementById('solicitudes-lista');
    if (!lista) {
        return;
    }
    const mensajeOk = document.querySelector('[data-mensaje]');
    const mensajeError = document.querySelector('[data-mensaje-error]');

    function mostrarMensaje(texto, esError) {
        const el = esError ? mensajeError : mensajeOk;
        const otro = esError ? mensajeOk : mensajeError;
        otro.hidden = true;
        el.textContent = texto;
        el.hidden = false;
    }

    function celda(etiqueta, texto) {
        const td = document.createElement('td');
        td.dataset.label = etiqueta;
        td.textContent = texto;
        return td;
    }

    async function procesar(cita, accion, fila, notasAdmin, csrf) {
        const body = new URLSearchParams({ csrf, accion, cita_id: String(cita.id) });
        if (notasAdmin !== undefined) {
            body.set('notas_admin', notasAdmin);
        }
        let respuesta;
        try {
            respuesta = await fetch('api/solicitudes-cita.php', { method: 'POST', body });
        } catch {
            mostrarMensaje('No se pudo conectar. Intenta nuevamente.', true);
            return;
        }
        if (respuesta.status === 401 || respuesta.redirected) {
            window.location.href = 'login.html';
            return;
        }
        const data = await respuesta.json();
        if (!data.ok) {
            mostrarMensaje(data.error || 'Ocurrio un error inesperado.', true);
            return;
        }
        mostrarMensaje(data.mensaje, false);
        fila.remove();
        if (!lista.querySelector('tbody tr')) {
            renderVacio();
        }
    }

    function renderFila(cita, notasAdminMax, csrf) {
        const tr = document.createElement('tr');
        tr.appendChild(celda('Fecha y hora solicitada', cita.fechaHoraSolicitada));
        tr.appendChild(celda('Paciente', cita.pacienteLabel));
        tr.appendChild(celda('Estudio', cita.estudioLabel));
        tr.appendChild(celda('Sede', cita.sucursalNombre || '-'));
        tr.appendChild(celda('Profesional', cita.profesionalLabel));

        const tdAcciones = document.createElement('td');
        tdAcciones.dataset.label = 'Acciones';
        const divAcciones = document.createElement('div');
        divAcciones.className = 'acciones-solicitud';

        const formConfirmar = document.createElement('form');
        formConfirmar.className = 'form-confirmar-cita';
        const btnConfirmar = document.createElement('button');
        btnConfirmar.type = 'submit';
        btnConfirmar.className = 'btn-primary btn-confirmar';
        btnConfirmar.textContent = 'Confirmar';
        formConfirmar.appendChild(btnConfirmar);
        formConfirmar.addEventListener('submit', (evento) => {
            evento.preventDefault();
            procesar(cita, 'confirmar', tr, undefined, csrf);
        });

        const formRechazar = document.createElement('form');
        formRechazar.className = 'form-rechazar-cita';
        const labelNotas = document.createElement('label');
        labelNotas.htmlFor = 'notas-' + cita.id;
        labelNotas.textContent = 'Motivo (opcional)';
        const textareaNotas = document.createElement('textarea');
        textareaNotas.id = 'notas-' + cita.id;
        textareaNotas.name = 'notas_admin';
        textareaNotas.maxLength = notasAdminMax;
        textareaNotas.rows = 2;
        const btnRechazar = document.createElement('button');
        btnRechazar.type = 'submit';
        btnRechazar.className = 'btn-secundario btn-rechazar';
        btnRechazar.textContent = 'Rechazar';
        formRechazar.append(labelNotas, textareaNotas, btnRechazar);
        formRechazar.addEventListener('submit', (evento) => {
            evento.preventDefault();
            procesar(cita, 'rechazar', tr, textareaNotas.value, csrf);
        });

        divAcciones.append(formConfirmar, formRechazar);
        tdAcciones.appendChild(divAcciones);
        tr.appendChild(tdAcciones);
        return tr;
    }

    function renderVacio() {
        lista.innerHTML = '';
        const p = document.createElement('p');
        p.textContent = 'No hay solicitudes de cita pendientes.';
        lista.appendChild(p);
    }

    function renderTabla(data, csrf) {
        if (!data.citas.length) {
            renderVacio();
            return;
        }
        const scroll = document.createElement('div');
        scroll.className = 'tabla-scroll';
        const tabla = document.createElement('table');
        tabla.className = 'tabla-datos';

        const thead = document.createElement('thead');
        const trHead = document.createElement('tr');
        ['Fecha y hora solicitada', 'Paciente', 'Estudio', 'Sede', 'Profesional', 'Acciones'].forEach((texto) => {
            const th = document.createElement('th');
            th.scope = 'col';
            th.textContent = texto;
            trHead.appendChild(th);
        });
        thead.appendChild(trHead);
        tabla.appendChild(thead);

        const tbody = document.createElement('tbody');
        data.citas.forEach((cita) => tbody.appendChild(renderFila(cita, data.notasAdminMax, csrf)));
        tabla.appendChild(tbody);

        scroll.appendChild(tabla);
        lista.innerHTML = '';
        lista.appendChild(scroll);
    }

    async function init() {
        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }
        let respuesta;
        try {
            respuesta = await fetch('api/solicitudes-cita.php');
        } catch {
            return;
        }
        if (respuesta.status === 401 || respuesta.status === 403 || respuesta.redirected) {
            window.location.href = 'login.html';
            return;
        }
        const data = await respuesta.json();
        renderTabla(data, sesion.csrf);
    }

    init();
})();
