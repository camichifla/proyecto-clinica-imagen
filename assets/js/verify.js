document.addEventListener('DOMContentLoaded', async () => {
    const token = new URL(window.location.href).searchParams.get('token');
    if (!token) {
        return;
    }

    document.querySelector('[data-default]').hidden = true;

    const data = await apiJson(`api/verify.php?token=${encodeURIComponent(token)}`, { publica: true });
    if (!data || !data.ok) {
        document.querySelector('[data-token-invalido]').hidden = false;
        return;
    }

    const panel = document.querySelector('[data-confirmar]');
    const error = panel.querySelector('[data-confirmar-error]');
    const boton = panel.querySelector('[data-confirmar-boton]');
    panel.hidden = false;
    boton.addEventListener('click', async () => {
        boton.disabled = true;
        const sesion = await window.sesionPublica;
        const respuesta = await apiJson('api/verify.php', {
            method: 'POST',
            publica: true,
            body: new URLSearchParams({ csrf: sesion.csrf, accion: 'confirmar', token }),
        });
        if (respuesta && respuesta.ok && respuesta.redirect) {
            window.location.href = respuesta.redirect;
            return;
        }
        error.textContent = textoErrores(respuesta);
        error.hidden = false;
        boton.disabled = false;
    });
});
