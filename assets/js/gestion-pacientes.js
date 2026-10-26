(() => {
    const lista = document.getElementById('pacientes-lista');
    const form = document.getElementById('form-paciente');
    const busqueda = document.getElementById('form-busqueda');
    if (!lista || !form || !busqueda) {
        return;
    }
    const seccionForm = document.getElementById('seccion-paciente-form');
    const mensajeOk = document.querySelector('[data-mensaje]');
    const mensajeError = document.querySelector('[data-mensaje-error]');
    const mostrarMensaje = crearMostrarMensaje(mensajeOk, mensajeError);
    const titulo = document.getElementById('paciente-form-titulo');
    const ayuda = document.getElementById('paciente-form-ayuda');
    const campoEmail = document.getElementById('campo-email');
    const CAMPOS = ['nombre', 'apellido', 'ci', 'direccion', 'numero'];

    // paciente === null: create; otherwise, edit their personal data (the email isn't edited here).
    function abrirForm(paciente) {
        limpiarErroresCampos(form);
        const editando = paciente !== null;
        form.elements.accion.value = editando ? 'guardar' : 'crear';
        form.elements.id.value = editando ? paciente.id : '';
        CAMPOS.forEach((campo) => {
            form.elements[campo].value = editando ? paciente[campo] || '' : '';
        });
        form.elements.email.value = '';
        campoEmail.hidden = editando;
        ayuda.hidden = editando;
        titulo.textContent = editando ? 'Editar paciente: ' + paciente.apellido + ', ' + paciente.nombre : 'Nuevo paciente';
        seccionForm.hidden = false;
        seccionForm.scrollIntoView({ block: 'start' });
        form.elements.nombre.focus();
    }

    function cerrarForm() {
        seccionForm.hidden = true;
    }

    // Only the login account goes away; the patient and their history stay ("Sin cuenta").
    async function borrarCuenta(paciente, boton) {
        if (!window.confirm('¿Borrar la cuenta de ' + paciente.apellido + ', ' + paciente.nombre + '? El paciente y su historial se conservan.')) {
            return;
        }
        boton.disabled = true;
        const cuerpo = new FormData(form);
        cuerpo.set('accion', 'borrar_cuenta');
        cuerpo.set('id', paciente.id);
        const data = await apiJson('api/gestion-pacientes.php', { method: 'POST', body: cuerpo });
        boton.disabled = false;
        if (!data || !data.ok) {
            mostrarMensaje((data && data.error) || 'No se pudo borrar la cuenta.', true);
            return;
        }
        mostrarMensaje('Cuenta borrada. El paciente y su historial se conservan.', false);
        cargar();
    }

    async function cambiarActivo(paciente, boton) {
        const accion = paciente.activo ? 'dar de baja' : 'reactivar';
        if (!window.confirm('¿Querés ' + accion + ' la cuenta de ' + paciente.apellido + ', ' + paciente.nombre + '?')) {
            return;
        }
        boton.disabled = true;
        const cuerpo = new FormData(form);
        cuerpo.set('accion', 'toggle_activo');
        cuerpo.set('id', paciente.id);
        const data = await apiJson('api/gestion-pacientes.php', { method: 'POST', body: cuerpo });
        boton.disabled = false;
        if (!data || !data.ok) {
            mostrarMensaje((data && data.error) || 'No se pudo cambiar el estado de la cuenta.', true);
            return;
        }
        mostrarMensaje(data.activo ? 'Cuenta reactivada.' : 'Cuenta dada de baja.', false);
        cargar();
    }

    function renderFila(paciente) {
        const tr = document.createElement('tr');
        tr.appendChild(celda('Nombre', paciente.apellido + ', ' + paciente.nombre));
        tr.appendChild(celda('Cédula', paciente.ci));
        tr.appendChild(celda('Teléfono', paciente.numero));
        tr.appendChild(celda('Correo', paciente.email || '-'));
        tr.appendChild(celda('Resultados', String(paciente.resultados)));
        tr.appendChild(celda('Cuenta', paciente.email ? (paciente.activo ? 'Activa' : 'Inactiva') : 'Sin cuenta'));
        const td = document.createElement('td');
        td.dataset.label = 'Acciones';
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn-secundario';
        boton.textContent = 'Editar';
        boton.addEventListener('click', () => abrirForm(paciente));
        td.appendChild(boton);
        if (paciente.email) {
            const baja = document.createElement('button');
            baja.type = 'button';
            baja.className = 'btn-secundario';
            baja.textContent = paciente.activo ? 'Dar de baja' : 'Reactivar';
            baja.addEventListener('click', () => cambiarActivo(paciente, baja));
            td.appendChild(baja);
            if (!paciente.activo) {
                const borrar = document.createElement('button');
                borrar.type = 'button';
                borrar.className = 'btn-peligro';
                borrar.textContent = 'Borrar cuenta';
                borrar.addEventListener('click', () => borrarCuenta(paciente, borrar));
                td.appendChild(borrar);
            }
        }
        tr.appendChild(td);
        return tr;
    }

    async function cargar() {
        const q = busqueda.elements.q.value.trim();
        const data = await apiJson('api/gestion-pacientes.php' + (q ? '?q=' + encodeURIComponent(q) : ''));
        if (!data || !data.pacientes) {
            mostrarMensaje('No se pudieron cargar los pacientes.', true);
            return;
        }
        if (!data.pacientes.length) {
            lista.replaceChildren(parrafo('No hay pacientes.'));
            return;
        }
        const nodos = [tabla(['Nombre', 'Cédula', 'Teléfono', 'Correo', 'Resultados', 'Cuenta', 'Acciones'], data.pacientes.map(renderFila))];
        if (data.truncado) {
            const p = document.createElement('p');
            p.className = 'campo-hint';
            p.textContent = 'Se muestran los primeros ' + data.max + ' resultados. Afiná la búsqueda para ver el resto.';
            nodos.push(p);
        }
        lista.replaceChildren(...nodos);
    }

    busqueda.addEventListener('submit', (evento) => {
        evento.preventDefault();
        cargar();
    });
    filtrarAlCambiar(busqueda);
    document.getElementById('paciente-nuevo').addEventListener('click', () => abrirForm(null));
    document.getElementById('paciente-cancelar').addEventListener('click', cerrarForm);

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        mensajeError.hidden = true;
        mensajeOk.hidden = true;
        limpiarErroresCampos(form);
        const editando = form.elements.accion.value === 'guardar';
        const data = await apiJson('api/gestion-pacientes.php', { method: 'POST', body: new FormData(form) });
        if (!data) {
            mostrarMensaje(ERROR_CONEXION, true);
            return;
        }
        if (data.errores) {
            const sueltos = mostrarErroresCampos(form, data.errores);
            if (sueltos.length) {
                mostrarMensaje(sueltos.join(' '), true);
            }
            return;
        }
        if (!data.ok) {
            mostrarMensaje(data.error || 'Ocurrió un error inesperado.', true);
            return;
        }
        mostrarMensaje(editando ? 'Paciente actualizado.' : 'Paciente registrado. Se le envió un correo para elegir su contraseña.', false);
        cerrarForm();
        cargar();
    });

    (async () => {
        const sesion = await window.sesionLista;
        if (sesion) {
            cargar();
        }
    })();
})();
