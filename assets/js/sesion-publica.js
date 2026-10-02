window.sesionPublica = (async () => {
    let respuesta;
    try {
        respuesta = await fetch('api/sesion.php?opcional=1');
    } catch {
        return null;
    }
    if (!respuesta.ok) {
        return null;
    }

    const sesion = await respuesta.json();

    document.querySelectorAll('input[name="csrf"]').forEach((input) => {
        input.value = sesion.csrf;
    });

    document.querySelectorAll('[data-sesion="user"]').forEach((el) => {
        el.hidden = !sesion.logueado;
    });
    document.querySelectorAll('[data-sesion="anon"]').forEach((el) => {
        el.hidden = sesion.logueado;
    });

    if (sesion.logueado) {
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
