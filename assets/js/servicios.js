(() => {
    const contenedor = document.getElementById('estudios-publicos');
    if (!contenedor) {
        return;
    }

    function tarjeta(estudio, sesion) {
        const card = document.createElement('article');
        card.className = 'card-estudio';

        const titulo = document.createElement('h2');
        titulo.textContent = estudio.etiqueta;
        card.appendChild(titulo);

        if (estudio.descripcion) {
            const p = document.createElement('p');
            p.textContent = estudio.descripcion;
            card.appendChild(p);
        }

        const modo = document.createElement('p');
        modo.className = 'card-estudio-modo';
        const enlace = document.createElement('a');
        enlace.className = 'btn-primario';
        if (estudio.autoagendable) {
            modo.textContent = 'Podés agendarlo en línea.';
            if (sesion && sesion.logueado) {
                if (sesion.rol !== 'paciente') {
                    card.appendChild(modo);
                    return card;
                }
                enlace.textContent = 'Agendar';
                enlace.href = 'agendar-cita.html';
            } else {
                enlace.textContent = 'Iniciar sesión para agendar';
                enlace.href = 'login.html';
            }
        } else {
            modo.textContent = 'Requiere la orden de un profesional.';
            enlace.textContent = 'Consultanos';
            enlace.href = 'contacto.html';
        }
        card.append(modo, enlace);
        return card;
    }

    (async () => {
        const [respuesta, sesion] = await Promise.all([
            apiJson('api/estudios-publicos.php', { publica: true }),
            window.sesionPublica,
        ]);
        if (!respuesta || !respuesta.estudios) {
            document.querySelector('[data-error]').hidden = false;
            return;
        }
        if (!respuesta.estudios.length) {
            contenedor.replaceChildren(vacio('No hay estudios publicados.'));
            return;
        }
        contenedor.replaceChildren(...respuesta.estudios.map((e) => tarjeta(e, sesion)));
    })();
})();
