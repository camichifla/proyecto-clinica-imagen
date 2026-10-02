(() => {
    const calendario = document.getElementById('agenda-calendario');
    if (!calendario) {
        return;
    }

    const formFiltro = document.querySelector('.form-buscar-paciente');
    const selectSucursal = formFiltro ? document.getElementById('sucursal_id') : null;

    const NOMBRES_DIA_SEMANA_COMPLETO = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];
    const NOMBRES_DIA_SEMANA = ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'];
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

        data.dias.forEach((dia) => {
            const divDia = document.createElement('div');
            divDia.className = 'agenda-dia-mes' + (!dia.esMesActual ? ' agenda-dia-fuera-mes' : '') + (dia.esHoy ? ' agenda-dia-hoy' : '');
            divDia.appendChild(enlace(String(dia.dia), urlAgenda('semana', dia.fecha, data.sucursalId), 'agenda-dia-numero'));

            dia.citas.slice(0, MAX_CITAS_VISIBLES_MES).forEach((cita) => divDia.appendChild(renderPillCita(cita)));

            if (dia.citas.length > MAX_CITAS_VISIBLES_MES) {
                divDia.appendChild(enlace('+' + (dia.citas.length - MAX_CITAS_VISIBLES_MES) + ' mas', urlAgenda('semana', dia.fecha, data.sucursalId), 'agenda-cita-mas'));
            }

            grid.appendChild(divDia);
        });

        calendario.appendChild(grid);
    }

    function renderTarjetaCita(cita) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'agenda-cita-card estado-' + cita.estado;
        boton.setAttribute('aria-expanded', 'false');

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
        const estadoBadge = document.createElement('span');
        estadoBadge.className = 'estado-badge estado-' + cita.estado;
        estadoBadge.textContent = cita.estadoLabel;
        detalle.appendChild(estadoBadge);

        boton.append(resumen, detalle);
        boton.addEventListener('click', () => {
            const expandida = boton.getAttribute('aria-expanded') === 'true';
            boton.setAttribute('aria-expanded', String(!expandida));
        });
        return boton;
    }

    function renderSemana(data) {
        const grid = document.createElement('div');
        grid.className = 'agenda-semana-grid';

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
                dia.citas.forEach((cita) => divDia.appendChild(renderTarjetaCita(cita)));
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
            renderSemana(data);
        }
        if (selectSucursal) {
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
    }

    const inicial = new URL(window.location.href).searchParams;
    cargar(inicial.get('vista'), inicial.get('fecha'), inicial.get('sucursal_id'), false);
})();
