// Sesion del usuario. Por defecto exige login (redirige a login.html y expone
// window.sesionLista). Con data-modo="publico" en el <script> no redirige,
// alterna los elementos [data-sesion] y expone window.sesionPublica.
// En modo no publico, si la vista actual no figura en el menu del rol,
// redirige a index.html (el control real sigue en la API).
(() => {
    const publico = document.currentScript.dataset.modo === 'publico';

    const promesa = (async () => {
        const url = publico ? 'api/sesion.php?opcional=1' : 'api/sesion.php';
        let respuesta;
        try {
            respuesta = await fetch(url);
            if (publico && respuesta.status === 401) {
                // La sesion vencida se destruye en esa respuesta; reintentar
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

        if (!publico || sesion.logueado) {
            const nombre = document.querySelector('.user-nombre');
            if (nombre) {
                nombre.textContent = sesion.nombre;
            }

            const lista = document.querySelector('.menu-usuario-lista');
            if (lista) {
                sesion.menu.forEach((item) => {
                    const li = document.createElement('li');
                    const a = document.createElement('a');
                    a.href = item.href;
                    a.textContent = item.label;
                    li.appendChild(a);
                    lista.appendChild(li);
                });
            }
        }

        return sesion;
    })();

    window[publico ? 'sesionPublica' : 'sesionLista'] = promesa;
})();
