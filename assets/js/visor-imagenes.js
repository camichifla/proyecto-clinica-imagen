(function () {
  var dialogo = document.getElementById('visor-imagen');
  if (!dialogo) {
    return;
  }
  var imagenGrande = dialogo.querySelector('img');
  var botonCerrar  = dialogo.querySelector('.visor-imagen-cerrar');

  document.addEventListener('click', function (evento) {
    var miniatura = evento.target.closest('.tarjeta-resultado-imagenes img');
    if (!miniatura) {
      return;
    }
    imagenGrande.src = miniatura.src;
    imagenGrande.alt = miniatura.alt;
    dialogo.showModal();
  });

  botonCerrar.addEventListener('click', function () {
    dialogo.close();
  });

  // Cerrar al clickear el fondo (::backdrop) — un click dentro de la
  // imagen no llega hasta el <dialog> mismo, solo el que cae afuera.
  dialogo.addEventListener('click', function (evento) {
    if (evento.target === dialogo) {
      dialogo.close();
    }
  });
})();
