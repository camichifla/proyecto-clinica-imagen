// Filtrado UX-only del <select> de citas segun el paciente elegido.
// Consume CITAS_POR_PACIENTE / CITA_ELEGIDA / MENSAJE_SIN_CITAS, declarados por cada pagina antes de este script.
// El servidor siempre re-valida paciente/profesional/estado.
(function () {
  var pacienteSelect = document.getElementById('paciente_id');
  var citaSelect      = document.getElementById('cita_id');
  if (!pacienteSelect || !citaSelect) {
    return;
  }

  function aplicarCitas() {
    var citas = CITAS_POR_PACIENTE[pacienteSelect.value] || [];
    citaSelect.innerHTML = '';

    var placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = pacienteSelect.value
      ? (citas.length ? 'Sin cita asociada' : MENSAJE_SIN_CITAS)
      : 'Elegi un paciente primero';
    citaSelect.appendChild(placeholder);

    citas.forEach(function (cita) {
      var opcion = document.createElement('option');
      opcion.value = String(cita.id);
      opcion.textContent = cita.label;
      if (CITA_ELEGIDA && String(cita.id) === CITA_ELEGIDA) {
        opcion.selected = true;
      }
      citaSelect.appendChild(opcion);
    });

    citaSelect.disabled = !pacienteSelect.value;
  }

  pacienteSelect.addEventListener('change', function () {
    CITA_ELEGIDA = '';
    aplicarCitas();
  });

  aplicarCitas();
})();
