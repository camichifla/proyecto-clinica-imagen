// Shows/hides especializacion by rol. UX convenience only: the server
// re-validates that especializacion is present iff rol === 'profesional'
// regardless of what the client sends.
(function () {
  var rolSelect = document.getElementById('rol');
  var campoEspecializacion = document.getElementById('campo-especializacion');
  if (!rolSelect || !campoEspecializacion) {
    return;
  }

  function aplicar() {
    var esProfesional = rolSelect.value === 'profesional';
    campoEspecializacion.hidden = !esProfesional;
    if (!esProfesional) {
      campoEspecializacion.querySelectorAll('input[name="especializacion[]"]').forEach(function (casilla) {
        casilla.checked = false;
      });
    }
  }

  rolSelect.addEventListener('change', aplicar);
  aplicar();
})();
