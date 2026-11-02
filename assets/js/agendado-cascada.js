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

    function sedeCubreEstudio(s, estudio) {
        if (!s) {
            return false;
        }
        var estudioPermitido = s.estudios.length === 0 || s.estudios.indexOf(estudio) !== -1;
        if (!estudioPermitido) {
            return false;
        }
        return opcionesProfesional.some(function (opcion) {
            if (opcion.value === '' || opcion.value === 'cualquiera') {
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
            sucursalSelect.dispatchEvent(new Event('change'));
        }
    }

    function aplicarFiltroProfesional() {
        var s = sucursalActual();
        var estudio = estudioSelect.value;
        var seleccionValida = false;
        opcionesProfesional.forEach(function (opcion) {
            if (opcion.value === '' || opcion.value === 'cualquiera') {
                opcion.hidden = false;
                if (opcion.value === profesionalSelect.value) {
                    seleccionValida = true;
                }
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
        apiJson('api/horas-ocupadas.php?' + params.toString())
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

    function seleccionada() {
        return fechaInput.value ? parseISO(fechaInput.value) : null;
    }

    fechaInput.diaHabilitado = function (fecha) {
        return diaAbierto(sucursalActual(), fecha);
    };
    function refrescarCalendario() {
        if (fechaInput.refrescarCalendario) {
            fechaInput.refrescarCalendario();
        }
    }

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
