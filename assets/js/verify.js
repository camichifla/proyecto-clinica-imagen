document.addEventListener('DOMContentLoaded', async () => {
    const token = new URL(window.location.href).searchParams.get('token');
    if (!token) {
        return; // default state: the [data-default] message is already visible
    }

    document.querySelector('[data-default]').hidden = true;

    const data = await apiJson(`api/verify.php?token=${encodeURIComponent(token)}`, { publica: true });
    if (data && data.ok && data.redirect) {
        window.location.href = data.redirect;
        return;
    }

    document.querySelector('[data-token-invalido]').hidden = false;
});
