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
    const titulo = document.getElementById('paciente-form-titulo');
    const ayuda = document.getElementById('paciente-form-ayuda');
    const campoEmail = document.getElementById('campo-email');
    const CAMPOS = ['nombre', 'apellido', 'ci', 'direccion', 'numero'];

    function mostrarMensaje(texto, esError) {
        const el = esError ? mensajeError : mensajeOk;
        const otro = esError ? mensajeOk : mensajeError;
        otro.hidden = true;
        el.textContent = texto;
        el.hidden = false;
    }

    // paciente === null: alta; si no, edicion de sus datos personales (el correo no se edita aca).
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

    async function cambiarActivo(paciente, boton) {
        const accion = paciente.activo ? 'dar de baja' : 'reactivar';
        if (!window.confirm('Seguro que querés ' + accion + ' la cuenta de ' + paciente.apellido + ', ' + paciente.nombre + '?')) {
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
            const p = document.createElement('p');
            p.textContent = 'No se encontraron pacientes.';
            lista.replaceChildren(p);
            return;
        }
        const nodos = [tabla(['Nombre', 'Cédula', 'Teléfono', 'Correo', 'Cuenta', 'Acciones'], data.pacientes.map(renderFila))];
        if (data.truncado) {
            const p = document.createElement('p');
            p.className = 'campo-hint';
            p.textContent = 'Se muestran los primeros ' + data.max + ' resultados. Afina la busqueda para ver el resto.';
            nodos.push(p);
        }
        lista.replaceChildren(...nodos);
    }

    busqueda.addEventListener('submit', (evento) => {
        evento.preventDefault();
        cargar();
    });
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
            mostrarMensaje('No se pudo conectar. Intenta nuevamente.', true);
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
            mostrarMensaje(data.error || 'Ocurrio un error inesperado.', true);
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
