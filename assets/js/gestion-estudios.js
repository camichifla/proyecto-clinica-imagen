(() => {
    const lista = document.getElementById('estudios-lista');
    const form = document.getElementById('form-estudio');
    if (!lista || !form) {
        return;
    }
    const mensajeOk = document.querySelector('[data-mensaje]');
    const mensajeError = document.querySelector('[data-mensaje-error]');
    const mostrarMensaje = crearMostrarMensaje(mensajeOk, mensajeError);
    const titulo = document.getElementById('estudio-form-titulo');
    const botonNuevo = document.getElementById('estudio-nuevo');
    const activoLabel = document.getElementById('estudio-activo-label');
    const claveHint = document.getElementById('estudio-clave-hint');

    function prepararForm(estudio) {
        limpiarErroresCampos(form);
        const editando = estudio !== null;
        form.elements.accion.value = editando ? 'editar' : 'crear';
        form.elements.clave.value = editando ? estudio.clave : '';
        form.elements.clave.readOnly = editando;
        form.elements.etiqueta.value = editando ? estudio.etiqueta : '';
        form.elements.descripcion.value = editando ? estudio.descripcion : '';
        form.elements.autoagendable.checked = editando && estudio.autoagendable;
        form.elements.activo.checked = editando && estudio.activo;
        activoLabel.hidden = !editando;
        botonNuevo.hidden = !editando;
        claveHint.hidden = editando;
        titulo.textContent = editando ? 'Editar estudio' : 'Nuevo estudio';
        (editando ? form.elements.etiqueta : form.elements.clave).focus();
    }

    function renderFila(estudio) {
        const tr = document.createElement('tr');
        tr.appendChild(celda('Clave', estudio.clave));
        tr.appendChild(celda('Etiqueta', estudio.etiqueta));
        tr.appendChild(celda('Agendable por el paciente', estudio.autoagendable ? 'Sí' : 'No'));
        tr.appendChild(celda('Estado', estudio.activo ? 'Activo' : 'Inactivo'));
        const td = document.createElement('td');
        td.dataset.label = 'Acciones';
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn-secundario';
        boton.textContent = 'Editar';
        boton.addEventListener('click', () => prepararForm(estudio));
        td.appendChild(boton);
        tr.appendChild(td);
        return tr;
    }

    async function cargar() {
        const data = await apiJson('api/gestion-estudios.php');
        if (!data || !data.estudios) {
            mostrarMensaje('No se pudieron cargar los estudios.', true);
            return;
        }
        lista.replaceChildren(tabla(
            ['Clave', 'Etiqueta', 'Agendable por el paciente', 'Estado', 'Acciones'],
            data.estudios.map(renderFila)
        ));
    }

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        mensajeError.hidden = true;
        mensajeOk.hidden = true;
        limpiarErroresCampos(form);
        const editando = form.elements.accion.value === 'editar';
        const data = await apiJson('api/gestion-estudios.php', { method: 'POST', body: new FormData(form) });
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
        mostrarMensaje(editando ? 'Estudio actualizado.' : 'Estudio creado.', false);
        prepararForm(null);
        cargar();
    });

    botonNuevo.addEventListener('click', () => prepararForm(null));

    (async () => {
        const sesion = await window.sesionLista;
        if (sesion) {
            cargar();
        }
    })();
})();
