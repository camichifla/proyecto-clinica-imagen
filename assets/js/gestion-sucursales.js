(() => {
    const lista = document.getElementById('sucursales-lista');
    const form = document.getElementById('form-sucursal');
    if (!lista || !form) {
        return;
    }
    const mensajeOk = document.querySelector('[data-mensaje]');
    const mensajeError = document.querySelector('[data-mensaje-error]');
    const mostrarMensaje = crearMostrarMensaje(mensajeOk, mensajeError);
    const titulo = document.getElementById('sucursal-form-titulo');
    const botonNueva = document.getElementById('sucursal-nueva');
    const contenedorEstudios = document.getElementById('sucursal-estudios');
    const contenedorHorarios = document.getElementById('sucursal-horarios');

    function poblarEstudios(estudios) {
        contenedorEstudios.replaceChildren(...estudios.map((estudio) => {
            const label = document.createElement('label');
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.name = 'estudios[]';
            input.value = estudio.clave;
            label.append(input, ' ' + estudio.etiqueta + (estudio.activo ? '' : ' (inactivo)'));
            return label;
        }));
    }

    function prepararForm(sucursal) {
        limpiarErroresCampos(form);
        const editando = sucursal !== null;
        form.elements.id.value = editando ? sucursal.id : '';
        form.elements.nombre.value = editando ? sucursal.nombre : '';
        form.elements.direccion.value = editando ? sucursal.direccion || '' : '';
        form.elements.equipamiento.value = editando ? sucursal.equipamiento || '' : '';
        form.elements.latitud.value = editando && sucursal.latitud !== null ? sucursal.latitud : '';
        form.elements.longitud.value = editando && sucursal.longitud !== null ? sucursal.longitud : '';
        form.elements.activo.checked = !editando || sucursal.activo;
        contenedorEstudios.querySelectorAll('input').forEach((input) => {
            input.checked = editando && sucursal.estudios.includes(input.value);
        });
        contenedorHorarios.replaceChildren(...(editando ? sucursal.horarios : []).map(filaHorario));
        botonNueva.hidden = !editando;
        titulo.textContent = editando ? 'Editar sede' : 'Nueva sede';
        form.elements.nombre.focus();
    }

    function renderFila(sucursal) {
        const tr = document.createElement('tr');
        tr.appendChild(celda('Nombre', sucursal.nombre));
        tr.appendChild(celda('Dirección', sucursal.direccion || '-'));
        tr.appendChild(celda('Estado', sucursal.activo ? 'Activa' : 'Inactiva'));
        const td = document.createElement('td');
        td.dataset.label = 'Acciones';
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn-secundario';
        boton.textContent = 'Editar';
        boton.addEventListener('click', () => prepararForm(sucursal));
        td.appendChild(boton);
        tr.appendChild(td);
        return tr;
    }

    async function cargar() {
        const data = await apiJson('api/gestion-sucursales.php');
        if (!data || !data.sucursales) {
            mostrarMensaje('No se pudieron cargar las sucursales.', true);
            return;
        }
        if (!contenedorEstudios.children.length) {
            poblarEstudios(data.estudios);
        }
        lista.replaceChildren(tabla(['Nombre', 'Dirección', 'Estado', 'Acciones'], data.sucursales.map(renderFila)));
    }

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        mensajeError.hidden = true;
        mensajeOk.hidden = true;
        limpiarErroresCampos(form);
        const editando = form.elements.id.value !== '';
        const cuerpo = new FormData(form);
        cuerpo.set('horarios', JSON.stringify(turnosDeContenedor(contenedorHorarios)));
        const data = await apiJson('api/gestion-sucursales.php', { method: 'POST', body: cuerpo });
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
            mostrarMensaje(data.error || 'Ocurrio un error inesperado.', true);
            return;
        }
        mostrarMensaje(editando ? 'Sede actualizada.' : 'Sede creada.', false);
        prepararForm(null);
        cargar();
    });

    document.getElementById('horario-agregar').addEventListener('click', () => {
        contenedorHorarios.appendChild(filaHorario({ dia: 1, apertura: '08:00', cierre: '20:00' }));
    });
    botonNueva.addEventListener('click', () => prepararForm(null));

    (async () => {
        const sesion = await window.sesionLista;
        if (sesion) {
            cargar();
        }
    })();
})();
