// Habilita/deshabilita el boton "Buscar" segun si hay un paciente elegido.
// Compartido por historial-pacientes.php, resultados.php y buscar-resultados.php.
(() => {
  const select = document.getElementById('paciente_id');
  const boton = document.getElementById('btn-buscar');
  select.addEventListener('change', () => {
    boton.disabled = select.value === '';
  });
})();
