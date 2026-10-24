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

// Side-by-side comparison (staff view only: needs #visor-comparar and the
// "Comparar" checkboxes rendered by resultados-render.js).
(function () {
  var dialogo = document.getElementById('visor-comparar');
  if (!dialogo) {
    return;
  }
  var MAX = 2;
  var contenedor = dialogo.querySelector('.visor-comparar-grid');

  function marcadas() {
    return document.querySelectorAll('.comparar-check:checked');
  }

  function imagenDe(check) {
    return check.closest('.imagen-comparable').querySelector('img');
  }

  function actualizarBarra() {
    var n = marcadas().length;
    var boton = document.querySelector('.comparar-abrir');
    var aviso = document.querySelector('.comparar-aviso');
    if (boton) {
      boton.disabled = n !== MAX;
    }
    if (aviso) {
      aviso.textContent = n === MAX
        ? 'Listo: 2 imagenes seleccionadas.'
        : 'Marca 2 imagenes con "Comparar" para verlas lado a lado (' + n + ' de ' + MAX + ').';
    }
  }

  document.addEventListener('change', function (evento) {
    var check = evento.target;
    if (!check.classList || !check.classList.contains('comparar-check')) {
      return;
    }
    if (marcadas().length > MAX) {
      check.checked = false;
      var aviso = document.querySelector('.comparar-aviso');
      if (aviso) {
        aviso.textContent = 'Solo se pueden comparar 2 imagenes. Desmarca una antes de elegir otra.';
      }
      return;
    }
    actualizarBarra();
  });

  document.addEventListener('click', function (evento) {
    if (!evento.target.closest('.comparar-abrir')) {
      return;
    }
    var checks = marcadas();
    if (checks.length !== MAX) {
      return;
    }
    contenedor.replaceChildren();
    checks.forEach(function (check) {
      var origen = imagenDe(check);
      var figura = document.createElement('figure');
      var img = document.createElement('img');
      img.src = origen.src;
      img.alt = origen.alt;
      var pie = document.createElement('figcaption');
      pie.textContent = origen.dataset.estudio + ' - ' + origen.dataset.fecha;
      figura.append(img, pie);
      contenedor.appendChild(figura);
    });
    dialogo.showModal();
  });

  dialogo.querySelector('.visor-imagen-cerrar').addEventListener('click', function () {
    dialogo.close();
  });

  dialogo.addEventListener('click', function (evento) {
    if (evento.target === dialogo) {
      dialogo.close();
    }
  });
})();
