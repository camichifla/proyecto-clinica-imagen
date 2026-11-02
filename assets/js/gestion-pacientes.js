(() => {
    const form = document.getElementById('form-paciente');
    if (!form) {
        return;
    }
    const aviso = form.closest('section').querySelector('[data-mensaje]');

    form.addEventListener('form-api:respuesta', (evento) => {
        if (!evento.detail.ok) {
            return;
        }
        form.reset();
        aviso.textContent = 'Paciente creado correctamente.';
        aviso.hidden = false;
    });
    form.addEventListener('submit', () => {
        aviso.hidden = true;
    });
})();
