(() => {
    const lista = document.getElementById('turnos-lista');
    const formFiltros = document.getElementById('filtros-turnos');
    if (!lista || !formFiltros) {
        return;
    }
    const mensajeOk = document.querySelector('[data-mensaje]');
    const mensajeError = document.querySelector('[data-mensaje-error]');
    const totalesEl = document.getElementById('turnos-totales');
    const avisoTruncado = document.getElementById('turnos-truncado');
    const selectSucursal = document.getElementById('filtro-sucursal');
    const selectProfesional = document.getElementById('filtro-profesional');
    const filtrosActivos = document.getElementById('turnos-filtros-activos');

    let csrf = '';
    let primeraCarga = true;

    function mostrarMensaje(texto, esError) {
        const el = esError ? mensajeError : mensajeOk;
        const otro = esError ? mensajeOk : mensajeError;
        otro.hidden = true;
        el.textContent = texto;
        el.hidden = false;
    }

    function textoErrores(data) {
        if (data && data.errores) {
            return Object.values(data.errores).join(' ');
        }
        return (data && data.error) || 'No se pudo conectar. Intenta nuevamente.';
    }

    async function enviarAccion(cuerpo, textoOk) {
        const data = await apiJson('api/turnos.php', { method: 'POST', body: new URLSearchParams({ csrf, ...cuerpo }) });
        if (!data || !data.ok) {
            mostrarMensaje(textoErrores(data), true);
            return;
        }
        mostrarMensaje(textoOk, false);
        cargar();
    }

    function campo(idCampo, etiqueta, control) {
        const label = document.createElement('label');
        label.htmlFor = idCampo;
        label.textContent = etiqueta;
        control.id = idCampo;
        return [label, control];
    }

    function desplegable(textoBoton, form) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn-secundario';
        boton.textContent = textoBoton;
        boton.setAttribute('aria-expanded', 'false');
        form.hidden = true;
        boton.addEventListener('click', () => {
            form.hidden = !form.hidden;
            boton.setAttribute('aria-expanded', String(!form.hidden));
        });
        return boton;
    }

    function formCancelar(cita, notasAdminMax) {
        const form = document.createElement('form');
        form.className = 'accion-turno';
        const textarea = document.createElement('textarea');
        textarea.name = 'notas_admin';
        textarea.maxLength = notasAdminMax;
        textarea.rows = 2;
        const confirmar = document.createElement('button');
        confirmar.type = 'submit';
        confirmar.className = 'btn-primary';
        confirmar.textContent = 'Confirmar cancelacion';
        form.append(...campo('motivo-' + cita.id, 'Motivo (opcional)', textarea), confirmar);
        form.addEventListener('submit', (evento) => {
            evento.preventDefault();
            enviarAccion({ accion: 'cancelar', cita_id: String(cita.id), notas_admin: textarea.value }, 'La cita fue cancelada.');
        });
        return form;
    }

    function formReprogramar(cita, rango) {
        const form = document.createElement('form');
        form.className = 'accion-turno';
        const fecha = document.createElement('input');
        fecha.type = 'date';
        fecha.min = rango.min;
        fecha.max = rango.max;
        const hora = document.createElement('select');
        const confirmar = document.createElement('button');
        confirmar.type = 'submit';
        confirmar.className = 'btn-primary';
        confirmar.textContent = 'Confirmar reprogramacion';

        function opcionHora(valor, texto) {
            const o = document.createElement('option');
            o.value = valor;
            o.textContent = texto;
            return o;
        }
        hora.replaceChildren(opcionHora('', 'Elegi una fecha primero'));

        let pedido = 0;
        fecha.addEventListener('change', async () => {
            const actual = ++pedido;
            hora.replaceChildren(opcionHora('', 'Elegi un horario'));
            if (!fecha.value) {
                return;
            }
            const data = await apiJson('api/turnos.php?' + new URLSearchParams({ horas: '1', cita_id: String(cita.id), fecha: fecha.value }));
            if (actual !== pedido) {
                return;
            }
            if (!data || !data.horas) {
                mostrarMensaje(textoErrores(data), true);
                return;
            }
            if (!data.horas.length) {
                hora.replaceChildren(opcionHora('', 'Sin horarios disponibles ese dia'));
                return;
            }
            data.horas.forEach((h) => hora.appendChild(opcionHora(h, h)));
        });

        form.append(...campo('fecha-' + cita.id, 'Nueva fecha', fecha), ...campo('hora-' + cita.id, 'Nueva hora', hora), confirmar);
        form.addEventListener('submit', (evento) => {
            evento.preventDefault();
            enviarAccion({ accion: 'reprogramar', cita_id: String(cita.id), fecha: fecha.value, hora: hora.value }, 'La cita fue reprogramada.');
        });
        return form;
    }

    function renderFila(cita, data) {
        const tr = document.createElement('tr');
        tr.appendChild(celda('Codigo', cita.codigo));
        tr.appendChild(celda('Fecha', cita.fecha));
        tr.appendChild(celda('Hora', cita.hora));
        tr.appendChild(celda('Paciente', cita.pacienteLabel));
        tr.appendChild(celda('Estudio', cita.estudioLabel));
        tr.appendChild(celda('Sede', cita.sucursalNombre || '-'));
        tr.appendChild(celda('Profesional', cita.profesionalLabel));
        tr.appendChild(celdaEstado(cita.estado, cita.estadoLabel));

        const tdAcciones = document.createElement('td');
        tdAcciones.dataset.label = 'Acciones';
        if (cita.gestionable) {
            const div = document.createElement('div');
            div.className = 'acciones-turno';
            const fCancelar = formCancelar(cita, data.notasAdminMax);
            const fReprogramar = formReprogramar(cita, data.rangoReserva);
            div.append(
                desplegable('Cancelar', fCancelar), fCancelar,
                desplegable('Reprogramar', fReprogramar), fReprogramar
            );
            tdAcciones.appendChild(div);
        }
        tr.appendChild(tdAcciones);
        return tr;
    }

    function renderTotales(totales) {
        const items = [['Total', totales.total], ['Pendientes', totales.pendiente], ['Confirmadas', totales.confirmada], ['Rechazadas', totales.rechazada], ['Canceladas', totales.cancelada]];
        totalesEl.replaceChildren(...items.map(([etiqueta, n]) => {
            const li = document.createElement('li');
            li.textContent = etiqueta + ': ' + n;
            return li;
        }));
    }

    function poblarFiltros(data) {
        if (!primeraCarga) {
            return;
        }
        primeraCarga = false;
        poblarSucursales(selectSucursal, data.sucursales.map((s) => ({ id: s.id, nombre: s.nombre })));
        data.profesionales.forEach((p) => {
            const o = document.createElement('option');
            o.value = String(p.id);
            o.textContent = p.label;
            selectProfesional.appendChild(o);
        });
        formFiltros.elements.desde.value = data.desde;
        formFiltros.elements.hasta.value = data.hasta;
    }

    async function cargar() {
        const params = new URLSearchParams();
        new FormData(formFiltros).forEach((valor, clave) => {
            if (String(valor).trim() !== '') {
                params.set(clave, String(valor).trim());
            }
        });
        const data = await apiJson('api/turnos.php?' + params.toString());
        if (!data || !data.citas) {
            mostrarMensaje(textoErrores(data), true);
            return;
        }
        poblarFiltros(data);
        renderTotales(data.totales);
        const elegido = (select) => (select.value ? select.selectedOptions[0].textContent : 'Todos');
        filtrosActivos.textContent = `Reporte de turnos del ${data.desde} al ${data.hasta}. Sede: ${elegido(selectSucursal)}. Profesional: ${elegido(selectProfesional)}.`;
        avisoTruncado.hidden = !data.truncado;
        if (!data.citas.length) {
            const p = document.createElement('p');
            p.textContent = 'No hay citas para esos filtros.';
            lista.replaceChildren(p);
            return;
        }
        lista.replaceChildren(tabla(
            ['Codigo', 'Fecha', 'Hora', 'Paciente', 'Estudio', 'Sede', 'Profesional', 'Estado', 'Acciones'],
            data.citas.map((cita) => renderFila(cita, data))
        ));
    }

    formFiltros.addEventListener('submit', (evento) => {
        evento.preventDefault();
        mensajeError.hidden = true;
        cargar();
    });
    document.getElementById('btn-imprimir').addEventListener('click', () => window.print());

    async function init() {
        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }
        csrf = sesion.csrf;
        cargar();
    }

    init();
})();
