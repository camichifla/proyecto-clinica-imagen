(() => {
    const form = document.querySelector('form[data-api]');
    const contenedor = document.getElementById('campos-usuario');
    const btnCancelar = document.getElementById('cancelar-edicion');
    if (btnCancelar) {
        btnCancelar.addEventListener('click', () => {
            if (history.length > 1) {
                history.back();
            } else {
                window.location.href = 'index.html';
            }
        });
    }
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

    const formEmail = document.getElementById('form-email');
    const formContrasena = document.getElementById('form-contrasena');

    // Persistent note while a change waits for confirmation (the current email stays active until then).
    function mostrarPendiente(email) {
        const p = document.getElementById('email-pendiente');
        p.hidden = !email;
        p.textContent = email ? `Pendiente de confirmación: ${email}. Revisá esa casilla para completar el cambio.` : '';
    }

    formEmail.addEventListener('form-api:respuesta', (evento) => {
        if (!evento.detail.ok) {
            return;
        }
        mostrarPendiente(evento.detail.email_pendiente);
        mostrarAviso('email', evento.detail.email_pendiente
            ? `Te enviamos un correo a ${evento.detail.email_pendiente}. El cambio se aplica cuando confirmes la nueva dirección (vence en 24 horas); hasta entonces seguís usando el correo actual.`
            : 'Correo actualizado correctamente.');
        formEmail.elements.contrasena_actual.value = '';
    });

    formContrasena.addEventListener('form-api:respuesta', (evento) => {
        if (evento.detail.ok) {
            mostrarAviso('contrasena');
            formContrasena.reset();
            formContrasena.elements.contrasena.dispatchEvent(new Event('input'));
        }
    });

    async function init() {
        const data = await apiJson('api/modificar-usuario.php');
        if (!data) {
            return;
        }
        formEmail.elements.email.value = data.email || '';
        mostrarPendiente(data.email_pendiente);
        if (!data.campos) {
            form.hidden = true;
            form.insertAdjacentElement('beforebegin', parrafo('Esta sección está en construcción.'));
            return;
        }

        const fila = document.createElement('div');
        fila.className = 'campo-fila';
        data.campos.slice(0, 2).forEach((campo) => fila.appendChild(crearCampo(campo)));
        contenedor.appendChild(fila);
        data.campos.slice(2).forEach((campo) => contenedor.appendChild(crearCampo(campo)));
    }

    init();
})();
