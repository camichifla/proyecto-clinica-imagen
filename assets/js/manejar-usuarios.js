(() => {
    const seccionCuentas = document.getElementById('seccion-cuentas');
    const tablaCuentasContenedor = document.getElementById('cuentas-lista');
    const seccionAsignaciones = document.getElementById('seccion-asignaciones');
    const formAsignar = document.querySelector('.form-asignar-paciente');
    const asignacionesContenedor = document.getElementById('asignaciones-lista');
    const datalistMedicos = document.getElementById('asignar-medico-id-lista');
    const datalistPacientes = document.getElementById('asignar-paciente-id-lista');
    if (!tablaCuentasContenedor || !formAsignar) {
        return;
    }

    let buscadorComboCargado = false;

    function badge(si) {
        const span = document.createElement('span');
        span.className = 'estado-badge ' + (si ? 'estado-si' : 'estado-no');
        span.textContent = si ? 'Sí' : 'No';
        return span;
    }

    function mostrarMensaje(seccion, texto, esError) {
        mostrarMensajeEn(seccion.querySelector('[data-mensaje]'), seccion.querySelector('[data-mensaje-error]'), texto, esError);
    }

    async function accionFila(campos) {
        return (await enviarPost('api/manejar-usuarios.php', campos))
            || { ok: false, error: ERROR_CONEXION };
    }

    function renderCuentas(data) {
        tablaCuentasContenedor.innerHTML = '';
        if (!data.cuentas.length) {
            const p = document.createElement('p');
            p.textContent = 'Todavía no hay cuentas registradas.';
            tablaCuentasContenedor.appendChild(p);
            return;
        }

        const filas = data.cuentas.map((cuenta) => {
            const tr = document.createElement('tr');
            tr.appendChild(celda('Nombre', `${cuenta.apellido} ${cuenta.nombre}`.trim()));
            tr.appendChild(celda('Email', cuenta.email));
            tr.appendChild(celda('Rol', ROLES_ETIQUETA[cuenta.rol] || cuenta.rol));

            const tdVerificado = document.createElement('td');
            tdVerificado.dataset.label = 'Verificado';
            tdVerificado.appendChild(badge(cuenta.verificado));
            tr.appendChild(tdVerificado);

            const tdActivo = document.createElement('td');
            tdActivo.dataset.label = 'Activo';
            tdActivo.appendChild(badge(cuenta.activo));
            tr.appendChild(tdActivo);

            const tdAcciones = document.createElement('td');
            tdAcciones.dataset.label = '';
            if (cuenta.cuenta_id === data.cuenta_actual_id) {
                const span = document.createElement('span');
                span.className = 'etiqueta-tu-cuenta';
                span.textContent = 'Tu cuenta';
                tdAcciones.appendChild(span);
            } else {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 'btn-secundario';
                boton.textContent = cuenta.activo ? 'Desactivar' : 'Reactivar';
                if (cuenta.rol === 'administrador' && cuenta.activo && data.admin_activos_count <= 1) {
                    boton.disabled = true;
                    boton.title = 'No se puede desactivar el último administrador activo.';
                }
                boton.addEventListener('click', async () => {
                    const resultado = await accionFila({ accion: 'toggle_activo', cuenta_id: String(cuenta.cuenta_id) });
                    if (resultado.ok) {
                        mostrarMensaje(seccionCuentas, 'Estado de la cuenta actualizado.', false);
                        cargarDatos();
                    } else if (resultado.error) {
                        mostrarMensaje(seccionCuentas, resultado.error, true);
                    }
                });
                tdAcciones.appendChild(boton);
            }
            tr.appendChild(tdAcciones);
            return tr;
        });
        tablaCuentasContenedor.appendChild(tabla(['Nombre', 'Email', 'Rol', 'Verificado', 'Activo', ''], filas));
    }

    function renderAsignaciones(data) {
        if (!buscadorComboCargado) {
            poblarDatalist(datalistMedicos, data.medicos, (m) => `${m.apellido}, ${m.nombre}`);
            poblarPacientes(datalistPacientes, data.pacientes);
        }

        asignacionesContenedor.innerHTML = '';
        if (!data.asignaciones.length) {
            const p = document.createElement('p');
            p.textContent = 'Todavía no hay asignaciones.';
            asignacionesContenedor.appendChild(p);
            return;
        }

        const filas = data.asignaciones.map((asignacion) => {
            const tr = document.createElement('tr');
            tr.appendChild(celda('Médico', `${asignacion.medico_apellido}, ${asignacion.medico_nombre}`));
            tr.appendChild(celda('Paciente', `${asignacion.paciente_apellido}, ${asignacion.paciente_nombre}`));

            const tdAcciones = document.createElement('td');
            tdAcciones.dataset.label = '';
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'btn-secundario';
            boton.textContent = 'Quitar';
            boton.addEventListener('click', async () => {
                const resultado = await accionFila({
                    accion: 'quitar_asignacion',
                    medico_id: String(asignacion.medico_id),
                    paciente_id: String(asignacion.paciente_id),
                });
                if (resultado.ok) {
                    mostrarMensaje(seccionAsignaciones, 'Asignación eliminada.', false);
                    cargarDatos();
                } else if (resultado.error) {
                    mostrarMensaje(seccionAsignaciones, resultado.error, true);
                }
            });
            tdAcciones.appendChild(boton);
            tr.appendChild(tdAcciones);
            return tr;
        });
        asignacionesContenedor.appendChild(tabla(['Médico', 'Paciente', ''], filas));
    }

    function poblarEspecializaciones(estudios) {
        document.querySelectorAll('#campo-especializacion .check-grid, #campo-especializacion-cambio .check-grid').forEach((grilla) => {
            if (grilla.children.length) {
                return;
            }
            estudios.forEach((estudio) => {
                const label = document.createElement('label');
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.name = 'especializacion[]';
                input.value = estudio.clave;
                label.append(input, ' ' + estudio.etiqueta);
                grilla.appendChild(label);
            });
        });
    }

    // Staff accounts whose role can be changed (not your own, not patients).
    function poblarCuentasCambioRol(data) {
        const select = document.getElementById('cambio-cuenta');
        const elegida = select.value;
        select.replaceChildren(select.options[0]);
        data.cuentas
            .filter((c) => c.rol !== 'paciente' && c.cuenta_id !== data.cuenta_actual_id)
            .forEach((c) => {
                const opcion = document.createElement('option');
                opcion.value = String(c.cuenta_id);
                opcion.textContent = `${c.apellido} ${c.nombre}`.trim() + ' — ' + (ROLES_ETIQUETA[c.rol] || c.rol);
                select.appendChild(opcion);
            });
        select.value = elegida;
    }

    async function cargarDatos() {
        const data = await apiJson('api/manejar-usuarios.php');
        if (!data) {
            return;
        }
        renderCuentas(data);
        renderAsignaciones(data);
        poblarEspecializaciones(data.estudios);
        poblarCuentasCambioRol(data);

        if (!buscadorComboCargado) {
            buscadorComboCargado = true;
            await cargarScript('assets/js/buscador-combo.js');
        }
    }

    formAsignar.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const medicoId = document.getElementById('asignar-medico-id').value;
        const pacienteId = document.getElementById('asignar-paciente-id').value;
        if (!medicoId || !pacienteId) {
            mostrarMensaje(seccionAsignaciones, 'Elegí un médico y un paciente validos.', true);
            return;
        }
        const resultado = await accionFila({ accion: 'asignar_paciente', medico_id: medicoId, paciente_id: pacienteId });
        if (resultado.ok) {
            mostrarMensaje(seccionAsignaciones, 'Paciente asignado correctamente.', false);
            formAsignar.reset();
            document.getElementById('asignar-medico-id').value = '';
            document.getElementById('asignar-paciente-id').value = '';
            cargarDatos();
        } else if (resultado.error) {
            mostrarMensaje(seccionAsignaciones, resultado.error, true);
        }
    });

    const formCambioRol = document.getElementById('form-cambiar-rol');
    if (formCambioRol) {
        const campoEspCambio = document.getElementById('campo-especializacion-cambio');
        formCambioRol.elements.rol.addEventListener('change', () => {
            campoEspCambio.hidden = formCambioRol.elements.rol.value !== 'profesional';
        });
        formCambioRol.addEventListener('submit', () => {
            document.getElementById('seccion-cambiar-rol').querySelector('[data-mensaje]').hidden = true;
        });
        formCambioRol.addEventListener('form-api:respuesta', (evento) => {
            if (!evento.detail.ok) {
                return;
            }
            const aviso = document.getElementById('seccion-cambiar-rol').querySelector('[data-mensaje]');
            aviso.textContent = 'Rol actualizado.';
            aviso.hidden = false;
            formCambioRol.reset();
            ['cuenta_id', 'rol'].forEach((nombre) => formCambioRol.elements[nombre].dispatchEvent(new Event('change')));
            cargarDatos();
        });
    }

    function mostrarAvisoStaff() {
        if (new URL(window.location.href).searchParams.get('staff_ok')) {
            const seccion = document.getElementById('seccion-crear-staff');
            const aviso = seccion && seccion.querySelector('[data-mensaje]');
            if (aviso) {
                aviso.textContent = 'Cuenta creada correctamente.';
                aviso.hidden = false;
            }
        }
    }

    (async () => {
        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }
        mostrarAvisoStaff();
        cargarDatos();
    })();
})();
