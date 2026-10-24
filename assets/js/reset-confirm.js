document.addEventListener('DOMContentLoaded', async () => {
  const token = new URL(window.location.href).searchParams.get('token') || '';
  const form = document.querySelector('form[data-api]');
  const panelInvalido = document.querySelector('[data-token-invalido]');

  if (!token) {
    panelInvalido.hidden = false;
    return;
  }

  let respuesta;
  try {
    respuesta = await fetch(`api/reset-confirm.php?token=${encodeURIComponent(token)}`);
  } catch {
    panelInvalido.hidden = false;
    return;
  }

  if (!respuesta.ok) {
    panelInvalido.hidden = false;
    return;
  }

  form.querySelector('input[name="token"]').value = token;
  form.hidden = false;
});
