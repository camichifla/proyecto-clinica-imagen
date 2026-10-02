(() => {
    function campoError(form, campo) {
        if (campo === 'general') {
            return form.closest('section').querySelector('[data-general]');
        }
        const input = form.querySelector(`[name="${campo}"]`);
        return input ? input.closest('.campo') : null;
    }

    function mostrarError(form, campo, texto) {
        const contenedor = campoError(form, campo);
        if (!contenedor) {
            return;
        }
        let p = contenedor.querySelector(':scope > .campo-error');
        if (!p) {
            p = document.createElement('p');
            p.className = 'campo-error';
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
        form.closest('section').querySelectorAll('.campo-error').forEach((p) => {
            if (p.hasAttribute('data-general')) {
                p.hidden = true;
                p.textContent = '';
            } else {
                p.remove();
            }
        });
        form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
    }

    async function enviar(form) {
        limpiarErrores(form);
        const url = form.dataset.api;

        let respuesta;
        try {
            respuesta = await fetch(url, { method: 'POST', body: new FormData(form) });
        } catch {
            mostrarError(form, 'general', 'No se pudo conectar. Intenta nuevamente.');
            return;
        }

        if (respuesta.status === 401 || respuesta.redirected) {
            window.location.href = 'login.html';
            return;
        }

        let data;
        try {
            data = await respuesta.json();
        } catch {
            mostrarError(form, 'general', 'Ocurrio un error inesperado.');
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
