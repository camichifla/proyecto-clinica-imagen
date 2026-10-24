(() => {
    const lista = document.getElementById('mensajes-lista');
    if (!lista) {
        return;
    }
    const mensajeOk = document.querySelector('[data-mensaje]');
    const mensajeError = document.querySelector('[data-mensaje-error]');

    function mostrarMensaje(texto, esError) {
        const el = esError ? mensajeError : mensajeOk;
        const otro = esError ? mensajeOk : mensajeError;
        otro.hidden = true;
        el.textContent = texto;
        el.hidden = false;
    }

    // Texto plano siempre: el contenido lo escribio un visitante anonimo.
    function renderMensaje(m, csrf) {
        const card = document.createElement('article');
        card.className = 'mensaje-contacto' + (m.atendido ? ' mensaje-atendido' : '');

        const meta = document.createElement('p');
        meta.className = 'mensaje-contacto-meta';
        const contacto = [m.email, m.telefono].filter(Boolean).join(' - ');
        meta.textContent = `${m.creado_en} - ${m.nombre} (${contacto})`;

        const texto = document.createElement('p');
        texto.className = 'mensaje-contacto-texto';
        texto.textContent = m.mensaje;

        const pie = document.createElement('div');
        pie.className = 'mensaje-contacto-pie';
        const estado = document.createElement('span');
        estado.className = 'estado-badge ' + (m.atendido ? 'estado-confirmada' : 'estado-pendiente');
        estado.textContent = m.atendido ? 'Atendido' : 'Pendiente';
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn-secundario';
        boton.textContent = m.atendido ? 'Marcar como pendiente' : 'Marcar como atendido';
        boton.addEventListener('click', async () => {
            boton.disabled = true;
            const data = await apiJson('api/mensajes-contacto.php', {
                method: 'POST',
                body: new URLSearchParams({ csrf, id: m.id, atendido: m.atendido ? '0' : '1' }),
            });
            if (data && data.ok) {
                mostrarMensaje('Mensaje actualizado.', false);
                cargar();
                return;
            }
            boton.disabled = false;
            mostrarMensaje((data && data.error) || 'No se pudo actualizar el mensaje.', true);
        });
        pie.append(estado, boton);

        card.append(meta, texto, pie);
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
            const p = document.createElement('p');
            p.textContent = 'Todavía no hay mensajes.';
            lista.replaceChildren(p);
            return;
        }
        lista.replaceChildren(...data.mensajes.map((m) => renderMensaje(m, sesion.csrf)));
        if (data.mensajes.length >= data.max) {
            mostrarMensaje(`Se muestran los últimos ${data.max} mensajes.`, false);
        }
    }

    cargar();
})();
