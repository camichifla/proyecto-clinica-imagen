// Solo comodidad de UX: el servidor revalida todo según el rol. Los bloques ocultos también se deshabilitan para que no se envíen ni se marquen como obligatorios.
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
        mostrar(campos, rol !== '');
        mostrar(campoEspecializacion, rol === 'profesional');
        mostrar(camposPaciente, rol === 'paciente');
    }

    rolSelect.addEventListener('change', aplicar);
    aplicar();
})();
