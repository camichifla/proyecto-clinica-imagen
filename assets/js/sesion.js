// User session. By default it requires login (redirects to login.html and exposes
// window.sesionLista). With data-modo="publico" on the <script> it doesn't redirect,
// toggles the [data-sesion] elements and exposes window.sesionPublica.
// In non-public mode, if the current view isn't in the role's menu,
// it redirects to index.html (the real check stays in the API).
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

    // Guarded pages show no content until the session is confirmed (no flash of the empty shell before a redirect).
    const principal = document.querySelector('main');
    if (!publico && principal) {
        principal.style.visibility = 'hidden';
    }

    const promesa = (async () => {
        const url = publico ? 'api/sesion.php?opcional=1' : 'api/sesion.php';
        let respuesta;
        try {
            respuesta = await fetch(url);
            if (publico && respuesta.status === 401) {
                // An expired session is destroyed in that response; retrying
                // opens a new one with its CSRF token.
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

        if (principal) {
            principal.style.visibility = '';
        }

        if (sesion.sinSede) {
            const aviso = document.createElement('p');
            aviso.className = 'aviso-error';
            aviso.setAttribute('role', 'alert');
            aviso.textContent = 'No tenés una sede asignada. Pedile a un administrador que te asigne una.';
            document.querySelector('main').prepend(aviso);
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

    window[publico ? 'sesionPublica' : 'sesionLista'] = promesa;
})();
