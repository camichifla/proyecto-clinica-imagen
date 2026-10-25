(() => {
    const lista = document.getElementById('solicitudes-lista');
    if (!lista) {
        return;
    }
    const mensajeOk = document.querySelector('[data-mensaje]');
    const mensajeError = document.querySelector('[data-mensaje-error]');
    const mostrarMensaje = crearMostrarMensaje(mensajeOk, mensajeError);

    async function procesar(cita, accion, fila, notasAdmin) {
        const campos = { accion, cita_id: String(cita.id) };
        if (notasAdmin !== undefined) {
            campos.notas_admin = notasAdmin;
        }
        const data = await enviarPost('api/solicitudes-cita.php', campos);
        if (!data) {
            mostrarMensaje(ERROR_CONEXION, true);
            return;
        }
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

    function renderFila(cita, notasAdminMax) {
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

        const botones = document.createElement('div');
        botones.className = 'botones-solicitud';

        const btnConfirmar = document.createElement('button');
        btnConfirmar.type = 'button';
        btnConfirmar.className = 'btn-exito';
        btnConfirmar.textContent = 'Confirmar';
        btnConfirmar.addEventListener('click', () => procesar(cita, 'confirmar', tr));

        // "Rechazar" only reveals the reason box; the request is sent by "Confirmar rechazo".
        const btnRechazar = document.createElement('button');
        btnRechazar.type = 'button';
        btnRechazar.className = 'btn-peligro';
        btnRechazar.textContent = 'Rechazar';
        btnRechazar.setAttribute('aria-expanded', 'false');
        btnRechazar.setAttribute('aria-controls', 'rechazo-' + cita.id);
        botones.append(btnConfirmar, btnRechazar);

        const formRechazar = document.createElement('form');
        formRechazar.className = 'form-rechazar-cita';
        formRechazar.id = 'rechazo-' + cita.id;
        formRechazar.hidden = true;
        const labelNotas = document.createElement('label');
        labelNotas.htmlFor = 'notas-' + cita.id;
        labelNotas.textContent = 'Motivo';
        const textareaNotas = document.createElement('textarea');
        textareaNotas.id = 'notas-' + cita.id;
        textareaNotas.name = 'notas_admin';
        textareaNotas.maxLength = notasAdminMax;
        textareaNotas.rows = 2;
        const btnConfirmarRechazo = document.createElement('button');
        btnConfirmarRechazo.type = 'submit';
        btnConfirmarRechazo.className = 'btn-peligro-suave';
        btnConfirmarRechazo.textContent = 'Confirmar rechazo';
        formRechazar.append(labelNotas, textareaNotas, btnConfirmarRechazo);
        formRechazar.addEventListener('submit', (evento) => {
            evento.preventDefault();
            procesar(cita, 'rechazar', tr, textareaNotas.value);
        });

        btnRechazar.addEventListener('click', () => {
            const abrir = formRechazar.hidden;
            formRechazar.hidden = !abrir;
            btnRechazar.setAttribute('aria-expanded', String(abrir));
            if (abrir) {
                textareaNotas.focus();
            }
        });

        divAcciones.append(botones, formRechazar);
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

    function renderTabla(data) {
        if (!data.citas.length) {
            renderVacio();
            return;
        }
        lista.innerHTML = '';
        lista.appendChild(tabla(
            ['Fecha y hora solicitada', 'Paciente', 'Estudio', 'Sede', 'Profesional', 'Acciones'],
            data.citas.map((cita) => renderFila(cita, data.notasAdminMax))
        ));
    }

    async function init() {
        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }
        const data = await apiJson('api/solicitudes-cita.php');
        if (!data) {
            return;
        }
        renderTabla(data);
    }

    init();
})();
