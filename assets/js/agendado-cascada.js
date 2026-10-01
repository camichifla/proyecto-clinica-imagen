// Cascada de agendado: estudio -> sucursal -> profesional -> fecha (calendario
// custom) -> hora. Compartido por agendar-cita.php (paciente) y
// agendar-cita-medico.php (medico agendando para un paciente) — el flujo, los
// ids de campo y las reglas de filtrado son identicos entre ambas pantallas,
// solo cambia quien es el "dueno" de la cita (logica de servidor, no de este
// script). Client filtering is UX only — the server re-validates every
// constraint independently (design.md Server-Side Re-Validation).
//
// Requiere que el HTML que lo incluye ya haya declarado, antes de este
// <script src>:
//   var SUCURSALES = {...};   // ver $datosSucursales en cada .php
//   var HORA_ELEGIDA = '...'; // hora previamente elegida, para re-render tras error
// y los siguientes ids/clases en el DOM: #sucursal, #estudio, #profesional_id
// (con data-especializaciones en cada <option>), #hora, .selector-fecha
// (con data-min/data-max), #fecha-boton (.selector-fecha-texto), #fecha,
// #calendario (.calendario-mes-actual, .calendario-dias-semana,
// .calendario-grid, [data-dir="-1"], [data-dir="1"]).
(function () {
  var sucursalSelect    = document.getElementById('sucursal');
  var estudioSelect     = document.getElementById('estudio');
  var profesionalSelect = document.getElementById('profesional_id');
  var horaSelect        = document.getElementById('hora');
  var contenedor        = document.querySelector('.selector-fecha');
  if (!sucursalSelect || !estudioSelect || !profesionalSelect || !horaSelect || !contenedor) {
    return;
  }
  var opcionesProfesional = Array.prototype.slice.call(profesionalSelect.options);

  function sucursalActual() {
    return SUCURSALES[sucursalSelect.value] || null;
  }

  // Una sede "sirve" un estudio si lo tiene habilitado (o no restringe
  // estudios) Y tiene al menos un profesional asignado que atienda ese
  // estudio — cubre el caso de sede+estudio permitidos pero sin nadie
  // que realmente lo haga ahi.
  function sedeCubreEstudio(s, estudio) {
    if (!s) {
      return false;
    }
    var estudioPermitido = s.estudios.length === 0 || s.estudios.indexOf(estudio) !== -1;
    if (!estudioPermitido) {
      return false;
    }
    return opcionesProfesional.some(function (opcion) {
      if (opcion.value === '') {
        return false;
      }
      var especializaciones = JSON.parse(opcion.dataset.especializaciones || '[]');
      return especializaciones.indexOf(estudio) !== -1 && s.profesionales.indexOf(Number(opcion.value)) !== -1;
    });
  }

  // --- Sede options filtered by estudio: el estudio se elige primero, asi
  // que solo mostramos sedes que realmente lo cubren. ---
  function aplicarFiltroSede() {
    var estudio = estudioSelect.value;
    Array.prototype.forEach.call(sucursalSelect.options, function (opcion) {
      if (opcion.value === '') {
        opcion.hidden = false;
        return;
      }
      var visible = !estudio || sedeCubreEstudio(SUCURSALES[opcion.value], estudio);
      opcion.hidden = !visible;
    });
    var seleccionActual = sucursalSelect.options[sucursalSelect.selectedIndex];
    if (seleccionActual && seleccionActual.hidden) {
      sucursalSelect.value = '';
      // Dispara el 'change' de sucursal para que se re-filtre
      // profesional y se resetee fecha/hora si correspondia.
      sucursalSelect.dispatchEvent(new Event('change'));
    }
  }

  // --- Profesional options filtered by branch AND estudio ---
  function aplicarFiltroProfesional() {
    var s = sucursalActual();
    var estudio = estudioSelect.value;
    var seleccionValida = false;
    opcionesProfesional.forEach(function (opcion) {
      if (opcion.value === '') {
        opcion.hidden = false;
        return;
      }
      var coincideEstudio = !estudio || JSON.parse(opcion.dataset.especializaciones || '[]').indexOf(estudio) !== -1;
      var coincideSucursal = !!s && s.profesionales.indexOf(Number(opcion.value)) !== -1;
      var visible = coincideEstudio && coincideSucursal;
      opcion.hidden = !visible;
      if (visible && opcion.value === profesionalSelect.value) {
        seleccionValida = true;
      }
    });
    if (!seleccionValida) {
      profesionalSelect.value = '';
    }
  }

  // --- 45-minute slot generation, mirrors includes/horarios.php ---
  function aMinutos(hhmm) {
    var partes = hhmm.split(':');
    return Number(partes[0]) * 60 + Number(partes[1]);
  }
  function aHHMM(minutos) {
    var h = Math.floor(minutos / 60);
    var m = minutos % 60;
    return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
  }
  // turnos = [['08:30','12:30'], ['15:00','19:00']]. Shifts are
  // generated independently, so the gap between split shifts never
  // appears as an option.
  function generarHoras(turnos) {
    var horas = [];
    turnos.forEach(function (t) {
      var min = aMinutos(t[0]);
      var fin = aMinutos(t[1]);
      for (; min <= fin; min += 45) {
        horas.push(aHHMM(min));
      }
    });
    return horas;
  }

  function diaSemanaISO(fecha) {
    return String((fecha.getDay() + 6) % 7 + 1);
  }

  function diaAbierto(s, fecha) {
    return !!(s && s.horarios[diaSemanaISO(fecha)]);
  }

  // --- Hora <select>, rebuilt whenever sucursal or fecha changes ---
  function aplicarHoras(fechaSeleccionada) {
    var s = sucursalActual();
    horaSelect.innerHTML = '';
    var turnos = (s && fechaSeleccionada) ? (s.horarios[diaSemanaISO(fechaSeleccionada)] || []) : [];
    var opciones = (s && fechaSeleccionada) ? generarHoras(turnos) : [];

    var placeholder = document.createElement('option');
    placeholder.value = '';
    if (!s || !fechaSeleccionada) {
      placeholder.textContent = 'Elegi sede y fecha primero';
    } else if (!opciones.length) {
      placeholder.textContent = 'Sin horarios disponibles ese dia';
    } else {
      placeholder.textContent = 'Elegi un horario';
    }
    horaSelect.appendChild(placeholder);

    opciones.forEach(function (hora) {
      var opcion = document.createElement('option');
      opcion.value = hora;
      opcion.textContent = hora;
      if (hora === HORA_ELEGIDA) {
        opcion.selected = true;
      }
      horaSelect.appendChild(opcion);
    });

    horaSelect.disabled = opciones.length === 0;
  }

  estudioSelect.addEventListener('change', function () {
    aplicarFiltroSede();
    aplicarFiltroProfesional();
  });

  // --- Calendar: no free typing, only clickable days within [min, max]
  // and open at the chosen sucursal ---
  var boton      = document.getElementById('fecha-boton');
  var textoBoton = boton.querySelector('.selector-fecha-texto');
  var oculto     = document.getElementById('fecha');
  var panel      = document.getElementById('calendario');
  var header     = panel.querySelector('.calendario-mes-actual');
  var filaDias   = panel.querySelector('.calendario-dias-semana');
  var grid       = panel.querySelector('.calendario-grid');
  var btnPrev    = panel.querySelector('[data-dir="-1"]');
  var btnNext    = panel.querySelector('[data-dir="1"]');

  var MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  var DIAS  = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do'];

  function pad(n) {
    return String(n).padStart(2, '0');
  }
  function parseISO(s) {
    var p = s.split('-').map(Number);
    return new Date(p[0], p[1] - 1, p[2]);
  }
  function aISO(d) {
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }
  function aVisible(d) {
    return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear();
  }
  function mismoDia(a, b) {
    return a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
  }

  var minFecha = parseISO(contenedor.dataset.min);
  var maxFecha = parseISO(contenedor.dataset.max);
  var seleccionada = oculto.value ? parseISO(oculto.value) : null;
  var vista = new Date((seleccionada || minFecha).getFullYear(), (seleccionada || minFecha).getMonth(), 1);

  if (seleccionada) {
    textoBoton.textContent = aVisible(seleccionada);
  }

  DIAS.forEach(function (d) {
    var span = document.createElement('span');
    span.textContent = d;
    filaDias.appendChild(span);
  });

  function enMesMin() {
    return vista.getFullYear() === minFecha.getFullYear() && vista.getMonth() === minFecha.getMonth();
  }
  function enMesMax() {
    return vista.getFullYear() === maxFecha.getFullYear() && vista.getMonth() === maxFecha.getMonth();
  }

  function render() {
    header.textContent = MESES[vista.getMonth()] + ' ' + vista.getFullYear();
    btnPrev.disabled = enMesMin();
    btnNext.disabled = enMesMax();
    grid.innerHTML = '';

    var primerDiaSemana = (new Date(vista.getFullYear(), vista.getMonth(), 1).getDay() + 6) % 7;
    var diasEnMes = new Date(vista.getFullYear(), vista.getMonth() + 1, 0).getDate();
    var hoy = new Date();

    for (var i = 0; i < primerDiaSemana; i++) {
      grid.appendChild(document.createElement('span'));
    }

    var s = sucursalActual();

    for (var dia = 1; dia <= diasEnMes; dia++) {
      var fecha = new Date(vista.getFullYear(), vista.getMonth(), dia);
      var celda = document.createElement('button');
      celda.type = 'button';
      celda.textContent = String(dia);
      celda.className = 'calendario-dia';

      if (fecha < minFecha || fecha > maxFecha || !diaAbierto(s, fecha)) {
        celda.disabled = true;
      } else {
        celda.addEventListener('click', (function (f) {
          return function () {
            seleccionada = f;
            oculto.value = aISO(f);
            textoBoton.textContent = aVisible(f);
            aplicarHoras(seleccionada);
            cerrar();
          };
        })(fecha));
      }

      if (mismoDia(fecha, seleccionada)) {
        celda.classList.add('calendario-dia-seleccionado');
      }
      if (mismoDia(fecha, hoy)) {
        celda.classList.add('calendario-dia-hoy');
      }

      grid.appendChild(celda);
    }
  }

  function cerrarSiAfuera(evento) {
    if (!contenedor.contains(evento.target)) {
      cerrar();
    }
  }
  function cerrarConEscape(evento) {
    if (evento.key === 'Escape') {
      cerrar();
    }
  }
  function abrir() {
    render();
    panel.hidden = false;
    boton.setAttribute('aria-expanded', 'true');
    document.addEventListener('click', cerrarSiAfuera);
    document.addEventListener('keydown', cerrarConEscape);
  }
  function cerrar() {
    panel.hidden = true;
    boton.setAttribute('aria-expanded', 'false');
    document.removeEventListener('click', cerrarSiAfuera);
    document.removeEventListener('keydown', cerrarConEscape);
  }

  boton.addEventListener('click', function (evento) {
    evento.stopPropagation();
    if (panel.hidden) {
      abrir();
    } else {
      cerrar();
    }
  });
  btnPrev.addEventListener('click', function () {
    if (!enMesMin()) {
      vista.setMonth(vista.getMonth() - 1);
      render();
    }
  });
  btnNext.addEventListener('click', function () {
    if (!enMesMax()) {
      vista.setMonth(vista.getMonth() + 1);
      render();
    }
  });

  sucursalSelect.addEventListener('change', function () {
    aplicarFiltroProfesional();

    // A previously chosen date may now be closed at the new sucursal.
    if (seleccionada && !diaAbierto(sucursalActual(), seleccionada)) {
      seleccionada = null;
      oculto.value = '';
      textoBoton.textContent = 'Elegi una fecha';
    }
    if (!panel.hidden) {
      render();
    }
    aplicarHoras(seleccionada);
  });

  // Restore prior state on load (a re-rendered form after a validation
  // error keeps the previously chosen sucursal/estudio/profesional/fecha).
  aplicarFiltroSede();
  aplicarFiltroProfesional();
  aplicarHoras(seleccionada);
})();
