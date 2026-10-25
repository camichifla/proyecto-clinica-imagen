// Calendar date picker for every <input type="date"> on the page (the one first built for "Agendar cita").
// The real input stays as the source of truth: it becomes type=hidden with the same id, name and YYYY-MM-DD value,
// so forms and scripts keep working; picking a day fires 'input' and 'change' on it. Inputs added later by
// scripts are picked up by a MutationObserver. Optional per-input hook (set by the page script):
//   input.diaHabilitado = (fecha) => boolean   // extra disabled days, e.g. days the branch is closed
//   input.refrescarCalendario()                 // re-render after the hook's inputs change
(() => {
    const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const DIAS = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do'];
    const valorNativo = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');

    const pad = (n) => String(n).padStart(2, '0');
    const parseISO = (s) => {
        const [anio, mes, dia] = s.split('-').map(Number);
        return new Date(anio, mes - 1, dia);
    };
    const aISO = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    const aVisible = (d) => pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear();
    const mismoDia = (a, b) => a && b && aISO(a) === aISO(b);
    const mesDe = (d) => new Date(d.getFullYear(), d.getMonth(), 1);

    let contador = 0;

    function mejorar(input) {
        if (input.dataset.selectorFecha) {
            return;
        }
        input.dataset.selectorFecha = '1';
        const id = input.id || 'fecha-' + ++contador;
        const placeholder = input.dataset.placeholder || 'Elegí una fecha';
        const opcional = !input.required;

        const contenedor = document.createElement('div');
        contenedor.className = 'selector-fecha';
        input.before(contenedor);

        const boton = document.createElement('button');
        boton.type = 'button';
        boton.id = id + '-boton';
        boton.className = 'selector-fecha-boton';
        boton.setAttribute('aria-haspopup', 'dialog');
        boton.setAttribute('aria-expanded', 'false');
        const texto = document.createElement('span');
        texto.className = 'selector-fecha-texto';
        boton.appendChild(texto);

        // Labels pointing at the input now point at the button (a hidden input can't be focused).
        if (input.id) {
            document.querySelectorAll(`label[for="${CSS.escape(input.id)}"]`).forEach((label) => {
                label.htmlFor = boton.id;
            });
        }

        const panel = document.createElement('div');
        panel.className = 'calendario';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Elegir fecha');
        panel.hidden = true;
        const header = document.createElement('div');
        header.className = 'calendario-header';
        const nav = (dir, etiqueta, simbolo) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'calendario-nav';
            b.setAttribute('aria-label', etiqueta);
            b.textContent = simbolo;
            b.addEventListener('click', () => {
                vista = new Date(vista.getFullYear(), vista.getMonth() + dir, 1);
                render();
            });
            return b;
        };
        const btnPrev = nav(-1, 'Mes anterior', '‹');
        const btnNext = nav(1, 'Mes siguiente', '›');
        const mesActual = document.createElement('span');
        mesActual.className = 'calendario-mes-actual';
        header.append(btnPrev, mesActual, btnNext);
        const filaDias = document.createElement('div');
        filaDias.className = 'calendario-dias-semana';
        DIAS.forEach((d) => {
            const span = document.createElement('span');
            span.textContent = d;
            filaDias.appendChild(span);
        });
        const grid = document.createElement('div');
        grid.className = 'calendario-grid';
        panel.append(header, filaDias, grid);

        if (opcional) {
            const borrar = document.createElement('button');
            borrar.type = 'button';
            borrar.className = 'calendario-borrar';
            borrar.textContent = 'Borrar';
            borrar.addEventListener('click', () => elegir(null));
            panel.appendChild(borrar);
        }

        input.type = 'hidden';
        contenedor.append(boton, input, panel);

        const limite = (atributo) => (input.getAttribute(atributo) ? parseISO(input.getAttribute(atributo)) : null);
        const seleccionada = () => (input.value ? parseISO(input.value) : null);
        let vista = mesDe(seleccionada() || new Date());

        function sincronizar() {
            const fecha = seleccionada();
            texto.textContent = fecha ? aVisible(fecha) : placeholder;
            boton.classList.toggle('selector-fecha-vacio', !fecha);
        }

        // Scripts that set input.value directly (default filters, reset forms) also update the button text.
        Object.defineProperty(input, 'value', {
            configurable: true,
            get: () => valorNativo.get.call(input),
            set: (valor) => {
                valorNativo.set.call(input, valor);
                sincronizar();
            },
        });

        // form.reset() restores the value without going through the setter above.
        if (input.form) {
            input.form.addEventListener('reset', () => setTimeout(sincronizar));
        }

        // Validation marks (form-api.js) and the disabled state (step-by-step forms) show on the button.
        const espejar = () => {
            const invalido = input.getAttribute('aria-invalid');
            if (invalido === null) {
                boton.removeAttribute('aria-invalid');
            } else {
                boton.setAttribute('aria-invalid', invalido);
            }
            boton.disabled = input.disabled;
            if (input.disabled && !panel.hidden) {
                cerrar();
            }
        };
        new MutationObserver(espejar).observe(input, { attributes: true, attributeFilter: ['aria-invalid', 'disabled'] });

        function elegir(fecha) {
            input.value = fecha ? aISO(fecha) : '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            cerrar();
            boton.focus();
        }

        function render() {
            const min = limite('min');
            const max = limite('max');
            const hoy = new Date();
            const elegida = seleccionada();
            mesActual.textContent = MESES[vista.getMonth()] + ' ' + vista.getFullYear();
            btnPrev.disabled = !!min && vista <= mesDe(min);
            btnNext.disabled = !!max && vista >= mesDe(max);
            grid.replaceChildren();

            const vacias = (vista.getDay() + 6) % 7;
            for (let i = 0; i < vacias; i++) {
                grid.appendChild(document.createElement('span'));
            }
            const diasEnMes = new Date(vista.getFullYear(), vista.getMonth() + 1, 0).getDate();
            for (let dia = 1; dia <= diasEnMes; dia++) {
                const fecha = new Date(vista.getFullYear(), vista.getMonth(), dia);
                const celda = document.createElement('button');
                celda.type = 'button';
                celda.className = 'calendario-dia';
                celda.textContent = String(dia);
                const fuera = (min && fecha < min) || (max && fecha > max);
                if (fuera || (input.diaHabilitado && !input.diaHabilitado(fecha))) {
                    celda.disabled = true;
                } else {
                    celda.addEventListener('click', () => elegir(fecha));
                }
                celda.classList.toggle('calendario-dia-seleccionado', mismoDia(fecha, elegida));
                celda.classList.toggle('calendario-dia-hoy', mismoDia(fecha, hoy));
                grid.appendChild(celda);
            }
        }

        // Fixed position next to the button, so scrolling tables or cards never clip it; opens upward if needed.
        function ubicar() {
            const r = boton.getBoundingClientRect();
            const alto = panel.offsetHeight;
            const ancho = panel.offsetWidth;
            const arriba = r.bottom + 8 + alto > window.innerHeight && r.top - 8 - alto > 0;
            panel.style.top = (arriba ? r.top - 8 - alto : r.bottom + 8) + 'px';
            panel.style.left = Math.max(8, Math.min(r.left, window.innerWidth - ancho - 8)) + 'px';
        }

        const cerrarSiAfuera = (evento) => {
            if (!contenedor.contains(evento.target)) {
                cerrar();
            }
        };
        const cerrarConEscape = (evento) => {
            if (evento.key === 'Escape') {
                cerrar();
                boton.focus();
            }
        };

        function abrir() {
            vista = mesDe(seleccionada() || limite('min') || new Date());
            render();
            panel.hidden = false;
            ubicar();
            boton.setAttribute('aria-expanded', 'true');
            document.addEventListener('click', cerrarSiAfuera);
            document.addEventListener('keydown', cerrarConEscape);
            window.addEventListener('scroll', ubicar, true);
            window.addEventListener('resize', ubicar);
        }

        function cerrar() {
            panel.hidden = true;
            boton.setAttribute('aria-expanded', 'false');
            document.removeEventListener('click', cerrarSiAfuera);
            document.removeEventListener('keydown', cerrarConEscape);
            window.removeEventListener('scroll', ubicar, true);
            window.removeEventListener('resize', ubicar);
        }

        boton.addEventListener('click', (evento) => {
            evento.preventDefault();
            if (panel.hidden) {
                abrir();
            } else {
                cerrar();
            }
        });

        espejar();

        input.refrescarCalendario = () => {
            sincronizar();
            if (!panel.hidden) {
                render();
            }
        };

        sincronizar();
    }

    const buscar = (raiz) => {
        if (raiz.matches && raiz.matches('input[type="date"]')) {
            mejorar(raiz);
        }
        if (raiz.querySelectorAll) {
            raiz.querySelectorAll('input[type="date"]').forEach(mejorar);
        }
    };

    buscar(document);
    new MutationObserver((cambios) => {
        cambios.forEach((cambio) => cambio.addedNodes.forEach((nodo) => {
            if (nodo.nodeType === Node.ELEMENT_NODE) {
                buscar(nodo);
            }
        }));
    }).observe(document.body, { childList: true, subtree: true });
})();
