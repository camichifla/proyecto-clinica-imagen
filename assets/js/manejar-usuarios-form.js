// Shows/hides the "Crear cuenta" fields by rol. UX convenience only: the server
// re-validates everything per rol regardless of what the client sends.
// Hidden blocks are also disabled, so they are neither submitted nor flagged as required.
(function () {
    var rolSelect = document.getElementById('rol');
    var campos = document.getElementById('campos-cuenta');
    var campoEspecializacion = document.getElementById('campo-especializacion');
    var camposPaciente = document.getElementById('campos-paciente');
    if (!rolSelect || !campos || !campoEspecializacion || !camposPaciente) {
        return;
    }

    function mostrar(bloque, visible) {
        bloque.hidden = !visible;
        bloque.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.disabled = !visible;
        });
    }

    function aplicar() {
        var rol = rolSelect.value;
        // Outer block first: it enables/disables everything inside, then the nested ones refine it.
        mostrar(campos, rol !== '');
        mostrar(campoEspecializacion, rol === 'profesional');
        mostrar(camposPaciente, rol === 'paciente');
    }

    rolSelect.addEventListener('change', aplicar);
    aplicar();
})();
