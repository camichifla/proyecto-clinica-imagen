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

    // Read-only card for receptionists: their branch, hours, studies and professionals.
    function renderMiSede(sede) {
        const seccion = document.getElementById('mi-sede');
        seccion.hidden = !sede;
        if (!sede) {
            return;
        }
        const titulo = document.createElement('h2');
        titulo.textContent = 'Mi sede: ' + sede.nombre;
        const nodos = [titulo, parrafo(sede.direccion || 'Sin dirección cargada.')];
        const bloque = (encabezado, items) => {
            const h = document.createElement('h3');
            h.textContent = encabezado;
            const ul = document.createElement('ul');
            items.forEach((texto) => {
                const li = document.createElement('li');
                li.textContent = texto;
                ul.appendChild(li);
            });
            nodos.push(h, items.length ? ul : parrafo('No hay datos.'));
        };
        bloque('Horarios', DIAS_SEMANA.map((dia, i) => {
            const turnos = sede.horarios.filter((t) => t.dia === i + 1).map((t) => t.apertura + ' - ' + t.cierre);
            return dia + ': ' + (turnos.join(', ') || 'Cerrado');
        }));
        bloque('Estudios', sede.estudios);
        bloque('Profesionales', sede.profesionales);
        seccion.replaceChildren(...nodos);
    }

    function render(data) {
        renderMiSede(data.miSede);
        const totalHoy = Object.values(data.hoyPorEstado).reduce((a, b) => a + b, 0);
        document.getElementById('panel-pendientes').textContent = String(data.pendientes);
        document.getElementById('panel-hoy').textContent = String(totalHoy);
        document.getElementById('panel-proximos').textContent = String(data.proximos7Confirmadas);

        if (!data.citasHoy.length) {
            listaHoy.replaceChildren(parrafo('No hay citas para hoy.'));
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
