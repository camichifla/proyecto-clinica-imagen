async function apiJson(url, opts = {}) {
    let respuesta;
    try {
        respuesta = await fetch(url, opts);
    } catch {
        return null;
    }
    const esLectura = !opts.body;
    if (!opts.publica && esLectura && respuesta.status === 403) {
        window.location.replace('index.html');
        return new Promise(() => {});
    }
    if (!opts.publica && (respuesta.status === 401 || respuesta.redirected)) {
        const expirado = respuesta.status === 401 && (await respuesta.json().catch(() => ({}))).expirado;
        window.location.href = expirado ? 'login.html?expirado=1' : 'login.html';
        return new Promise(() => {});
    }
    try {
        return await respuesta.json();
    } catch {
        return { ok: false };
    }
}

const ERROR_CONEXION = 'No se pudo conectar. Intentá nuevamente.';

async function enviarPost(url, campos) {
    const sesion = await window.sesionLista;
    return apiJson(url, { method: 'POST', body: new URLSearchParams({ csrf: sesion.csrf, ...campos }) });
}

function mostrarMensajeEn(ok, error, texto, esError) {
    const el = esError ? error : ok;
    const otro = esError ? ok : error;
    otro.hidden = true;
    el.textContent = texto;
    el.hidden = false;
}

function crearMostrarMensaje(ok, error) {
    return (texto, esError) => mostrarMensajeEn(ok, error, texto, esError);
}

function textoErrores(data) {
    if (data && data.errores) {
        return Object.values(data.errores).join(' ');
    }
    return (data && data.error) || ERROR_CONEXION;
}

function cargarScript(src) {
    return new Promise((resolve) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = resolve;
        document.body.appendChild(script);
    });
}

async function cargarScripts(...srcs) {
    for (const src of srcs) {
        await cargarScript(src);
    }
}

function confirmar(mensaje, { peligro = false } = {}) {
    return new Promise((resolve) => {
        const dialogo = document.createElement('dialog');
        dialogo.className = 'dialogo-confirmar';
        const acciones = document.createElement('form');
        acciones.method = 'dialog';
        acciones.className = 'acciones-form';
        const boton = (valor, texto, clase) => {
            const b = document.createElement('button');
            b.value = valor;
            b.className = clase;
            b.textContent = texto;
            return b;
        };
        const cancelar = boton('no', 'Cancelar', 'btn-secundario');
        cancelar.autofocus = true;
        acciones.append(cancelar, boton('si', 'Aceptar', peligro ? 'btn-peligro' : 'btn-primario'));
        dialogo.append(parrafo(mensaje), acciones);
        dialogo.addEventListener('close', () => {
            dialogo.remove();
            resolve(dialogo.returnValue === 'si');
        });
        document.body.appendChild(dialogo);
        dialogo.showModal();
    });
}

function normalizar(texto) {
    return String(texto ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();
}

function coincidePalabras(busqueda, campos) {
    const texto = normalizar(campos.join(' '));
    return normalizar(busqueda).split(/\s+/).filter(Boolean).every((palabra) => texto.includes(palabra));
}

const pad = (n) => String(n).padStart(2, '0');
function parseISO(s) {
    const [anio, mes, dia] = s.split('-').map(Number);
    return new Date(anio, mes - 1, dia);
}
const aISO = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());

function parrafo(texto) {
    const p = document.createElement('p');
    p.textContent = texto;
    return p;
}

function vacio(texto) {
    const p = parrafo(texto);
    p.className = 'estado-vacio';
    return p;
}

function fechaHora(texto) {
    const m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2})(?::\d{2})?)?$/.exec(String(texto ?? ''));
    return m ? `${m[3]}/${m[2]}/${m[1]}` + (m[4] ? ' ' + m[4] : '') : texto;
}

function mostrarAviso(clave, texto) {
    const aviso = document.querySelector(`[data-aviso="${clave}"]`);
    if (!aviso) {
        return;
    }
    if (texto) {
        aviso.textContent = texto;
    }
    aviso.hidden = false;
}

function celda(etiqueta, texto) {
    const td = document.createElement('td');
    td.dataset.label = etiqueta;
    td.textContent = texto;
    return td;
}

function limpiarErroresCampos(form) {
    form.querySelectorAll('.campo-error[data-dinamico]').forEach((p) => p.remove());
    form.querySelectorAll('[data-error-id]').forEach(desvincularError);
}

let contadorErrores = 0;
function vincularError(p, control) {
    if (!control) {
        return;
    }
    p.id = p.id || 'error-' + (++contadorErrores);
    const ids = (control.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
    if (!ids.includes(p.id)) {
        control.setAttribute('aria-describedby', [...ids, p.id].join(' '));
    }
    control.dataset.errorId = p.id;
}

function desvincularError(control) {
    const ids = (control.getAttribute('aria-describedby') || '').split(' ')
        .filter((id) => id && id !== control.dataset.errorId);
    if (ids.length) {
        control.setAttribute('aria-describedby', ids.join(' '));
    } else {
        control.removeAttribute('aria-describedby');
    }
    control.removeAttribute('data-error-id');
}

function mostrarErroresCampos(form, errores) {
    limpiarErroresCampos(form);
    const sueltos = [];
    Object.entries(errores).forEach(([campo, texto]) => {
        const destino = form.querySelector(`[name="${campo}"], [name="${campo}[]"], #campo-${campo}`);
        const contenedor = destino && destino.closest('.campo, .check-grid');
        if (!contenedor) {
            sueltos.push(texto);
            return;
        }
        const p = document.createElement('p');
        p.className = 'campo-error';
        p.dataset.dinamico = '';
        p.textContent = texto;
        contenedor.appendChild(p);
        vincularError(p, destino.matches('input, select, textarea') ? destino : null);
    });
    return sueltos;
}

function limpiarErrorDe(evento) {
    const contenedor = evento.target.closest && evento.target.closest('.campo');
    if (!contenedor || !contenedor.querySelector('.campo-error[data-dinamico], [aria-invalid]')) {
        return;
    }
    contenedor.querySelectorAll('.campo-error[data-dinamico]').forEach((p) => p.remove());
    contenedor.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
    contenedor.querySelectorAll('[data-error-id]').forEach(desvincularError);
}
let tabulando = false;
document.addEventListener('keydown', (evento) => {
    tabulando = evento.key === 'Tab';
});
document.addEventListener('focusin', (evento) => {
    if (tabulando) {
        limpiarErrorDe(evento);
    }
    tabulando = false;
});
['pointerdown', 'input', 'change'].forEach((tipo) => document.addEventListener(tipo, limpiarErrorDe));

function celdaEstado(estado, etiqueta, columna = 'Estado') {
    const td = document.createElement('td');
    td.dataset.label = columna;
    const badge = document.createElement('span');
    badge.className = 'estado-badge estado-' + estado;
    badge.textContent = etiqueta;
    td.appendChild(badge);
    return td;
}

const observadorTablas = new ResizeObserver((entradas) => {
    entradas.forEach(({ target: scroll }) => {
        const ancho = scroll.clientWidth;
        if (ancho === Number(scroll.dataset.ancho)) {
            return;
        }
        scroll.dataset.ancho = String(ancho);
        scroll.classList.remove('tabla-tarjetas');
        scroll.classList.toggle('tabla-tarjetas', scroll.scrollWidth > ancho + 1);
    });
});

function filtrarAlCambiar(form, demora = 400) {
    const esTexto = (el) => el.matches('input[type="text"], input[type="search"]');
    const esBuscador = (el) => el.matches('[role="combobox"]');
    let espera;
    form.addEventListener('input', (evento) => {
        if (esTexto(evento.target) && !esBuscador(evento.target)) {
            clearTimeout(espera);
            espera = setTimeout(() => form.requestSubmit(), demora);
        }
    });
    form.addEventListener('change', (evento) => {
        if (!esTexto(evento.target) && !esBuscador(evento.target)) {
            clearTimeout(espera);
            form.requestSubmit();
        }
    });
}

const CHEVRON_DER = '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>';
const CHEVRON_IZQ = '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>';

function tabla(encabezados, filas, opciones = {}) {
    const scroll = document.createElement('div');
    scroll.className = 'tabla-scroll';
    const table = document.createElement('table');
    table.className = 'tabla-datos';

    const thead = document.createElement('thead');
    const trHead = document.createElement('tr');
    encabezados.forEach((texto) => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.textContent = texto;
        trHead.appendChild(th);
    });
    thead.appendChild(trHead);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    table.appendChild(tbody);
    scroll.appendChild(table);
    observadorTablas.observe(scroll);

    filas.forEach((fila) => tbody.appendChild(fila));
    const porPagina = opciones.porPagina ?? 5;
    if (porPagina <= 0 || filas.length <= porPagina) {
        return scroll;
    }

    const paginas = Math.ceil(filas.length / porPagina);
    let actual = 0;
    const contenedor = document.createElement('div');
    contenedor.appendChild(scroll);

    const barra = document.createElement('div');
    barra.className = 'paginacion';
    const anterior = document.createElement('button');
    anterior.type = 'button';
    anterior.className = 'paginacion-flecha';
    anterior.innerHTML = CHEVRON_IZQ;
    anterior.setAttribute('aria-label', 'Página anterior');
    const indicador = document.createElement('span');
    indicador.className = 'paginacion-info';
    const siguiente = document.createElement('button');
    siguiente.type = 'button';
    siguiente.className = 'paginacion-flecha';
    siguiente.innerHTML = CHEVRON_DER;
    siguiente.setAttribute('aria-label', 'Página siguiente');
    barra.append(anterior, indicador, siguiente);
    contenedor.appendChild(barra);

    const mostrar = () => {
        filas.forEach((fila, i) => { fila.classList.toggle('pagina-oculta', i < actual * porPagina || i >= (actual + 1) * porPagina); });
        indicador.textContent = (actual + 1) + '/' + paginas;
        anterior.classList.toggle('oculto', actual === 0);
        siguiente.classList.toggle('oculto', actual === paginas - 1);
    };
    anterior.addEventListener('click', () => { if (actual > 0) { actual--; mostrar(); } });
    siguiente.addEventListener('click', () => { if (actual < paginas - 1) { actual++; mostrar(); } });
    mostrar();
    return contenedor;
}

function poblarPacientes(select, pacientes) {
    select.replaceChildren(
        new Option('Elegí un paciente', ''),
        ...pacientes.map((p) => new Option(`${p.apellido}, ${p.nombre} (${p.ci})`, String(p.id)))
    );
    if (!pacientes.length && select.dataset.mensajeSinPacientes) {
        const mensaje = document.createElement('p');
        mensaje.className = 'campo-error';
        mensaje.textContent = select.dataset.mensajeSinPacientes;
        select.closest('.campo').appendChild(mensaje);
    }
}

function poblarEstudios(select, estudios) {
    estudios.forEach((estudio) => {
        const opcion = document.createElement('option');
        opcion.value = estudio.clave;
        opcion.textContent = estudio.etiqueta;
        select.appendChild(opcion);
    });
}

function poblarSucursales(select, sucursalesOrden, seleccionada = '') {
    sucursalesOrden.forEach((sucursal) => {
        const opcion = document.createElement('option');
        opcion.value = String(sucursal.id);
        opcion.textContent = sucursal.direccion ? sucursal.nombre + ' — ' + sucursal.direccion : sucursal.nombre;
        if (String(sucursal.id) === seleccionada) {
            opcion.selected = true;
        }
        select.appendChild(opcion);
    });
}

function poblarProfesionales(select, profesionales, seleccionado = '', etiquetasEstudio = {}, soloNombre = false) {
    profesionales.forEach((profesional) => {
        const opcion = document.createElement('option');
        opcion.value = String(profesional.id);
        opcion.dataset.especializaciones = JSON.stringify(profesional.especializaciones);
        const etiquetas = profesional.especializaciones.map((e) => etiquetasEstudio[e] || e).join(', ');
        opcion.textContent = soloNombre
            ? profesional.apellido + ', ' + profesional.nombre
            : profesional.apellido + ', ' + profesional.nombre + ' — ' + etiquetas;
        if (String(profesional.id) === seleccionado) {
            opcion.selected = true;
        }
        select.appendChild(opcion);
    });
    if (!profesionales.length) {
        document.querySelector('[data-mensaje-sin-profesionales]').hidden = false;
    }
}

const DIAS_SEMANA = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

function selectorHora(valor, etiqueta) {
    const select = document.createElement('select');
    select.className = 'selector-combo';
    select.setAttribute('aria-label', etiqueta);
    const horas = [];
    for (let minutos = 0; minutos < 24 * 60; minutos += 15) {
        horas.push(String(Math.floor(minutos / 60)).padStart(2, '0') + ':' + String(minutos % 60).padStart(2, '0'));
    }
    if (valor && !horas.includes(valor)) {
        horas.push(valor);
        horas.sort();
    }
    horas.forEach((hora) => select.appendChild(new Option(hora, hora)));
    select.value = valor;
    return select;
}

function filaHorario(turno) {
    const fila = document.createElement('div');
    fila.className = 'fila-horario';

    const dia = document.createElement('select');
    dia.className = 'selector-combo';
    dia.setAttribute('aria-label', 'Día');
    DIAS_SEMANA.forEach((nombre, i) => {
        const opcion = document.createElement('option');
        opcion.value = String(i + 1);
        opcion.textContent = nombre;
        dia.appendChild(opcion);
    });
    dia.value = String(turno.dia);

    const apertura = selectorHora(turno.apertura, 'Apertura');
    const cierre = selectorHora(turno.cierre, 'Cierre');

    const quitar = document.createElement('button');
    quitar.type = 'button';
    quitar.className = 'btn-secundario';
    quitar.textContent = 'Quitar';
    quitar.addEventListener('click', () => fila.remove());

    fila.append(dia, apertura, cierre, quitar);
    return fila;
}

function turnosDeContenedor(contenedor) {
    return Array.from(contenedor.querySelectorAll('.fila-horario')).map((fila) => {
        const [dia, apertura, cierre] = fila.querySelectorAll('select');
        return { dia: dia.value, apertura: apertura.value, cierre: cierre.value };
    });
}

(() => {
    function campoError(form, campo) {
        if (campo === 'general') {
            return form.closest('section').querySelector('[data-general]');
        }
        const input = form.querySelector(`[name="${campo}"], [name="${campo}[]"]`);
        return input ? input.closest('.campo') : null;
    }

    function mostrarError(form, campo, texto) {
        const contenedor = campoError(form, campo);
        if (!contenedor) {
            return;
        }
        let p = contenedor.hasAttribute('data-general')
            ? contenedor
            : contenedor.querySelector(':scope > .campo-error[data-dinamico]');
        if (!p) {
            p = document.createElement('p');
            p.className = 'campo-error';
            p.dataset.dinamico = '';
            contenedor.appendChild(p);
        }
        p.hidden = false;
        p.textContent = texto;
        const input = contenedor.matches('.campo') ? contenedor.querySelector('[name]') : null;
        if (input) {
            input.setAttribute('aria-invalid', 'true');
            vincularError(p, input);
        }
    }

    function limpiarErrores(form) {
        const seccion = form.closest('section');
        seccion.querySelectorAll('.campo-error[data-general]').forEach((p) => {
            p.hidden = true;
            p.textContent = '';
        });
        seccion.querySelectorAll('.campo-error[data-dinamico]').forEach((p) => p.remove());
        form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
        form.querySelectorAll('[data-error-id]').forEach(desvincularError);
    }

    function faltanObligatorios(form) {
        const vacios = Array.from(form.querySelectorAll('[required]'))
            .filter((el) => !el.disabled && el.type !== 'checkbox' && el.type !== 'radio' && !String(el.value).trim());
        vacios.forEach((el) => mostrarError(form, el.name.replace(/\[\]$/, ''), 'Este campo es obligatorio.'));
        if (vacios.length) {
            const campo = vacios[0].closest('.campo');
            const destino = campo && campo.querySelector('input:not([type="hidden"]), textarea, button');
            (destino || vacios[0]).focus();
        }
        return vacios.length > 0;
    }

    async function enviar(form) {
        limpiarErrores(form);
        if (faltanObligatorios(form)) {
            return;
        }
        const url = form.dataset.api;

        const data = await apiJson(url, { method: 'POST', body: new FormData(form) });
        if (!data) {
            mostrarError(form, 'general', ERROR_CONEXION);
            return;
        }

        if (data.errores) {
            Object.entries(data.errores).forEach(([campo, texto]) => mostrarError(form, campo, texto));
            form.dispatchEvent(new CustomEvent('form-api:respuesta', { detail: data }));
            return;
        }

        if (!data.ok) {
            mostrarError(form, 'general', data.error || 'Ocurrió un error inesperado.');
            form.dispatchEvent(new CustomEvent('form-api:respuesta', { detail: data }));
            return;
        }

        if (data.redirect) {
            window.location.href = data.redirect;
            return;
        }

        if (form.dataset.exito) {
            const panel = document.querySelector(form.dataset.exito);
            if (panel) {
                panel.hidden = false;
                form.hidden = true;
            }
        }

        form.dispatchEvent(new CustomEvent('form-api:respuesta', { detail: data }));
    }

    document.querySelectorAll('form[data-api]').forEach((form) => {
        form.addEventListener('submit', (evento) => {
            evento.preventDefault();
            enviar(form);
        });
    });
})();
