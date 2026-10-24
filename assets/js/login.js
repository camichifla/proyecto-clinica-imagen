document.addEventListener('DOMContentLoaded', () => {
  const params = new URL(window.location.href).searchParams;
  if (params.has('expirado')) {
    document.querySelector('[data-aviso="expirado"]').hidden = false;
  }
  if (params.has('verificado')) {
    document.querySelector('[data-aviso="verificado"]').hidden = false;
  }

  document.querySelector('form[data-api]').addEventListener('form-api:respuesta', (evento) => {
    const error = evento.detail.error || '';
    document.querySelector('[data-aviso="reenviar"]').hidden = !error.includes('verificar');
  });
});
