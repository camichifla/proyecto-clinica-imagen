(() => {
    const calendario = document.getElementById('agenda-calendario');
    if (!calendario) {
        return;
    }

    const formFiltro = document.querySelector('.form-buscar-paciente');
    const selectSucursal = formFiltro ? document.getElementById('sucursal_id') : null;

    const NOMBRES_DIA_SEMANA_COMPLETO = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    const NOMBRES_DIA_SEMANA = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    const MAX_CITAS_VISIBLES_MES = 3;

    function urlAgenda(vista, fecha, sucursalId) {
        const url = new URL(window.location.href);
        url.searchParams.set('vista', vista);
        url.searchParams.set('fecha', fecha);
        if (sucursalId) {
            url.searchParams.set('sucursal_id', sucursalId);
        } else {
            url.searchParams.delete('sucursal_id');
        }
        return url;
    }

    function enlace(texto, href, clase) {
        const a = document.createElement('a');
        a.href = href;
        a.className = clase;
        a.textContent = texto;
        return a;
    }

    function renderControles(data) {
        const controles = document.createElement('div');
        controles.className = 'agenda-controles';

        const toggle = document.createElement('div');
        toggle.className = 'agenda-toggle';
        toggle.setAttribute('role', 'group');
        toggle.setAttribute('aria-label', 'Vista de agenda');
        toggle.appendChild(enlace('Mes', urlAgenda('mes', data.fecha, data.sucursalId), 'agenda-toggle-opcion' + (data.vista === 'mes' ? ' agenda-toggle-activa' : '')));
        toggle.appendChild(enlace('Semana', urlAgenda('semana', data.fecha, data.sucursalId), 'agenda-toggle-opcion' + (data.vista === 'semana' ? ' agenda-toggle-activa' : '')));
        toggle.appendChild(enlace('Día', urlAgenda('dia', data.fecha, data.sucursalId), 'agenda-toggle-opcion' + (data.vista === 'dia' ? ' agenda-toggle-activa' : '')));

        const nav = document.createElement('div');
        nav.className = 'agenda-nav';
        const prev = enlace('‹', urlAgenda(data.vista, data.fechaAnterior, data.sucursalId), 'calendario-nav');
        prev.setAttribute('aria-label', 'Anterior');
        const titulo = document.createElement('span');
        titulo.className = 'agenda-nav-titulo';
        titulo.textContent = data.tituloRango;
        const next = enlace('›', urlAgenda(data.vista, data.fechaSiguiente, data.sucursalId), 'calendario-nav');
        next.setAttribute('aria-label', 'Siguiente');
        const hoy = enlace('Hoy', urlAgenda(data.vista, data.hoy, data.sucursalId), 'btn-secundario agenda-hoy');
        nav.append(prev, titulo, next, hoy);

        controles.append(toggle, nav);
        calendario.appendChild(controles);
    }

    function renderPillCita(cita) {
        const span = document.createElement('span');
        span.className = 'agenda-cita-pill estado-' + cita.estado;
        span.textContent = cita.hora + ' ' + cita.pacienteCorto;
        return span;
    }

    function renderMes(data) {
        const diasSemana = document.createElement('div');
        diasSemana.className = 'agenda-mes-dias-semana';
        NOMBRES_DIA_SEMANA.forEach((nombre) => {
            const span = document.createElement('span');
            span.textContent = nombre;
            diasSemana.appendChild(span);
        });
        calendario.appendChild(diasSemana);

        const grid = document.createElement('div');
        grid.className = 'agenda-mes-grid';

        // Details of the selected day, shown under the month grid.
        const detalle = document.createElement('section');
        detalle.className = 'agenda-mes-detalle';
        detalle.id = 'agenda-mes-detalle';
        detalle.hidden = true;
        let seleccionado = null;

        data.dias.forEach((dia) => {
            const divDia = document.createElement('div');
            divDia.className = 'agenda-dia-mes' + (!dia.esMesActual ? ' agenda-dia-fuera-mes' : '') + (dia.esHoy ? ' agenda-dia-hoy' : '');
            divDia.appendChild(enlace(String(dia.dia), urlAgenda('semana', dia.fecha, data.sucursalId), 'agenda-dia-numero'));

            dia.citas.slice(0, MAX_CITAS_VISIBLES_MES).forEach((cita) => divDia.appendChild(renderPillCita(cita)));

            if (dia.citas.length > MAX_CITAS_VISIBLES_MES) {
                const mas = document.createElement('span');
                mas.className = 'agenda-cita-mas';
                mas.textContent = '+' + (dia.citas.length - MAX_CITAS_VISIBLES_MES) + ' más';
                divDia.appendChild(mas);
            }

            if (dia.citas.length) {
                // The day cell toggles its details; the day number still opens the week view.
                divDia.classList.add('agenda-dia-con-citas');
                divDia.tabIndex = 0;
                divDia.setAttribute('role', 'button');
                divDia.setAttribute('aria-expanded', 'false');
                divDia.setAttribute('aria-controls', detalle.id);
                divDia.setAttribute('aria-label', dia.dia + ': ' + dia.citas.length + (dia.citas.length === 1 ? ' cita' : ' citas'));
                const alternar = () => {
                    const abrir = seleccionado !== divDia;
                    if (seleccionado) {
                        seleccionado.setAttribute('aria-expanded', 'false');
                    }
                    seleccionado = abrir ? divDia : null;
                    divDia.setAttribute('aria-expanded', String(abrir));
                    detalle.hidden = !abrir;
                    if (abrir) {
                        renderDetalleDia(detalle, dia);
                        detalle.scrollIntoView({ block: 'nearest' });
                    }
                };
                divDia.addEventListener('click', (evento) => {
                    if (!evento.target.closest('a')) {
                        alternar();
                    }
                });
                divDia.addEventListener('keydown', (evento) => {
                    if ((evento.key === 'Enter' || evento.key === ' ') && evento.target === divDia) {
                        evento.preventDefault();
                        alternar();
                    }
                });
            }

            grid.appendChild(divDia);
        });

        calendario.append(grid, detalle);
    }

    function renderDetalleDia(detalle, dia) {
        const titulo = document.createElement('h2');
        titulo.textContent = NOMBRES_DIA_SEMANA_COMPLETO[dia.diaSemanaIso - 1] + ' ' + dia.dia;
        const lista = document.createElement('div');
        lista.className = 'agenda-mes-detalle-lista';
        dia.citas.forEach((cita) => lista.appendChild(renderTarjetaCita(cita)));
        detalle.replaceChildren(titulo, lista);
    }

    // Month details: static card, everything visible. Week view (plegable): a button that
    // shows only time and study until it is opened.
    function renderTarjetaCita(cita, plegable) {
        const tarjeta = document.createElement(plegable ? 'button' : 'article');
        tarjeta.className = 'agenda-cita-card estado-' + cita.estado;
        if (plegable) {
            tarjeta.type = 'button';
            tarjeta.setAttribute('aria-expanded', 'false');
            tarjeta.addEventListener('click', () => {
                tarjeta.setAttribute('aria-expanded', String(tarjeta.getAttribute('aria-expanded') !== 'true'));
            });
        }

        const resumen = document.createElement('span');
        resumen.className = 'agenda-cita-resumen';
        const hora = document.createElement('strong');
        hora.textContent = cita.hora;
        const estudio = document.createElement('span');
        estudio.textContent = cita.estudioLabel;
        resumen.append(hora, estudio);

        const detalle = document.createElement('span');
        detalle.className = 'agenda-cita-detalle';
        [cita.pacienteLabel, cita.profesionalLabel, cita.sucursalNombre || '-'].forEach((texto) => {
            const span = document.createElement('span');
            span.textContent = texto;
            detalle.appendChild(span);
        });

        tarjeta.append(resumen, detalle);
        return tarjeta;
    }

    function renderSemana(data) {
        const grid = document.createElement('div');
        grid.className = 'agenda-semana-grid' + (data.vista === 'dia' ? ' agenda-dia-grid' : '');

        data.dias.forEach((dia) => {
            const divDia = document.createElement('div');
            divDia.className = 'agenda-dia-semana' + (dia.esHoy ? ' agenda-dia-hoy' : '');

            const header = document.createElement('div');
            header.className = 'agenda-dia-semana-header';
            header.append(NOMBRES_DIA_SEMANA_COMPLETO[dia.diaSemanaIso - 1] + ' ');
            const strong = document.createElement('strong');
            strong.textContent = String(dia.dia);
            header.appendChild(strong);
            divDia.appendChild(header);

            if (!dia.citas.length) {
                const vacio = document.createElement('p');
                vacio.className = 'agenda-dia-semana-vacio';
                vacio.textContent = 'Sin citas';
                divDia.appendChild(vacio);
            } else {
                dia.citas.forEach((cita) => divDia.appendChild(renderTarjetaCita(cita, data.vista !== 'dia')));
            }

            grid.appendChild(divDia);
        });

        calendario.appendChild(grid);
    }

    function renderOpcionesSucursal(sucursales, sucursalId) {
        selectSucursal.innerHTML = '';
        const todas = document.createElement('option');
        todas.value = '';
        todas.textContent = 'Todas';
        selectSucursal.appendChild(todas);
        sucursales.forEach((sucursal) => {
            const opcion = document.createElement('option');
            opcion.value = String(sucursal.id);
            opcion.textContent = sucursal.nombre;
            selectSucursal.appendChild(opcion);
        });
        selectSucursal.value = sucursalId || '';
    }

    function render(data) {
        calendario.innerHTML = '';
        renderControles(data);
        if (data.vista === 'mes') {
            renderMes(data);
        } else {
            renderSemana(data); // 'semana' and 'dia' share it: the day view is a one-column week
        }
        if (selectSucursal) {
            // Only administrators and receptionists get branches; the professional only sees their own agenda.
            // A receptionist only gets their own branch: nothing to choose, so no Sede filter.
            formFiltro.hidden = data.sucursales.length <= 1;
            renderOpcionesSucursal(data.sucursales, data.sucursalId);
        }
    }

    async function cargar(vista, fecha, sucursalId, agregarHistorial) {
        const params = new URLSearchParams({ vista: vista || 'mes', fecha: fecha || '' });
        if (sucursalId) {
            params.set('sucursal_id', sucursalId);
        }
        const data = await apiJson('api/agenda.php?' + params.toString());
        if (!data) {
            return;
        }
        render(data);
        if (agregarHistorial) {
            history.pushState(null, '', urlAgenda(data.vista, data.fecha, data.sucursalId));
        }
    }

    calendario.addEventListener('click', (evento) => {
        const link = evento.target.closest('a[href]');
        if (!link) {
            return;
        }
        evento.preventDefault();
        const url = new URL(link.href);
        cargar(url.searchParams.get('vista'), url.searchParams.get('fecha'), url.searchParams.get('sucursal_id'), true);
    });

    window.addEventListener('popstate', () => {
        const params = new URL(window.location.href).searchParams;
        cargar(params.get('vista'), params.get('fecha'), params.get('sucursal_id'), false);
    });

    if (formFiltro) {
        formFiltro.addEventListener('submit', (evento) => {
            evento.preventDefault();
            const params = new URL(window.location.href).searchParams;
            cargar(params.get('vista'), params.get('fecha'), selectSucursal.value, true);
        });
        filtrarAlCambiar(formFiltro);
    }

    const inicial = new URL(window.location.href).searchParams;
    cargar(inicial.get('vista'), inicial.get('fecha'), inicial.get('sucursal_id'), false);
})();
