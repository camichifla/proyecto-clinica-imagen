// Habilita/deshabilita el boton "Buscar" segun si hay un paciente elegido.
(() => {
  const select = document.getElementById('paciente_id');
  const boton = document.getElementById('btn-buscar');
  select.addEventListener('change', () => {
    boton.disabled = select.value === '';
  });
})();
