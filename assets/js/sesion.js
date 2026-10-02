window.sesionLista = (async () => {
    let respuesta;
    try {
        respuesta = await fetch('api/sesion.php');
    } catch {
        window.location.href = 'login.html';
        return null;
    }
    if (!respuesta.ok) {
        window.location.href = 'login.html';
        return null;
    }

    const sesion = await respuesta.json();

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

    document.querySelectorAll('input[name="csrf"]').forEach((input) => {
        input.value = sesion.csrf;
    });

    return sesion;
})();
