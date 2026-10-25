// Sesion del usuario. Por defecto exige login (redirige a login.html y expone
// window.sesionLista). Con data-modo="publico" en el <script> no redirige,
// alterna los elementos [data-sesion] y expone window.sesionPublica.
// En modo no publico, si la vista actual no figura en el menu del rol,
// redirige a index.html (el control real sigue en la API).
// Role labels shared by every page (sesion.js loads first, so this is a global).
const ROLES_ETIQUETA = { paciente: 'Paciente', medico: 'Médico', profesional: 'Profesional', administrador: 'Administrador', recepcionista: 'Recepcionista' };

(() => {
    const publico = document.currentScript.dataset.modo === 'publico';
    // Without a session the public nav moves into the hamburger (the header only keeps the logo).
    const MENU_ANONIMO = [
        { href: 'login.html', label: 'Iniciar sesión' },
        { href: 'register.html', label: 'Registrarse' },
        { href: 'servicios.html', label: 'Servicios' },
        { href: 'contacto.html', label: 'Contacto' },
    ];

    const promesa = (async () => {
        const url = publico ? 'api/sesion.php?opcional=1' : 'api/sesion.php';
        let respuesta;
        try {
            respuesta = await fetch(url);
            if (publico && respuesta.status === 401) {
                // La sesión vencida se destruye en esa respuesta; reintentar
                // abre una nueva con su token CSRF.
                respuesta = await fetch(url);
            }
        } catch {
            if (!publico) {
                window.location.href = 'login.html';
            }
            return null;
        }
        if (!respuesta.ok) {
            if (!publico) {
                const expirado = (await respuesta.json().catch(() => ({}))).expirado;
                window.location.href = expirado ? 'login.html?expirado=1' : 'login.html';
            }
            return null;
        }

        const sesion = await respuesta.json();

        if (!publico) {
            const actual = window.location.pathname.split('/').pop();
            if (actual && !sesion.menu.some((item) => item.href === actual)) {
                window.location.replace('index.html');
                return null;
            }
        }

        document.querySelectorAll('input[name="csrf"]').forEach((input) => {
            input.value = sesion.csrf;
        });

        if (publico) {
            document.querySelectorAll('[data-sesion="user"]').forEach((el) => {
                el.hidden = !sesion.logueado;
            });
            document.querySelectorAll('[data-sesion="anon"]').forEach((el) => {
                el.hidden = sesion.logueado;
            });
        }

        // Logged in: role menu, theme switch and logout. Visitors on public pages get the
        // same hamburger with the public pages, "Iniciar sesión", "Registrarse" and the theme switch.
        const lista = document.querySelector('.menu-usuario-lista');
        if (lista) {
            const logueado = !publico || sesion.logueado;
            const actual = window.location.pathname.split('/').pop();
            const menu = logueado ? sesion.menu : MENU_ANONIMO;

            // Panel header: [account icon] name / role. The icon is the way into the account page.
            const nombre = lista.parentElement.querySelector('.user-nombre');
            const cabecera = document.createElement('div');
            cabecera.className = 'menu-usuario-cabecera';
            nombre.before(cabecera);
            const textos = document.createElement('div');
            const rol = document.createElement('span');
            rol.className = 'menu-usuario-rol';
            nombre.textContent = logueado ? sesion.nombre : 'Bienvenido';
            rol.textContent = logueado ? (ROLES_ETIQUETA[sesion.rol] || '') : 'Ingresá o creá tu cuenta';
            textos.append(nombre, rol);
            const cuenta = menu.find((item) => item.cuenta);
            if (cuenta) {
                cabecera.appendChild(iconoCuenta(cuenta, actual));
            }
            cabecera.appendChild(textos);

            const logout = lista.parentElement.querySelector('.form-logout');
            renderMenu(lista, menu.filter((item) => !item.cuenta), actual);
            const pie = document.createElement('div');
            pie.className = 'menu-usuario-pie';
            pie.appendChild(interruptorTema());
            if (logout) {
                if (logueado) {
                    pie.appendChild(logout);
                } else {
                    logout.remove();
                }
            }
            lista.after(pie);
            const detalles = lista.closest('details');
            detalles.hidden = false;
            prepararPanel(detalles);
        }

        return sesion;
    })();

    // Generic user silhouette (no photo) linking to the account page.
    function iconoCuenta(item, actual) {
        const enlace = document.createElement('a');
        enlace.href = item.href;
        enlace.className = 'menu-cuenta';
        enlace.title = 'Mi cuenta';
        enlace.setAttribute('aria-label', 'Mi cuenta');
        if (item.href === actual) {
            enlace.setAttribute('aria-current', 'page');
        }
        enlace.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>';
        return enlace;
    }

    function enlaceMenu(item, actual) {
        const li = document.createElement('li');
        const a = document.createElement('a');
        a.href = item.href;
        a.textContent = item.label;
        if (item.href === actual) {
            a.setAttribute('aria-current', 'page');
        }
        li.appendChild(a);
        return li;
    }

    // Consecutive items sharing a grupo become one collapsible section; the section
    // holding the current page starts open.
    function renderMenu(lista, menu, actual) {
        let grupo = null;
        let sublista = null;
        menu.forEach((item) => {
            if (!item.grupo) {
                grupo = null;
                lista.appendChild(enlaceMenu(item, actual));
                return;
            }
            if (item.grupo !== grupo) {
                grupo = item.grupo;
                const details = document.createElement('details');
                details.className = 'menu-grupo';
                const summary = document.createElement('summary');
                summary.textContent = grupo;
                sublista = document.createElement('ul');
                details.append(summary, sublista);
                const li = document.createElement('li');
                li.appendChild(details);
                lista.appendChild(li);
            }
            sublista.appendChild(enlaceMenu(item, actual));
            if (item.href === actual) {
                sublista.parentElement.open = true;
            }
        });
    }

    function interruptorTema() {
        const label = document.createElement('label');
        label.className = 'menu-tema';
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.setAttribute('role', 'switch');
        const sincronizar = () => {
            input.checked = window.tema.actual() === 'dark';
        };
        sincronizar();
        document.documentElement.addEventListener('cambio-tema', sincronizar);
        // Let the knob finish sliding (0.2s, --transition-fast) before the page cross-fades;
        // the view transition would otherwise freeze it mid-way.
        input.addEventListener('change', () => {
            const tema = input.checked ? 'dark' : 'light';
            setTimeout(() => window.tema.cambiar(tema), 200);
        });
        label.append(input, 'Modo oscuro');
        return label;
    }

    // Open menu: full-height panel under the header, the rest of the page dimmed and
    // locked. Closes with Esc, a click on the backdrop or choosing a link.
    function prepararPanel(details) {
        const header = details.closest('header');
        const toggle = details.querySelector('summary');
        const fondo = document.createElement('div');
        fondo.className = 'menu-fondo';
        fondo.hidden = true;
        header.after(fondo);

        const medirHeader = () => {
            const alto = Math.max(0, header.getBoundingClientRect().bottom);
            document.documentElement.style.setProperty('--alto-header', alto + 'px');
        };
        // Closing plays the opening animation in reverse (class "cerrando"), then really closes.
        // Without animations (prefers-reduced-motion) it closes at once.
        const panel = details.querySelector('.menu-usuario-panel');
        const cerrar = () => {
            if (!details.open || details.classList.contains('cerrando')) {
                return;
            }
            details.classList.add('cerrando');
            fondo.classList.add('cerrando');
            const terminar = () => {
                details.classList.remove('cerrando');
                fondo.classList.remove('cerrando');
                details.open = false;
            };
            if (getComputedStyle(panel).animationName === 'none') {
                terminar();
            } else {
                panel.addEventListener('animationend', terminar, { once: true });
            }
        };
        toggle.addEventListener('click', (evento) => {
            if (details.open) {
                evento.preventDefault();
                cerrar();
            }
        });

        details.addEventListener('toggle', () => {
            const abierto = details.open;
            if (abierto) {
                medirHeader();
                const primero = details.querySelector('.menu-usuario-lista a, .menu-usuario-lista summary');
                if (primero) {
                    primero.focus();
                }
            } else {
                toggle.focus();
            }
            fondo.hidden = !abierto;
            document.body.classList.toggle('menu-abierto', abierto);
        });
        fondo.addEventListener('click', cerrar);
        details.querySelector('.menu-usuario-lista').addEventListener('click', (evento) => {
            if (evento.target.closest('a')) {
                cerrar();
            }
        });
        document.addEventListener('keydown', (evento) => {
            if (evento.key === 'Escape' && details.open) {
                cerrar();
            }
        });
        window.addEventListener('resize', () => {
            if (details.open) {
                medirHeader();
            }
        });
    }

    // Text boxes grow with their text via CSS field-sizing (base.css); where the browser lacks it,
    // grow them by hand on typing and on focus (covers values filled in by scripts).
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

    // Floating labels (base.css) tell an empty box by :placeholder-shown, so text fields in a .campo without a
    // placeholder get an invisible one; fields added later by scripts too.
    const ponerPlaceholder = (raiz) => {
        if (raiz.querySelectorAll) {
            raiz.querySelectorAll('.campo input:not([placeholder]), .campo textarea:not([placeholder])').forEach((campo) => {
                campo.placeholder = ' ';
            });
        }
    };
    ponerPlaceholder(document);

    // Clear/reset button next to a filled field (base.css shows it only when there is something to clear):
    //   ↺ on dropdowns: back to the default '' option ("Todas", "Elegí ...").
    //   ✕ on text fields, search boxes and date pickers: empties it to type or pick again.
    // It fires the same events a manual change would, so filters, cascades and search boxes react as usual.
    const TEXTO = 'input:is([type="text"], [type="search"], [type="email"], [type="tel"]):not([hidden])'; // [hidden]: value holders (search boxes' ids)
    const agregarLimpiar = (contenedor, simbolo, etiqueta, limpiar) => {
        if (contenedor.querySelector(':scope > .campo-limpiar')) {
            return;
        }
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'campo-limpiar';
        boton.textContent = simbolo;
        boton.title = etiqueta;
        boton.setAttribute('aria-label', etiqueta);
        boton.addEventListener('mousedown', (evento) => evento.preventDefault());
        // ↺ resets right away and spins once to the left before hiding (base.css keeps it visible while .girando).
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
        // Dropdowns with a default option.
        raiz.querySelectorAll('.buscador-combo > select.selector-combo').forEach((select) => {
            if (select.dataset.buscable === undefined && Array.from(select.options).some((o) => o.value === '')) {
                agregarLimpiar(select.parentElement, '↺', 'Volver a la opción por defecto', () => {
                    select.value = '';
                    disparar(select, 'change');
                });
            }
        });
        // Search boxes (patients, professionals, accounts) and plain text fields.
        raiz.querySelectorAll(`.buscador-combo > ${TEXTO}, .campo > ${TEXTO}`).forEach((input) => {
            // base.css shows ✕ with :not(:placeholder-shown), which is always true without a placeholder.
            if (!input.placeholder) {
                input.placeholder = ' ';
            }
            agregarLimpiar(input.parentElement, '✕', 'Borrar', () => {
                input.value = '';
                disparar(input, 'input');
                input.focus();
            });
        });
        // Date pickers.
        raiz.querySelectorAll('.selector-fecha > input[type="hidden"]').forEach((input) => {
            agregarLimpiar(input.parentElement, '✕', 'Borrar fecha', () => {
                input.value = '';
                disparar(input, 'input', 'change');
            });
        });
        programarUbicacion();
    };

    // The button sits right after the box's text (capped before the dropdown arrow), level with the value line.
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
    // Box texts changed by scripts (dropdown labels, date texts) move the button too.
    // Also class changes (a box gets a value without new text nodes) and late web fonts (text width/height change).
    new MutationObserver(programarUbicacion).observe(document.body, { subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['class'] });
    document.fonts.ready.then(programarUbicacion);
    prepararLimpiar(document);

    // File fields: the chosen files are listed under the field, each with a ✕ that removes only that file, and
    // on a multiple field choosing again adds to the list instead of replacing it. The field's FileList is
    // rebuilt with DataTransfer, so the form submits exactly what is listed.
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

        // Drag and drop onto the box: only the types in accept, added like a normal pick (the change handler merges).
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

    window[publico ? 'sesionPublica' : 'sesionLista'] = promesa;
})();
