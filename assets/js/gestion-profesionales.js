(() => {
    const lista = document.getElementById('profesionales-lista');
    const form = document.getElementById('form-profesional');
    if (!lista || !form) {
        return;
    }
    const seccionForm = document.getElementById('seccion-profesional-form');
    const mensajeOk = document.querySelector('[data-mensaje]');
    const mensajeError = document.querySelector('[data-mensaje-error]');
    const mostrarMensaje = crearMostrarMensaje(mensajeOk, mensajeError);
    const titulo = document.getElementById('profesional-form-titulo');
    const contenedorSucursales = document.getElementById('profesional-sucursales');
    const contenedorEstudios = document.getElementById('profesional-estudios');
    const contenedorHorarios = document.getElementById('profesional-horarios');
    let catalogo = { estudios: [], sucursales: [] };
    let etiquetas = {};

    function casilla(nombre, valor, texto, marcada) {
        const label = document.createElement('label');
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.name = nombre;
        input.value = valor;
        input.checked = marcada;
        label.append(input, ' ' + texto);
        return label;
    }

    function abrirForm(profesional) {
        limpiarErroresCampos(form);
        form.elements.id.value = profesional.id;
        titulo.textContent = 'Editar profesional: ' + profesional.apellido + ', ' + profesional.nombre;
        contenedorSucursales.replaceChildren(...catalogo.sucursales.map((s) =>
            casilla('sucursales[]', String(s.id), s.nombre + (s.activo ? '' : ' (inactiva)'), profesional.sucursales.includes(s.id))));
        // Estudios activos, mas los inactivos que el profesional ya tenia (para no perderlos al guardar).
        contenedorEstudios.replaceChildren(...catalogo.estudios
            .filter((e) => e.activo || profesional.especializacion.includes(e.clave))
            .map((e) => casilla('especializacion[]', e.clave, e.etiqueta + (e.activo ? '' : ' (inactivo)'), profesional.especializacion.includes(e.clave))));
        contenedorHorarios.replaceChildren(...profesional.horarios.map(filaHorario));
        seccionForm.hidden = false;
        seccionForm.scrollIntoView({ block: 'start' });
    }

    function cerrarForm() {
        seccionForm.hidden = true;
        form.elements.id.value = '';
    }

    function renderFila(profesional) {
        const tr = document.createElement('tr');
        tr.appendChild(celda('Nombre', profesional.apellido + ', ' + profesional.nombre));
        tr.appendChild(celda('Sedes', profesional.sucursales
            .map((id) => (catalogo.sucursales.find((s) => s.id === id) || {}).nombre).filter(Boolean).join(', ') || '-'));
        tr.appendChild(celda('Estudios', profesional.especializacion.map((c) => etiquetas[c] || c).join(', ') || '-'));
        tr.appendChild(celda('Horarios propios', profesional.horarios.length ? 'Sí' : 'No (siempre que la sede abra)'));
        const td = document.createElement('td');
        td.dataset.label = 'Acciones';
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn-secundario';
        boton.textContent = 'Editar';
        boton.addEventListener('click', () => abrirForm(profesional));
        td.appendChild(boton);
        tr.appendChild(td);
        return tr;
    }

    async function cargar() {
        const data = await apiJson('api/gestion-profesionales.php');
        if (!data || !data.profesionales) {
            mostrarMensaje('No se pudieron cargar los profesionales.', true);
            return;
        }
        catalogo = data;
        etiquetas = Object.fromEntries(data.estudios.map((e) => [e.clave, e.etiqueta]));
        if (!data.profesionales.length) {
            const p = document.createElement('p');
            p.textContent = 'Todavía no hay profesionales. Crea una cuenta de rol Profesional en Manejar Usuarios.';
            lista.replaceChildren(p);
            return;
        }
        lista.replaceChildren(tabla(['Nombre', 'Sedes', 'Estudios', 'Horarios propios', 'Acciones'], data.profesionales.map(renderFila)));
    }

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        mensajeError.hidden = true;
        mensajeOk.hidden = true;
        limpiarErroresCampos(form);
        const cuerpo = new FormData(form);
        cuerpo.set('horarios', JSON.stringify(turnosDeContenedor(contenedorHorarios)));
        const data = await apiJson('api/gestion-profesionales.php', { method: 'POST', body: cuerpo });
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
        mostrarMensaje('Profesional actualizado.', false);
        cerrarForm();
        cargar();
    });

    document.getElementById('horario-agregar').addEventListener('click', () => {
        contenedorHorarios.appendChild(filaHorario({ dia: 1, apertura: '08:00', cierre: '20:00' }));
    });
    document.getElementById('profesional-cancelar').addEventListener('click', cerrarForm);

    (async () => {
        const sesion = await window.sesionLista;
        if (sesion) {
            cargar();
        }
    })();
})();
