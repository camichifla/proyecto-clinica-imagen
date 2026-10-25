document.addEventListener('DOMContentLoaded', async () => {
    const token = new URL(window.location.href).searchParams.get('token');
    if (!token) {
        return; // default state: the [data-default] message is already visible
    }

    document.querySelector('[data-default]').hidden = true;

    let respuesta;
    try {
        respuesta = await fetch(`api/verify.php?token=${encodeURIComponent(token)}`);
    } catch {
        document.querySelector('[data-token-invalido]').hidden = false;
        return;
    }

    const data = await respuesta.json();
    if (data.ok && data.redirect) {
        window.location.href = data.redirect;
        return;
    }

    document.querySelector('[data-token-invalido]').hidden = false;
});
