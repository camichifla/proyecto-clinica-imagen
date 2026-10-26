(() => {
    const lista = document.getElementById('turnos-lista');
    const formFiltros = document.getElementById('filtros-turnos');
    if (!lista || !formFiltros) {
        return;
    }
    const mensajeOk = document.querySelector('[data-mensaje]');
    const mensajeError = document.querySelector('[data-mensaje-error]');
    const mostrarMensaje = crearMostrarMensaje(mensajeOk, mensajeError);
    const avisoTruncado = document.getElementById('turnos-truncado');
    const selectSucursal = document.getElementById('filtro-sucursal');
    const selectProfesional = document.getElementById('filtro-profesional');

    let primeraCarga = true;

    // Pending citas are confirmed/rejected through the solicitudes API, the rest through turnos.
    async function enviarAccion(cuerpo, textoOk, api = 'api/turnos.php') {
        const data = await enviarPost(api, cuerpo);
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

    function desplegable(textoBoton, form, clase = 'btn-secundario') {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = clase;
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
        confirmar.className = 'btn-peligro-suave';
        confirmar.textContent = 'Confirmar cancelación';
        form.append(...campo('motivo-' + cita.id, 'Motivo', textarea), confirmar);
        form.addEventListener('submit', (evento) => {
            evento.preventDefault();
            enviarAccion({ accion: 'cancelar', cita_id: String(cita.id), notas_admin: textarea.value }, 'La cita fue cancelada.');
        });
        return form;
    }

    function formRechazar(cita, notasAdminMax) {
        const form = document.createElement('form');
        form.className = 'accion-turno';
        const textarea = document.createElement('textarea');
        textarea.name = 'notas_admin';
        textarea.maxLength = notasAdminMax;
        textarea.rows = 2;
        const confirmar = document.createElement('button');
        confirmar.type = 'submit';
        confirmar.className = 'btn-peligro-suave';
        confirmar.textContent = 'Confirmar rechazo';
        form.append(...campo('rechazo-' + cita.id, 'Motivo', textarea), confirmar);
        form.addEventListener('submit', (evento) => {
            evento.preventDefault();
            enviarAccion({ accion: 'rechazar', cita_id: String(cita.id), notas_admin: textarea.value }, 'La cita fue rechazada.', 'api/solicitudes-cita.php');
        });
        return form;
    }

    function botonConfirmar(cita) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn-exito';
        boton.textContent = 'Confirmar';
        boton.addEventListener('click', () => {
            enviarAccion({ accion: 'confirmar', cita_id: String(cita.id) }, 'La cita fue confirmada.', 'api/solicitudes-cita.php');
        });
        return boton;
    }

    function formReprogramar(cita, rango) {
        const form = document.createElement('form');
        form.className = 'accion-turno';
        const fecha = document.createElement('input');
        fecha.type = 'date';
        fecha.min = rango.min;
        fecha.max = rango.max;
        const hora = document.createElement('select');
        hora.className = 'selector-combo';
        const confirmar = document.createElement('button');
        confirmar.type = 'submit';
        confirmar.className = 'btn-primary';
        confirmar.textContent = 'Confirmar reprogramación';

        const opcionHora = (valor, texto) => new Option(texto, valor);
        // The hour stays locked until a date with free hours is chosen.
        hora.replaceChildren(opcionHora('', 'Elegí una fecha primero'));
        hora.disabled = true;

        let pedido = 0;
        fecha.addEventListener('change', async () => {
            const actual = ++pedido;
            hora.disabled = true;
            if (!fecha.value) {
                hora.replaceChildren(opcionHora('', 'Elegí una fecha primero'));
                return;
            }
            hora.replaceChildren(opcionHora('', 'Elegí un horario'));
            const data = await apiJson('api/turnos.php?' + new URLSearchParams({ horas: '1', cita_id: String(cita.id), fecha: fecha.value }));
            if (actual !== pedido) {
                return;
            }
            if (!data || !data.horas) {
                mostrarMensaje(textoErrores(data), true);
                return;
            }
            if (!data.horas.length) {
                hora.replaceChildren(opcionHora('', 'Sin horarios disponibles ese día'));
                return;
            }
            data.horas.forEach((h) => hora.appendChild(opcionHora(h, h)));
            hora.disabled = false;
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
        tr.appendChild(celda('Código', cita.codigo));
        tr.appendChild(celda('Fecha', fechaHora(cita.fecha)));
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
            if (cita.estado === 'pendiente') {
                const fRechazar = formRechazar(cita, data.notasAdminMax);
                div.append(desplegable('Rechazar', fRechazar, 'btn-peligro'), fRechazar, botonConfirmar(cita));
            } else {
                const fCancelar = formCancelar(cita, data.notasAdminMax);
                const fReprogramar = formReprogramar(cita, data.rangoReserva);
                div.append(
                    desplegable('Cancelar', fCancelar, 'btn-peligro'), fCancelar,
                    desplegable('Reprogramar', fReprogramar), fReprogramar
                );
            }
            tdAcciones.appendChild(div);
        }
        tr.appendChild(tdAcciones);
        return tr;
    }

    // Staff booking ("Nueva cita") and the live free-hours list ("Turnos disponibles"), fed by the catalog of the first load.
    const btnNueva = document.getElementById('btn-nueva-cita');
    const formNueva = document.getElementById('form-nueva-cita');
    const formDisp = document.getElementById('form-disponibles');
    const listaHoras = document.getElementById('disponibles-horas');
    const nueva = formNueva.elements;
    const disp = formDisp.elements;
    let reserva = null;
    let sincronizando = false; // programmatic updates must not re-trigger the change listeners

    const opcion = (valor, texto) => new Option(texto, valor);
    const notificar = (select) => select.dispatchEvent(new Event('change', { bubbles: true }));

    function abrirNueva(abrir) {
        formNueva.hidden = !abrir;
        btnNueva.setAttribute('aria-expanded', String(abrir));
    }

    // Professionals of the branch (and, if given, doing the study).
    function poblarProfesionalesDe(select, sedeId, estudio, vacio) {
        const sede = reserva.sucursales[sedeId];
        const validos = reserva.profesionales.filter((p) => sede && sede.profesionales.includes(p.id)
            && (!estudio || p.especializaciones.includes(estudio)));
        const previo = select.value;
        select.replaceChildren(opcion('', vacio), ...validos.map((p) => opcion(String(p.id), p.apellido + ', ' + p.nombre)));
        select.value = validos.some((p) => String(p.id) === previo) ? previo : '';
    }

    async function horasLibres(f) {
        const data = await apiJson('api/turnos.php?' + new URLSearchParams({
            disponibles: '1',
            sucursal_id: f.sucursal_id.value,
            fecha: f.fecha.value,
            profesional_id: f.profesional_id.value,
            estudio: f.estudio.value,
        }));
        if (!data || !data.horas) {
            mostrarMensaje(textoErrores(data), true);
            return null;
        }
        return data.horas;
    }

    let pedidoNueva = 0;
    async function refrescarHorasNueva(elegir = '') {
        const actual = ++pedidoNueva;
        const mostrar = (texto, horas = []) => {
            nueva.hora.replaceChildren(opcion('', texto), ...horas.map((h) => opcion(h, h)));
            nueva.hora.disabled = !horas.length;
            nueva.hora.value = horas.includes(elegir) ? elegir : '';
        };
        if (!nueva.sucursal_id.value || !nueva.estudio.value || !nueva.fecha.value) {
            mostrar('Elegí sede, estudio y fecha primero');
            return;
        }
        nueva.hora.disabled = true;
        const horas = await horasLibres(nueva);
        if (actual !== pedidoNueva || !horas) {
            return;
        }
        mostrar(horas.length ? 'Elegí un horario' : 'Sin horarios disponibles ese día', horas);
    }

    let pedidoDisp = 0;
    async function refrescarDisponibles() {
        const actual = ++pedidoDisp;
        if (!disp.sucursal_id.value || !disp.fecha.value) {
            listaHoras.replaceChildren(parrafo('Elegí una sede y una fecha para ver los turnos libres.'));
            return;
        }
        const horas = await horasLibres(disp);
        if (actual !== pedidoDisp || !horas) {
            return;
        }
        listaHoras.replaceChildren(...(horas.length ? horas.map(botonHora) : [parrafo('No hay turnos libres ese día.')]));
    }

    function botonHora(hora) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn-secundario';
        boton.textContent = hora;
        boton.addEventListener('click', () => prellenarNueva(hora));
        return boton;
    }

    // A free hour was picked: open "Nueva cita" with the list's filters and that hour.
    async function prellenarNueva(hora) {
        abrirNueva(true);
        sincronizando = true;
        nueva.sucursal_id.value = disp.sucursal_id.value;
        nueva.estudio.value = disp.estudio.value;
        nueva.fecha.value = disp.fecha.value;
        poblarProfesionalesDe(nueva.profesional_id, nueva.sucursal_id.value, nueva.estudio.value, 'Cualquiera');
        nueva.profesional_id.value = disp.profesional_id.value;
        [nueva.sucursal_id, nueva.estudio, nueva.profesional_id, nueva.fecha].forEach(notificar);
        sincronizando = false;
        await refrescarHorasNueva(hora);
        sincronizando = true;
        notificar(nueva.hora);
        sincronizando = false;
        formNueva.scrollIntoView({ block: 'nearest' });
    }

    function limpiarNueva() {
        sincronizando = true;
        ['paciente_id', 'estudio', 'profesional_id', 'fecha'].forEach((n) => {
            nueva[n].value = '';
            notificar(nueva[n]);
        });
        sincronizando = false;
        poblarProfesionalesDe(nueva.profesional_id, nueva.sucursal_id.value, '', 'Cualquiera');
        refrescarHorasNueva();
        abrirNueva(false);
    }

    function iniciarAlta(catalogo, rango) {
        reserva = catalogo;
        const sedes = catalogo.sucursalesOrden;
        [nueva.sucursal_id, disp.sucursal_id].forEach((select) => {
            if (sedes.length > 1) {
                select.appendChild(opcion('', 'Elegí una sede'));
            }
            poblarSucursales(select, sedes);
            // A receptionist only has their own branch: nothing to choose.
            select.closest('.campo').hidden = sedes.length <= 1;
        });
        nueva.paciente_id.replaceChildren(opcion('', 'Elegí un paciente'), ...catalogo.pacientes.map((p) => opcion(String(p.id), p.etiqueta)));
        nueva.estudio.appendChild(opcion('', 'Elegí un estudio'));
        disp.estudio.appendChild(opcion('', 'Todos'));
        poblarEstudios(nueva.estudio, catalogo.estudios);
        poblarEstudios(disp.estudio, catalogo.estudios);
        poblarProfesionalesDe(nueva.profesional_id, nueva.sucursal_id.value, '', 'Cualquiera');
        poblarProfesionalesDe(disp.profesional_id, disp.sucursal_id.value, '', 'Todos');
        [nueva.fecha, disp.fecha].forEach((f) => {
            f.setAttribute('min', rango.min);
            f.setAttribute('max', rango.max);
        });
        // Today can't be booked (see rango_reserva), so the list starts at the first bookable day.
        disp.fecha.value = rango.min;
        refrescarHorasNueva();
        refrescarDisponibles();
    }

    btnNueva.addEventListener('click', () => abrirNueva(formNueva.hidden));

    formNueva.addEventListener('change', (evento) => {
        if (sincronizando) {
            return;
        }
        if (evento.target === nueva.sucursal_id || evento.target === nueva.estudio) {
            poblarProfesionalesDe(nueva.profesional_id, nueva.sucursal_id.value, nueva.estudio.value, 'Cualquiera');
        }
        if (evento.target !== nueva.paciente_id && evento.target !== nueva.hora) {
            refrescarHorasNueva();
        }
    });

    formNueva.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        limpiarErroresCampos(formNueva);
        const data = await enviarPost('api/turnos.php', { accion: 'crear', ...Object.fromEntries(new FormData(formNueva)) });
        if (!data || !data.ok) {
            const sueltos = data && data.errores ? mostrarErroresCampos(formNueva, data.errores) : [textoErrores(data)];
            mostrarMensaje(sueltos.length ? sueltos.join(' ') : 'Revisá los datos de la cita.', true);
            if (data && data.errores && data.errores.hora) {
                refrescarHorasNueva();
                refrescarDisponibles();
            }
            return;
        }
        mostrarMensaje(`Cita ${data.codigo} creada y confirmada.`, false);
        limpiarNueva();
        cargar();
        refrescarDisponibles();
    });

    formDisp.addEventListener('change', (evento) => {
        if (evento.target === disp.sucursal_id || evento.target === disp.estudio) {
            poblarProfesionalesDe(disp.profesional_id, disp.sucursal_id.value, disp.estudio.value, 'Todos');
        }
    });
    formDisp.addEventListener('submit', (evento) => {
        evento.preventDefault();
        refrescarDisponibles();
    });
    filtrarAlCambiar(formDisp);

    function poblarFiltros(data) {
        if (!primeraCarga) {
            return;
        }
        primeraCarga = false;
        poblarSucursales(selectSucursal, data.sucursales.map((s) => ({ id: s.id, nombre: s.nombre })));
        // A receptionist only gets their own branch: nothing to choose, so no Sede filter.
        if (data.sucursales.length <= 1) {
            selectSucursal.closest('.campo').hidden = true;
        }
        data.profesionales.forEach((p) => {
            const o = document.createElement('option');
            o.value = String(p.id);
            o.textContent = p.label;
            selectProfesional.appendChild(o);
        });
        formFiltros.elements.desde.value = data.desde;
        formFiltros.elements.hasta.value = data.hasta;
        iniciarAlta(data.reserva, data.rangoReserva);
    }

    async function cargar() {
        const params = new URLSearchParams();
        new FormData(formFiltros).forEach((valor, clave) => {
            if (String(valor).trim() !== '') {
                params.set(clave, String(valor).trim());
            }
        });
        // After the first load Desde/Hasta always go, empty included (cleared = that end of the range open).
        if (!primeraCarga) {
            params.set('desde', formFiltros.elements.desde.value);
            params.set('hasta', formFiltros.elements.hasta.value);
        } else {
            params.set('catalogo', '1');
        }
        const data = await apiJson('api/turnos.php?' + params.toString());
        if (!data || !data.citas) {
            mostrarMensaje(textoErrores(data), true);
            return;
        }
        poblarFiltros(data);
        // Basic report: totals per state for the current filters (the Estado filter doesn't narrow them).
        const t = data.totales;
        document.getElementById('turnos-totales').textContent = `Total: ${t.total} · Pendientes: ${t.pendiente} · `
            + `Confirmadas: ${t.confirmada} · Realizadas: ${t.realizada} · Rechazadas: ${t.rechazada} · Canceladas: ${t.cancelada}`;
        avisoTruncado.hidden = !data.truncado;
        if (!data.citas.length) {
            lista.replaceChildren(parrafo('No hay citas para esos filtros.'));
            return;
        }
        lista.replaceChildren(tabla(
            ['Código', 'Fecha', 'Hora', 'Paciente', 'Estudio', 'Sede', 'Profesional', 'Estado', 'Acciones'],
            data.citas.map((cita) => renderFila(cita, data))
        ));
    }

    formFiltros.addEventListener('submit', (evento) => {
        evento.preventDefault();
        mensajeError.hidden = true;
        cargar();
    });
    filtrarAlCambiar(formFiltros);

    // Código: only digits can be typed; the "CI-" prefix is added automatically (pasting "CI-000123" works too).
    const inputCodigo = document.getElementById('filtro-codigo');
    inputCodigo.inputMode = 'numeric';
    inputCodigo.addEventListener('input', () => {
        const digitos = inputCodigo.value.replace(/\D/g, '').slice(0, 9);
        inputCodigo.value = digitos ? 'CI-' + digitos : '';
    });

    async function init() {
        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }
        cargar();
    }

    init();
})();
