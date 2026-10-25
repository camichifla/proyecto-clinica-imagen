// Sesion del usuario. Por defecto exige login (redirige a login.html y expone
// window.sesionLista). Con data-modo="publico" en el <script> no redirige,
// alterna los elementos [data-sesion] y expone window.sesionPublica.
// En modo no publico, si la vista actual no figura en el menu del rol,
// redirige a index.html (el control real sigue en la API).
(() => {
    const publico = document.currentScript.dataset.modo === 'publico';
    const ROLES = { paciente: 'Paciente', medico: 'Médico', profesional: 'Profesional', administrador: 'Administrador', recepcionista: 'Recepcionista' };
    const MENU_ANONIMO = [
        { href: 'login.html', label: 'Iniciar sesión' },
        { href: 'register.html', label: 'Registrarse' },
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
        // same hamburger with only "Iniciar sesión" and "Registrarse".
        const lista = document.querySelector('.menu-usuario-lista');
        if (lista) {
            const logueado = !publico || sesion.logueado;
            const nombre = lista.parentElement.querySelector('.user-nombre');
            const rol = document.createElement('span');
            rol.className = 'menu-usuario-rol';
            nombre.textContent = logueado ? sesion.nombre : 'Bienvenido/a';
            rol.textContent = logueado ? (ROLES[sesion.rol] || '') : 'Ingresá o creá tu cuenta';
            nombre.after(rol);

            const logout = lista.parentElement.querySelector('.form-logout');
            renderMenu(lista, logueado ? sesion.menu : MENU_ANONIMO, window.location.pathname.split('/').pop());
            if (logueado) {
                const pie = document.createElement('div');
                pie.className = 'menu-usuario-pie';
                pie.appendChild(interruptorTema());
                if (logout) {
                    pie.appendChild(logout);
                }
                lista.after(pie);
            } else if (logout) {
                logout.remove();
            }
            const detalles = lista.closest('details');
            detalles.hidden = false;
            prepararPanel(detalles);
        }

        return sesion;
    })();

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
        input.addEventListener('change', () => window.tema.cambiar(input.checked ? 'dark' : 'light'));
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
        const cerrar = () => {
            details.open = false;
        };

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
