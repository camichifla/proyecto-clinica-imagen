(() => {
    const lista = document.getElementById('mensajes-lista');
    if (!lista) {
        return;
    }
    const mensajeOk = document.querySelector('[data-mensaje]');
    const mensajeError = document.querySelector('[data-mensaje-error]');
    const mostrarMensaje = crearMostrarMensaje(mensajeOk, mensajeError);

    // Both actions delete the message server-side; a reply also emails it first.
    async function atender(m, respuesta, botones, textoOk) {
        botones.forEach((b) => { b.disabled = true; });
        const data = await enviarPost('api/mensajes-contacto.php', { id: m.id, respuesta });
        if (data && data.ok) {
            mostrarMensaje(textoOk, false);
            cargar();
            return;
        }
        botones.forEach((b) => { b.disabled = false; });
        mostrarMensaje((data && data.error) || 'No se pudo atender el mensaje.', true);
    }

    // Always plain text: the content was written by an anonymous visitor.
    function renderMensaje(m, maxRespuesta) {
        const card = document.createElement('article');
        card.className = 'mensaje-contacto';

        const meta = document.createElement('p');
        meta.className = 'mensaje-contacto-meta';
        const contacto = [m.email, m.telefono].filter(Boolean).join(' - ');
        meta.textContent = `${fechaHora(m.creado_en)} - ${m.nombre} (${contacto})`;

        const texto = document.createElement('p');
        texto.className = 'mensaje-contacto-texto';
        texto.textContent = m.mensaje;

        const responder = document.createElement('details');
        const resumen = document.createElement('summary');
        resumen.textContent = 'Responder por correo';
        const form = document.createElement('form');
        const label = document.createElement('label');
        label.htmlFor = 'respuesta-' + m.id;
        label.textContent = 'Respuesta para ' + m.email;
        const textarea = document.createElement('textarea');
        textarea.id = 'respuesta-' + m.id;
        textarea.required = true;
        textarea.maxLength = maxRespuesta;
        textarea.rows = 4;
        const enviar = document.createElement('button');
        enviar.type = 'submit';
        enviar.className = 'btn-primary';
        enviar.textContent = 'Enviar respuesta';
        // .campo gives it the same box and floating label as every other form.
        const campo = document.createElement('div');
        campo.className = 'campo';
        campo.append(label, textarea);
        form.append(campo, enviar);
        responder.append(resumen, form);

        const pie = document.createElement('div');
        pie.className = 'mensaje-contacto-pie';
        const atendido = document.createElement('button');
        atendido.type = 'button';
        atendido.className = 'btn-secundario';
        atendido.textContent = 'Marcar como atendido';
        pie.append(atendido);

        form.addEventListener('submit', (evento) => {
            evento.preventDefault();
            if (textarea.value.trim() === '') {
                textarea.reportValidity();
                return;
            }
            atender(m, textarea.value, [enviar, atendido], 'Respuesta enviada. El mensaje se borró.');
        });
        atendido.addEventListener('click', () => {
            if (window.confirm('El mensaje se va a borrar sin enviar respuesta. ¿Continuar?')) {
                atender(m, '', [enviar, atendido], 'Mensaje atendido y borrado.');
            }
        });

        card.append(meta, texto, responder, pie);
        return card;
    }

    async function cargar() {
        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }
        const data = await apiJson('api/mensajes-contacto.php');
        if (!data || !data.mensajes) {
            mostrarMensaje('No se pudieron cargar los mensajes.', true);
            return;
        }
        if (!data.mensajes.length) {
            lista.replaceChildren(parrafo('No hay mensajes pendientes.'));
            return;
        }
        lista.replaceChildren(...data.mensajes.map((m) => renderMensaje(m, data.maxRespuesta)));
        if (data.mensajes.length >= data.max) {
            mostrarMensaje(`Se muestran los últimos ${data.max} mensajes.`, false);
        }
    }

    cargar();
})();
