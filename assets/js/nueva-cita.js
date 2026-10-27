(() => {
    const formNueva = document.getElementById('form-nueva-cita');
    if (!formNueva) {
        return;
    }
    const mensajeOk = document.querySelector('[data-mensaje]');
    const mensajeError = document.querySelector('[data-mensaje-error]');
    const mostrarMensaje = crearMostrarMensaje(mensajeOk, mensajeError);

    // Staff booking ("Nueva cita"), fed by the booking catalog; Hora only lists free hours.
    const nueva = formNueva.elements;
    let reserva = null;
    let sincronizando = false; // programmatic updates must not re-trigger the change listener

    const opcion = (valor, texto) => new Option(texto, valor);
    const notificar = (select) => select.dispatchEvent(new Event('change', { bubbles: true }));
    const botonCrear = formNueva.querySelector('button[type="submit"]');

    // Fields unlock top to bottom: each one waits for the required ones above it
    // (Profesional is optional, so it doesn't hold back Fecha).
    function desbloquear() {
        const conPaciente = nueva.paciente_id.value !== '';
        const conSede = conPaciente && nueva.sucursal_id.value !== '';
        const conEstudio = conSede && nueva.estudio.value !== '';
        const conFecha = conEstudio && nueva.fecha.value !== '';
        nueva.sucursal_id.disabled = !conPaciente;
        nueva.estudio.disabled = !conSede;
        nueva.profesional_id.disabled = !conEstudio;
        nueva.fecha.disabled = !conEstudio;
        nueva.hora.disabled = !conFecha || nueva.hora.options.length <= 1;
        botonCrear.disabled = !conFecha || nueva.hora.value === '';
    }

    // Professionals of the branch (and, if given, doing the study).
    function poblarProfesionalesDe(select, sedeId, estudio, vacio) {
        const sede = reserva.sucursales[sedeId];
        const validos = reserva.profesionales.filter((p) => sede && sede.profesionales.includes(p.id)
            && (!estudio || p.especializaciones.includes(estudio)));
        const previo = select.value;
        select.replaceChildren(opcion('', vacio), ...validos.map((p) => opcion(String(p.id), p.apellido + ', ' + p.nombre)));
        select.value = validos.some((p) => String(p.id) === previo) ? previo : '';
    }

    let pedido = 0;
    async function refrescarHoras() {
        const actual = ++pedido;
        const mostrar = (texto, horas = []) => {
            nueva.hora.replaceChildren(opcion('', texto), ...horas.map((h) => opcion(h, h)));
            desbloquear();
        };
        if (!nueva.sucursal_id.value || !nueva.estudio.value || !nueva.fecha.value) {
            mostrar('Elegí sede, estudio y fecha primero');
            return;
        }
        nueva.hora.disabled = true;
        const data = await apiJson('api/turnos.php?' + new URLSearchParams({
            disponibles: '1',
            sucursal_id: nueva.sucursal_id.value,
            fecha: nueva.fecha.value,
            profesional_id: nueva.profesional_id.value,
            estudio: nueva.estudio.value,
        }));
        if (actual !== pedido) {
            return;
        }
        if (!data || !data.horas) {
            mostrarMensaje(textoErrores(data), true);
            return;
        }
        mostrar(data.horas.length ? 'Elegí un horario' : 'Sin horarios disponibles ese día', data.horas);
    }

    function limpiar() {
        sincronizando = true;
        ['paciente_id', 'estudio', 'profesional_id', 'fecha'].forEach((n) => {
            nueva[n].value = '';
            notificar(nueva[n]);
        });
        sincronizando = false;
        poblarProfesionalesDe(nueva.profesional_id, nueva.sucursal_id.value, '', 'Cualquiera');
        refrescarHoras();
    }

    function iniciar(catalogo, rango) {
        reserva = catalogo;
        const sedes = catalogo.sucursalesOrden;
        if (sedes.length > 1) {
            nueva.sucursal_id.appendChild(opcion('', 'Elegí una sede'));
        }
        poblarSucursales(nueva.sucursal_id, sedes);
        // A receptionist only has their own branch: nothing to choose.
        nueva.sucursal_id.closest('.campo').hidden = sedes.length <= 1;
        nueva.paciente_id.replaceChildren(opcion('', 'Elegí un paciente'), ...catalogo.pacientes.map((p) => opcion(String(p.id), p.etiqueta)));
        nueva.estudio.appendChild(opcion('', 'Elegí un estudio'));
        poblarEstudios(nueva.estudio, catalogo.estudios);
        poblarProfesionalesDe(nueva.profesional_id, nueva.sucursal_id.value, '', 'Cualquiera');
        nueva.fecha.setAttribute('min', rango.min);
        nueva.fecha.setAttribute('max', rango.max);
        refrescarHoras();
    }

    formNueva.addEventListener('change', (evento) => {
        if (sincronizando) {
            return;
        }
        if (evento.target === nueva.sucursal_id || evento.target === nueva.estudio) {
            poblarProfesionalesDe(nueva.profesional_id, nueva.sucursal_id.value, nueva.estudio.value, 'Cualquiera');
        }
        if (evento.target !== nueva.paciente_id && evento.target !== nueva.hora) {
            refrescarHoras();
        }
        desbloquear();
    });

    formNueva.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        limpiarErroresCampos(formNueva);
        const data = await enviarPost('api/turnos.php', { accion: 'crear', ...Object.fromEntries(new FormData(formNueva)) });
        if (!data || !data.ok) {
            const sueltos = data && data.errores ? mostrarErroresCampos(formNueva, data.errores) : [textoErrores(data)];
            mostrarMensaje(sueltos.length ? sueltos.join(' ') : 'Revisá los datos de la cita.', true);
            if (data && data.errores && data.errores.hora) {
                refrescarHoras();
            }
            return;
        }
        mostrarMensaje(`Cita ${data.codigo} creada y confirmada.`, false);
        limpiar();
    });

    async function init() {
        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }
        const data = await apiJson('api/turnos.php?catalogo=1');
        if (!data || !data.reserva) {
            mostrarMensaje(textoErrores(data), true);
            return;
        }
        iniciar(data.reserva, data.rangoReserva);
    }

    init();
})();
