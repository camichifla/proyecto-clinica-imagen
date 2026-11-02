(() => {
    // Los textareas crecen con field-sizing (base.css); donde el navegador no lo soporta se ajusta la altura a mano.
    if (!CSS.supports('field-sizing', 'content')) {
        const ajustar = (evento) => {
            const caja = evento.target;
            if (caja instanceof HTMLTextAreaElement) {
                caja.style.height = 'auto';
                caja.style.height = caja.scrollHeight + caja.offsetHeight - caja.clientHeight + 'px';
            }
        };
        document.addEventListener('input', ajustar);
        document.addEventListener('focusin', ajustar);
    }

    // Las etiquetas flotantes (base.css) detectan el campo vacío con :placeholder-shown, por eso se pone un placeholder invisible.
    const ponerPlaceholder = (raiz) => {
        if (raiz.querySelectorAll) {
            raiz.querySelectorAll('.campo input:not([placeholder]), .campo textarea:not([placeholder])').forEach((campo) => {
                campo.placeholder = ' ';
            });
        }
    };
    ponerPlaceholder(document);

    const TEXTO = 'input:is([type="text"], [type="search"], [type="email"], [type="tel"]):not([hidden])';
    const agregarLimpiar = (contenedor, simbolo, etiqueta, limpiar) => {
        if (contenedor.querySelector(':scope > .campo-limpiar') || contenedor.closest('[data-sin-limpiar]')) {
            return;
        }
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'campo-limpiar';
        boton.textContent = simbolo;
        boton.title = etiqueta;
        boton.setAttribute('aria-label', etiqueta);
        boton.addEventListener('mousedown', (evento) => evento.preventDefault());
        boton.addEventListener('click', () => {
            const girar = boton.textContent === '↺' && !matchMedia('(prefers-reduced-motion: reduce)').matches;
            limpiar();
            if (girar && !boton.classList.contains('girando')) {
                boton.classList.add('girando');
                boton.addEventListener('animationend', () => boton.classList.remove('girando'), { once: true });
            }
        });
        contenedor.appendChild(boton);
    };
    const disparar = (el, ...tipos) => tipos.forEach((tipo) => el.dispatchEvent(new Event(tipo, { bubbles: true })));
    const prepararLimpiar = (raiz) => {
        if (!raiz.querySelectorAll) {
            return;
        }
        raiz.querySelectorAll('.buscador-combo > select.selector-combo').forEach((select) => {
            if (select.dataset.buscable === undefined && Array.from(select.options).some((o) => o.value === '')) {
                agregarLimpiar(select.parentElement, '↺', 'Volver a la opción por defecto', () => {
                    select.value = '';
                    disparar(select, 'change');
                });
            }
        });
        raiz.querySelectorAll(`.buscador-combo > ${TEXTO}, .campo > ${TEXTO}`).forEach((input) => {
            if (!input.placeholder) {
                input.placeholder = ' ';
            }
            agregarLimpiar(input.parentElement, '✕', 'Borrar', () => {
                input.value = '';
                disparar(input, 'input');
                input.focus();
            });
        });
        raiz.querySelectorAll('.selector-fecha > input[type="hidden"]').forEach((input) => {
            agregarLimpiar(input.parentElement, '✕', 'Borrar fecha', () => {
                input.value = '';
                disparar(input, 'input', 'change');
            });
        });
        programarUbicacion();
    };

    const lienzo = document.createElement('canvas').getContext('2d');
    const anchoTexto = (caja) => {
        if (caja.tagName === 'INPUT') {
            lienzo.font = getComputedStyle(caja).font;
            return lienzo.measureText(caja.value).width;
        }
        const rango = document.createRange();
        rango.selectNodeContents(caja.querySelector('.selector-fecha-texto') || caja);
        return rango.getBoundingClientRect().width;
    };
    const ubicarLimpiar = (boton) => {
        const caja = boton.parentElement.querySelector(':scope > input:not([type="hidden"]), :scope > .selector-combo-boton, :scope > .selector-fecha-boton');
        if (!caja || !boton.offsetParent) {
            return;
        }
        const estilo = getComputedStyle(caja);
        const linea = parseFloat(estilo.lineHeight) || parseFloat(estilo.fontSize) * 1.2;
        const conFlecha = caja.tagName !== 'INPUT';
        const izquierda = caja.offsetLeft + parseFloat(estilo.paddingLeft) + anchoTexto(caja) + 6;
        const maximo = caja.offsetLeft + caja.offsetWidth - boton.offsetWidth - (conFlecha ? 40 : 8);
        boton.style.left = Math.min(izquierda, maximo) + 'px';
        boton.style.right = 'auto';
        boton.style.top = caja.offsetTop + parseFloat(estilo.paddingTop) + linea / 2 - boton.offsetHeight / 2 + 'px';
    };
    let pendiente = false;
    function programarUbicacion() {
        if (!pendiente) {
            pendiente = true;
            requestAnimationFrame(() => {
                pendiente = false;
                document.querySelectorAll('.campo-limpiar').forEach(ubicarLimpiar);
            });
        }
    }
    ['input', 'change', 'focusout', 'click'].forEach((tipo) => document.addEventListener(tipo, programarUbicacion));
    window.addEventListener('resize', programarUbicacion);
    new MutationObserver(programarUbicacion).observe(document.body, { subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['class'] });
    document.fonts.ready.then(programarUbicacion);
    prepararLimpiar(document);

    document.querySelectorAll('input[type="file"]').forEach((input) => {
        const lista = document.createElement('ul');
        lista.className = 'archivos-lista';
        input.after(lista);
        let actuales = [];
        let interno = false;
        const fijar = (archivos) => {
            const datos = new DataTransfer();
            archivos.forEach((archivo) => datos.items.add(archivo));
            input.files = datos.files;
        };
        const mismo = (a, b) => a.name === b.name && a.size === b.size && a.lastModified === b.lastModified;
        const mostrar = () => {
            lista.replaceChildren(...Array.from(input.files).map((archivo, indice) => {
                const li = document.createElement('li');
                const nombre = document.createElement('span');
                nombre.textContent = archivo.name;
                const quitar = document.createElement('button');
                quitar.type = 'button';
                quitar.className = 'archivo-quitar';
                quitar.textContent = '✕';
                quitar.title = 'Quitar ' + archivo.name;
                quitar.setAttribute('aria-label', 'Quitar ' + archivo.name);
                quitar.addEventListener('click', () => {
                    fijar(actuales.filter((otro, i) => i !== indice));
                    interno = true;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    interno = false;
                });
                li.append(nombre, quitar);
                return li;
            }));
        };
        input.addEventListener('change', () => {
            if (!interno && input.multiple) {
                const nuevos = Array.from(input.files).filter((archivo) => !actuales.some((otro) => mismo(otro, archivo)));
                fijar(actuales.concat(nuevos));
            }
            actuales = Array.from(input.files);
            mostrar();
        });
        if (input.form) {
            input.form.addEventListener('reset', () => setTimeout(() => {
                actuales = [];
                mostrar();
            }));
        }

        const aceptado = (archivo) => !input.accept || input.accept.split(',').some((tipo) => {
            tipo = tipo.trim().toLowerCase();
            return tipo.startsWith('.') ? archivo.name.toLowerCase().endsWith(tipo)
                : tipo.endsWith('/*') ? archivo.type.startsWith(tipo.slice(0, -1)) : archivo.type === tipo;
        });
        input.addEventListener('dragover', (evento) => {
            evento.preventDefault();
            input.classList.add('arrastrando');
        });
        input.addEventListener('dragleave', () => input.classList.remove('arrastrando'));
        input.addEventListener('drop', (evento) => {
            evento.preventDefault();
            input.classList.remove('arrastrando');
            const soltados = Array.from(evento.dataTransfer.files).filter(aceptado);
            if (soltados.length && !input.disabled) {
                fijar(input.multiple ? soltados : soltados.slice(0, 1));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });
    new MutationObserver((cambios) => cambios.forEach((cambio) => cambio.addedNodes.forEach((nodo) => {
        if (nodo.nodeType === Node.ELEMENT_NODE) {
            prepararLimpiar(nodo.parentElement || nodo);
        }
    }))).observe(document.body, { childList: true, subtree: true });
    new MutationObserver((cambios) => cambios.forEach((cambio) => cambio.addedNodes.forEach(ponerPlaceholder)))
        .observe(document.body, { childList: true, subtree: true });

    document.addEventListener('input', (evento) => {
        const campo = evento.target;
        // data-permitidos="<cuerpo de clase de caracteres regex>": lo que quede fuera se descarta al tipear o pegar; el pattern del campo sigue validando el formato.
        if (!campo.dataset || campo.dataset.permitidos === undefined) {
            return;
        }
        const fuera = new RegExp(`[^${campo.dataset.permitidos}]`, 'gu');
        if (fuera.test(campo.value)) {
            const cursor = campo.selectionStart - (campo.value.slice(0, campo.selectionStart).match(fuera) || []).length;
            campo.value = campo.value.replace(fuera, '');
            campo.setSelectionRange(cursor, cursor);
        }
    });

    // Listas de requisitos: <ul class="requisitos" data-para="<id del input>"> con <li data-regla="<regex>">; cada ítem se marca (.cumplido) mientras el valor cumple su regla.
    document.querySelectorAll('.requisitos[data-para]').forEach((lista) => {
        const campo = document.getElementById(lista.dataset.para);
        const items = Array.from(lista.querySelectorAll('li[data-regla]'));
        const revisar = () => items.forEach((li) => li.classList.toggle('cumplido', new RegExp(li.dataset.regla, 'u').test(campo.value)));
        campo.addEventListener('input', revisar);
        revisar();
    });

    const OJO = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
    const OJO_TACHADO = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7c2 0 3.8.65 5.3 1.6M22 12s-3.5 7-10 7c-2 0-3.8-.65-5.3-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m3 3 18 18"/></svg>';
    document.querySelectorAll('input[type="password"]').forEach((input) => {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'campo-ojo';
        boton.innerHTML = OJO;
        boton.title = 'Mostrar contraseña';
        boton.setAttribute('aria-label', 'Mostrar contraseña');
        boton.setAttribute('aria-pressed', 'false');
        boton.addEventListener('mousedown', (evento) => evento.preventDefault());
        boton.addEventListener('click', () => {
            const ver = input.type === 'password';
            input.type = ver ? 'text' : 'password';
            boton.innerHTML = ver ? OJO_TACHADO : OJO;
            const etiqueta = ver ? 'Ocultar contraseña' : 'Mostrar contraseña';
            boton.title = etiqueta;
            boton.setAttribute('aria-label', etiqueta);
            boton.setAttribute('aria-pressed', String(ver));
        });
        input.after(boton);
    });
})();
