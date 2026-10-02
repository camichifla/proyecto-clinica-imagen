(() => {
    const form = document.querySelector('form[data-api]');
    const contenedor = document.getElementById('campos-usuario');
    if (!form || !contenedor) {
        return;
    }

    function crearCampo(campo) {
        const div = document.createElement('div');
        div.className = 'campo';
        const label = document.createElement('label');
        label.htmlFor = campo.campo;
        label.textContent = campo.label;
        const input = document.createElement('input');
        input.type = campo.tipo;
        input.id = campo.campo;
        input.name = campo.campo;
        input.value = campo.valor;
        div.append(label, input);
        return div;
    }

    form.addEventListener('form-api:respuesta', (evento) => {
        if (evento.detail.ok) {
            const aviso = document.querySelector('[data-aviso]');
            if (aviso) {
                aviso.hidden = false;
            }
        }
    });

    async function init() {
        let respuesta;
        try {
            respuesta = await fetch('api/modificar-usuario.php');
        } catch {
            return;
        }
        if (respuesta.status === 401 || respuesta.status === 403 || respuesta.redirected) {
            window.location.href = 'login.html';
            return;
        }
        if (respuesta.status === 404) {
            form.hidden = true;
            const p = document.createElement('p');
            p.textContent = 'Esta seccion esta en construccion.';
            form.insertAdjacentElement('beforebegin', p);
            return;
        }

        const data = await respuesta.json();

        const fila = document.createElement('div');
        fila.className = 'campo-fila';
        data.campos.slice(0, 2).forEach((campo) => fila.appendChild(crearCampo(campo)));
        contenedor.appendChild(fila);
        data.campos.slice(2).forEach((campo) => contenedor.appendChild(crearCampo(campo)));
    }

    init();
})();
