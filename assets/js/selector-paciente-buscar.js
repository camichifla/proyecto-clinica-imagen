(() => {
  const select = document.getElementById('paciente_id');
  const boton = document.getElementById('btn-buscar');
  select.addEventListener('change', () => {
    boton.disabled = select.value === '';
  });
})();
