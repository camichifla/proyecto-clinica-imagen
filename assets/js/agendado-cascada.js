// Booking cascade (study -> branch -> professional -> date -> time), shared by patient and doctor.
// Client filtering is UX only: the server re-validates every constraint.
//
// The including HTML must already have declared, before this
// <script src>:
//   var SUCURSALES = {...};   // see $datosSucursales in each .php
//   var HORA_ELEGIDA = '...'; // previously chosen time, to re-render after an error
// and the following ids in the DOM: #sucursal, #estudio, #profesional_id
// (with data-especializaciones on each <option>), #hora and #fecha (an
// <input type="date"> with min/max, turned into a calendar by selector-fecha.js;
// here it is only told which days the branch is open).
(function () {
    var sucursalSelect    = document.getElementById('sucursal');
    var estudioSelect     = document.getElementById('estudio');
    var profesionalSelect = document.getElementById('profesional_id');
    var horaSelect        = document.getElementById('hora');
    var fechaInput        = document.getElementById('fecha');
    if (!sucursalSelect || !estudioSelect || !profesionalSelect || !horaSelect || !fechaInput) {
        return;
    }
    var opcionesProfesional = Array.prototype.slice.call(profesionalSelect.options);

    function sucursalActual() {
        return SUCURSALES[sucursalSelect.value] || null;
    }

    // A branch "serves" a study if it has it enabled (or doesn't restrict
    // studies) AND has at least one assigned professional who performs that
    // study - covers the case of an allowed branch+study with nobody
    // actually doing it there.
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
            // Fires the branch 'change' so the professional is re-filtered
            // and date/time are reset if needed.
            sucursalSelect.dispatchEvent(new Event('change'));
        }
    }

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

    function aplicarHoras(fechaSeleccionada) {
        var s = sucursalActual();
        horaSelect.innerHTML = '';
        var turnos = (s && fechaSeleccionada) ? (s.horarios[diaSemanaISO(fechaSeleccionada)] || []) : [];
        var opciones = (s && fechaSeleccionada) ? generarHoras(turnos) : [];

        var placeholder = document.createElement('option');
        placeholder.value = '';
        if (!s || !fechaSeleccionada) {
            placeholder.textContent = 'Elegí sede y fecha primero';
        } else if (!opciones.length) {
            placeholder.textContent = 'Sin horarios disponibles ese día';
        } else {
            placeholder.textContent = 'Elegí un horario';
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
        quitarOcupadas(fechaSeleccionada);
    }

    // Removes the already taken times. If the fetch fails they are all kept (the
    // server constraint still protects); the time being edited
    // (HORA_ELEGIDA) is never removed. The counter discards stale responses.
    var pedidoHoras = 0;
    function quitarOcupadas(fecha) {
        var pedido = ++pedidoHoras;
        if (!sucursalSelect.value || !fecha || !estudioSelect.value) {
            return;
        }
        var params = new URLSearchParams({
            sucursal_id: sucursalSelect.value,
            fecha: aISO(fecha),
            estudio: estudioSelect.value
        });
        if (profesionalSelect.value) {
            params.set('profesional_id', profesionalSelect.value);
        }
        fetch('api/horas-ocupadas.php?' + params.toString())
            .then(function (respuesta) { return respuesta.ok ? respuesta.json() : null; })
            .then(function (data) {
                if (pedido !== pedidoHoras || !data || !Array.isArray(data.ocupadas)) {
                    return;
                }
                Array.prototype.slice.call(horaSelect.options).forEach(function (opcion) {
                    if (opcion.value !== '' && opcion.value !== HORA_ELEGIDA && data.ocupadas.indexOf(opcion.value) !== -1) {
                        horaSelect.removeChild(opcion);
                    }
                });
            })
            .catch(function () {});
    }

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
    function seleccionada() {
        return fechaInput.value ? parseISO(fechaInput.value) : null;
    }

    // The shared calendar (selector-fecha.js) only offers days the chosen sede is open.
    fechaInput.diaHabilitado = function (fecha) {
        return diaAbierto(sucursalActual(), fecha);
    };
    function refrescarCalendario() {
        if (fechaInput.refrescarCalendario) {
            fechaInput.refrescarCalendario();
        }
    }

    // Steps go top to bottom: each control stays locked (faded, no arrow) until the previous one is chosen.
    // Study -> Branch -> Professional (optional) and Date -> Time (aplicarHoras locks it without a date).
    // A locked step is also emptied, so clearing an earlier choice resets everything below it.
    function aplicarBloqueos() {
        if (!estudioSelect.value) {
            sucursalSelect.value = '';
        }
        if (!sucursalSelect.value) {
            profesionalSelect.value = '';
            if (fechaInput.value) {
                fechaInput.value = '';
            }
        }
        sucursalSelect.disabled = !estudioSelect.value;
        profesionalSelect.disabled = !sucursalSelect.value;
        fechaInput.disabled = !sucursalSelect.value;
    }

    estudioSelect.addEventListener('change', function () {
        aplicarFiltroSede();
        aplicarFiltroProfesional();
        aplicarBloqueos();
        aplicarHoras(seleccionada());
    });
    profesionalSelect.addEventListener('change', function () {
        aplicarHoras(seleccionada());
    });
    fechaInput.addEventListener('change', function () {
        aplicarHoras(seleccionada());
    });

    sucursalSelect.addEventListener('change', function () {
        aplicarFiltroProfesional();

        // A previously chosen date may now be closed at the new sucursal.
        if (seleccionada() && !diaAbierto(sucursalActual(), seleccionada())) {
            fechaInput.value = '';
        }
        aplicarBloqueos();
        refrescarCalendario();
        aplicarHoras(seleccionada());
    });

    aplicarFiltroSede();
    aplicarFiltroProfesional();
    aplicarBloqueos();
    aplicarHoras(seleccionada());
})();
