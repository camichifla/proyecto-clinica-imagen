// Helpers compartidos por las paginas autenticadas (globals de script clasico).
// Las paginas que los usan cargan este archivo antes de su propio script.

// fetch + JSON. Devuelve el JSON parseado, null si no hubo conexion, o
// { ok: false } si la respuesta no era JSON. Ante sesion vencida (401, o 403
// en lecturas, o redireccion al login) navega a login.html y NO resuelve: la
// pagina se esta yendo y el llamador no debe seguir.
async function apiJson(url, opts = {}) {
    let respuesta;
    try {
        respuesta = await fetch(url, opts);
    } catch {
        return null;
    }
    const esLectura = !opts.body;
    if (respuesta.status === 401 || respuesta.redirected || (esLectura && respuesta.status === 403)) {
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

function celda(etiqueta, texto) {
    const td = document.createElement('td');
    td.dataset.label = etiqueta;
    td.textContent = texto;
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
        mensaje.textContent = inputBuscar.dataset.mensajeSinPacientes || 'Todavia no hay pacientes registrados.';
        inputBuscar.closest('.campo').appendChild(mensaje);
    }
}

const ETIQUETAS_ESTUDIO = { placa: 'Placa', radiografia: 'Radiografia' };

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

function poblarProfesionales(select, profesionales, seleccionado = '') {
    profesionales.forEach((profesional) => {
        const opcion = document.createElement('option');
        opcion.value = String(profesional.id);
        opcion.dataset.especializaciones = JSON.stringify(profesional.especializaciones);
        const etiquetas = profesional.especializaciones.map((e) => ETIQUETAS_ESTUDIO[e] || e).join(', ');
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
