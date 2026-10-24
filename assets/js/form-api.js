// Helpers compartidos por las paginas autenticadas (globals de script clasico).
// Las paginas que los usan cargan este archivo antes de su propio script.

// fetch + JSON. Devuelve el JSON parseado, null si no hubo conexion, o
// { ok: false } si la respuesta no era JSON. Ante sesion vencida (401 o
// redireccion al login) navega a login.html; ante un 403 en lecturas (rol sin
// permiso) navega a index.html. En ambos casos NO resuelve: la pagina se esta
// yendo y el llamador no debe seguir.
async function apiJson(url, opts = {}) {
    let respuesta;
    try {
        respuesta = await fetch(url, opts);
    } catch {
        return null;
    }
    const esLectura = !opts.body;
    if (esLectura && respuesta.status === 403) {
        window.location.replace('index.html');
        return new Promise(() => {});
    }
    if (respuesta.status === 401 || respuesta.redirected) {
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

function parrafo(texto) {
    const p = document.createElement('p');
    p.textContent = texto;
    return p;
}

function celda(etiqueta, texto) {
    const td = document.createElement('td');
    td.dataset.label = etiqueta;
    td.textContent = texto;
    return td;
}

// Errores de validacion { campo: mensaje } bajo el .campo del input con ese
// name (o bajo #campo-<campo>). Devuelve los mensajes que no encontraron donde
// mostrarse, para que el llamador los muestre como aviso general.
function limpiarErroresCampos(form) {
    form.querySelectorAll('.campo-error[data-dinamico]').forEach((p) => p.remove());
    form.querySelectorAll('[data-error-id]').forEach(desvincularError);
}

// Ata el mensaje de error al control para lectores de pantalla, sin pisar
// la ayuda de formato que el control ya tenga en aria-describedby.
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

function celdaEstado(estado, etiqueta) {
    const td = document.createElement('td');
    td.dataset.label = 'Estado';
    const badge = document.createElement('span');
    badge.className = 'estado-badge estado-' + estado;
    badge.textContent = etiqueta;
    td.appendChild(badge);
    return td;
}

// Devuelve el contenedor .tabla-scroll con una <table class="tabla-datos">:
// `encabezados` son los textos de <th>, `filas` ya son elementos <tr>.
function tabla(encabezados, filas) {
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
    filas.forEach((fila) => tbody.appendChild(fila));
    table.appendChild(tbody);

    scroll.appendChild(table);
    return scroll;
}

function poblarDatalist(datalist, filas, etiquetaFn) {
    datalist.innerHTML = '';
    filas.forEach((fila) => {
        const opcion = document.createElement('option');
        opcion.dataset.id = fila.id;
        opcion.value = etiquetaFn(fila);
        datalist.appendChild(opcion);
    });
}

function poblarPacientes(datalist, pacientes, inputBuscar) {
    poblarDatalist(datalist, pacientes, (p) => `${p.apellido}, ${p.nombre} (CI ${p.ci})`);
    if (!pacientes.length && inputBuscar) {
        const mensaje = document.createElement('p');
        mensaje.className = 'campo-error';
        mensaje.textContent = inputBuscar.dataset.mensajeSinPacientes || 'Todavía no hay pacientes registrados.';
        inputBuscar.closest('.campo').appendChild(mensaje);
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

function poblarProfesionales(select, profesionales, seleccionado = '', etiquetasEstudio = {}) {
    profesionales.forEach((profesional) => {
        const opcion = document.createElement('option');
        opcion.value = String(profesional.id);
        opcion.dataset.especializaciones = JSON.stringify(profesional.especializaciones);
        const etiquetas = profesional.especializaciones.map((e) => etiquetasEstudio[e] || e).join(', ');
        opcion.textContent = profesional.apellido + ', ' + profesional.nombre + ' — ' + etiquetas;
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

function filaHorario(turno) {
    const fila = document.createElement('div');
    fila.className = 'fila-horario';

    const dia = document.createElement('select');
    dia.setAttribute('aria-label', 'Día');
    DIAS_SEMANA.forEach((nombre, i) => {
        const opcion = document.createElement('option');
        opcion.value = String(i + 1);
        opcion.textContent = nombre;
        dia.appendChild(opcion);
    });
    dia.value = String(turno.dia);

    const apertura = document.createElement('input');
    apertura.type = 'time';
    apertura.value = turno.apertura;
    apertura.setAttribute('aria-label', 'Apertura');
    const cierre = document.createElement('input');
    cierre.type = 'time';
    cierre.value = turno.cierre;
    cierre.setAttribute('aria-label', 'Cierre');

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
        const [dia, apertura, cierre] = fila.querySelectorAll('select, input');
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
        // Static notices (sin-pacientes, data-aviso...) are left alone.
        const seccion = form.closest('section');
        seccion.querySelectorAll('.campo-error[data-general]').forEach((p) => {
            p.hidden = true;
            p.textContent = '';
        });
        seccion.querySelectorAll('.campo-error[data-dinamico]').forEach((p) => p.remove());
        form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
        form.querySelectorAll('[data-error-id]').forEach(desvincularError);
    }

    async function enviar(form) {
        limpiarErrores(form);
        const url = form.dataset.api;

        const data = await apiJson(url, { method: 'POST', body: new FormData(form) });
        if (!data) {
            mostrarError(form, 'general', 'No se pudo conectar. Intenta nuevamente.');
            return;
        }

        if (data.errores) {
            Object.entries(data.errores).forEach(([campo, texto]) => mostrarError(form, campo, texto));
            form.dispatchEvent(new CustomEvent('form-api:respuesta', { detail: data }));
            return;
        }

        if (!data.ok) {
            mostrarError(form, 'general', data.error || 'Ocurrio un error inesperado.');
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
