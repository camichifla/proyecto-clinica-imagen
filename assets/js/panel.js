(() => {
    const listaHoy = document.getElementById('citas-hoy');
    if (!listaHoy) {
        return;
    }
    const mensajeError = document.querySelector('[data-mensaje-error]');

    function renderFila(cita) {
        const tr = document.createElement('tr');
        tr.appendChild(celda('Código', cita.codigo));
        tr.appendChild(celda('Hora', cita.hora));
        tr.appendChild(celda('Paciente', cita.pacienteLabel));
        tr.appendChild(celda('Estudio', cita.estudioLabel));
        tr.appendChild(celda('Sede', cita.sucursalNombre || '-'));
        tr.appendChild(celda('Profesional', cita.profesionalLabel));
        tr.appendChild(celdaEstado(cita.estado, cita.estadoLabel));
        return tr;
    }

    function render(data) {
        const totalHoy = Object.values(data.hoyPorEstado).reduce((a, b) => a + b, 0);
        document.getElementById('panel-pendientes').textContent = String(data.pendientes);
        document.getElementById('panel-hoy').textContent = String(totalHoy);
        document.getElementById('panel-proximos').textContent = String(data.proximos7Confirmadas);

        if (!data.citasHoy.length) {
            const p = document.createElement('p');
            p.textContent = 'No hay citas para hoy.';
            listaHoy.replaceChildren(p);
            return;
        }
        listaHoy.replaceChildren(tabla(
            ['Código', 'Hora', 'Paciente', 'Estudio', 'Sede', 'Profesional', 'Estado'],
            data.citasHoy.map(renderFila)
        ));
    }

    async function cargar() {
        const data = await apiJson('api/panel.php');
        if (!data || data.error || !data.citasHoy) {
            mensajeError.textContent = 'No se pudo actualizar el panel. Reintentamos en un minuto.';
            mensajeError.hidden = false;
            return;
        }
        mensajeError.hidden = true;
        render(data);
    }

    async function init() {
        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }
        cargar();
        setInterval(cargar, 60000);
    }

    init();
})();
