document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('anio-actual').textContent = new Date().getFullYear();
  window.sesionPublica.then((sesion) => {
    if (sesion && sesion.logueado) {
      const nombreHero = document.querySelector('.user-nombre-hero');
      if (nombreHero) {
        nombreHero.textContent = sesion.nombre;
      }
    }
  });
});
