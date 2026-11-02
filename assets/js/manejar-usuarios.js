(() => {
    const seccionCuentas = document.getElementById('seccion-cuentas');
    const tablaCuentasContenedor = document.getElementById('cuentas-lista');
    const seccionAsignaciones = document.getElementById('seccion-asignaciones');
    const formMedicos = document.getElementById('filtros-medicos');
    const formPacientes = document.getElementById('filtros-pacientes');
    const botonGuardarPacientes = document.getElementById('asignacion-guardar');
    const medicosContenedor = document.getElementById('medicos-lista');
    const pacientesContenedor = document.getElementById('pacientes-lista');
    const pasoMedico = document.getElementById('asignacion-paso-medico');
    const pasoPacientes = document.getElementById('asignacion-paso-pacientes');
    const formFiltros = document.getElementById('filtros-cuentas');
    if (!tablaCuentasContenedor || !formMedicos || !formPacientes || !formFiltros || !document.getElementById('form-editar-cuenta')) {
        return;
    }

    let datos = null;
    let medicoId = '';
    const cambiosPacientes = new Map();

    const celdaSiNo = (si, columna) => celdaEstado(si ? 'si' : 'no', si ? 'Sí' : 'No', columna);

    function mostrarMensaje(seccion, texto, esError) {
        mostrarMensajeEn(seccion.querySelector('[data-mensaje]'), seccion.querySelector('[data-mensaje-error]'), texto, esError);
    }

    async function accionFila(campos) {
        return (await enviarPost('api/manejar-usuarios.php', campos))
            || { ok: false, error: ERROR_CONEXION };
    }

    function cuentasFiltradas(data) {
        const rol = formFiltros.elements.rol.value;
        return data.cuentas.filter((c) => (!rol || c.rol === rol)
            && coincidePalabras(formFiltros.elements.q.value, [c.apellido, c.nombre, c.email, c.ci]));
    }

    function renderCuentas(data) {
        tablaCuentasContenedor.innerHTML = '';
        const sinRol = !formFiltros.elements.rol.value;
        formFiltros.elements.q.disabled = sinRol;
        if (sinRol) {
            tablaCuentasContenedor.appendChild(parrafo('Elegí un rol para ver sus cuentas.'));
            return;
        }
        const cuentas = cuentasFiltradas(data);
        if (!cuentas.length) {
            tablaCuentasContenedor.appendChild(vacio(data.cuentas.length ? 'No hay cuentas para esos filtros.' : 'No hay cuentas.'));
            return;
        }

        const filas = cuentas.map((cuenta) => {
            const tr = document.createElement('tr');
            tr.appendChild(celda('Nombre', `${cuenta.apellido} ${cuenta.nombre}`.trim()));
            tr.appendChild(celda('Cédula', cuenta.ci || '—'));
            tr.appendChild(celda('Email', cuenta.email));
            tr.appendChild(celda('Rol', ROLES_ETIQUETA[cuenta.rol] || cuenta.rol));

            tr.appendChild(celdaSiNo(cuenta.verificado, 'Verificado'));
            tr.appendChild(celdaSiNo(cuenta.activo, 'Activo'));

            const tdAcciones = document.createElement('td');
            tdAcciones.dataset.label = '';
            tdAcciones.className = 'acciones-cuenta';
            const botonEditar = document.createElement('button');
            botonEditar.type = 'button';
            botonEditar.className = 'btn-secundario';
            botonEditar.textContent = 'Editar';
            botonEditar.addEventListener('click', () => abrirEdicion(cuenta));
            tdAcciones.appendChild(botonEditar);
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
                    const nombre = `${cuenta.apellido} ${cuenta.nombre}`.trim() || cuenta.email;
                    if (cuenta.activo && !(await confirmar(`¿Desactivar la cuenta de ${nombre}? No va a poder iniciar sesión.`, { peligro: true }))) {
                        return;
                    }
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
        tablaCuentasContenedor.appendChild(tabla(['Nombre', 'Cédula', 'Email', 'Rol', 'Verificado', 'Activo', ''], filas));
    }

    const seccionEditar = document.getElementById('seccion-editar-cuenta');
    const formEditar = document.getElementById('form-editar-cuenta');
    const contenedorHorarios = document.getElementById('editar-horarios');

    function mostrarBloque(bloque, visible) {
        bloque.hidden = !visible;
        bloque.querySelectorAll('input, select').forEach((el) => {
            el.disabled = !visible;
        });
    }

    function casilla(nombre, valor, texto, marcada) {
        const label = document.createElement('label');
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.name = nombre;
        input.value = valor;
        input.checked = marcada;
        label.append(input, ' ' + texto);
        return label;
    }

    function abrirEdicion(cuenta) {
        limpiarErroresCampos(formEditar);
        const f = formEditar.elements;
        f.cuenta_id.value = cuenta.cuenta_id;
        f.nombre.value = cuenta.nombre;
        f.apellido.value = cuenta.apellido;
        f.ci.value = cuenta.ci;
        f.email.value = cuenta.email;
        document.getElementById('editar-titulo').textContent = `Editar cuenta: ${cuenta.apellido} ${cuenta.nombre}`.trim();

        mostrarBloque(document.getElementById('editar-paciente'), cuenta.rol === 'paciente');
        f.direccion.value = cuenta.direccion;
        f.numero.value = cuenta.numero;

        mostrarBloque(document.getElementById('editar-recepcionista'), cuenta.rol === 'recepcionista');
        const sede = f.sucursal_id;
        sede.replaceChildren(new Option('Sin asignar', ''), ...datos.sucursales.map((s) => new Option(s.nombre, String(s.id))));
        sede.value = cuenta.sucursalId != null ? String(cuenta.sucursalId) : '';
        sede.dispatchEvent(new Event('change'));

        const esProfesional = cuenta.rol === 'profesional';
        mostrarBloque(document.getElementById('editar-profesional'), esProfesional);
        if (esProfesional) {
            const sedes = cuenta.sedes.map(String);
            document.getElementById('editar-sedes').replaceChildren(...datos.sucursales.map((s) =>
                casilla('sucursales[]', String(s.id), s.nombre + (Number(s.activo) ? '' : ' (inactiva)'), sedes.includes(String(s.id)))));
            document.getElementById('editar-estudios').replaceChildren(...datos.estudios_catalogo
                .filter((e) => e.activo || cuenta.especializacion.includes(e.clave))
                .map((e) => casilla('especializacion[]', e.clave, e.etiqueta + (e.activo ? '' : ' (inactivo)'), cuenta.especializacion.includes(e.clave))));
            contenedorHorarios.replaceChildren(...cuenta.horarios.map(filaHorario));
        }
        seccionEditar.hidden = false;
        seccionEditar.scrollIntoView({ block: 'start' });
    }

    function cerrarEdicion() {
        seccionEditar.hidden = true;
    }

    formEditar.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        limpiarErroresCampos(formEditar);
        const cuerpo = new FormData(formEditar);
        cuerpo.set('horarios', JSON.stringify(turnosDeContenedor(contenedorHorarios)));
        const data = await apiJson('api/manejar-usuarios.php', { method: 'POST', body: cuerpo });
        if (!data) {
            mostrarMensaje(seccionCuentas, ERROR_CONEXION, true);
            return;
        }
        if (data.errores) {
            const sueltos = mostrarErroresCampos(formEditar, data.errores);
            if (sueltos.length) {
                mostrarMensaje(seccionCuentas, sueltos.join(' '), true);
            }
            return;
        }
        if (!data.ok) {
            mostrarMensaje(seccionCuentas, data.error || 'Ocurrió un error inesperado.', true);
            return;
        }
        mostrarMensaje(seccionCuentas, data.email_pendiente
            ? `Cuenta actualizada. Se envió un correo a ${data.email_pendiente}; el cambio de correo se aplica cuando se confirme la nueva dirección (vence en 24 horas). Hasta entonces la cuenta sigue con el correo actual.`
            : 'Cuenta actualizada.', false);
        cerrarEdicion();
        cargarDatos();
    });
    document.getElementById('horario-agregar').addEventListener('click', () => {
        contenedorHorarios.appendChild(filaHorario({ dia: 1, apertura: '08:00', cierre: '20:00' }));
    });
    document.getElementById('editar-cancelar').addEventListener('click', cerrarEdicion);

    function filtrarPersonas(personas, form) {
        return personas.filter((p) => coincidePalabras(form.elements.q.value, [p.apellido, p.nombre, p.email, p.ci]));
    }

    const nombreCompleto = (p) => `${p.apellido} ${p.nombre}`.trim();

    function renderMedicos(data) {
        medicosContenedor.innerHTML = '';
        const medicos = filtrarPersonas(data.medicos, formMedicos);
        if (!medicos.length) {
            medicosContenedor.appendChild(vacio(data.medicos.length ? 'No hay médicos para esa búsqueda.' : 'No hay médicos.'));
            return;
        }
        const filas = medicos.map((medico) => {
            const tr = document.createElement('tr');
            tr.appendChild(celda('Nombre', nombreCompleto(medico)));
            tr.appendChild(celda('Cédula', medico.ci || '—'));
            tr.appendChild(celda('Email', medico.email || '—'));
            const td = document.createElement('td');
            td.dataset.label = '';
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'btn-secundario';
            boton.textContent = 'Seleccionar';
            boton.addEventListener('click', () => {
                medicoId = String(medico.id);
                formPacientes.reset();
                cambiosPacientes.clear();
                renderAsignaciones(data);
            });
            td.appendChild(boton);
            tr.appendChild(td);
            return tr;
        });
        medicosContenedor.appendChild(tabla(['Nombre', 'Cédula', 'Email', ''], filas));
    }

    function renderPacientes(data) {
        botonGuardarPacientes.disabled = !cambiosPacientes.size;
        pacientesContenedor.innerHTML = '';
        const vinculados = new Set(data.asignaciones.filter((a) => String(a.medico_id) === medicoId).map((a) => a.paciente_id));
        const marcado = (p) => (cambiosPacientes.has(p.id) ? cambiosPacientes.get(p.id) : vinculados.has(p.id));
        const pacientes = filtrarPersonas(data.pacientes, formPacientes)
            .sort((a, b) => marcado(b) - marcado(a));
        if (!pacientes.length) {
            pacientesContenedor.appendChild(vacio(data.pacientes.length ? 'No hay pacientes para esa búsqueda.' : 'No hay pacientes.'));
            return;
        }
        const filas = pacientes.map((paciente) => {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.dataset.label = 'Asignado';
            const check = document.createElement('input');
            check.type = 'checkbox';
            check.className = 'check-celda';
            check.checked = marcado(paciente);
            check.setAttribute('aria-label', `Asignar a ${nombreCompleto(paciente)}`);
            check.addEventListener('change', () => {
                if (check.checked === vinculados.has(paciente.id)) {
                    cambiosPacientes.delete(paciente.id);
                } else {
                    cambiosPacientes.set(paciente.id, check.checked);
                }
                renderPacientes(data);
            });
            td.appendChild(check);
            tr.appendChild(td);
            tr.appendChild(celda('Nombre', nombreCompleto(paciente)));
            tr.appendChild(celda('Cédula', paciente.ci || '—'));
            tr.appendChild(celda('Email', paciente.email || '—'));
            return tr;
        });
        pacientesContenedor.appendChild(tabla(['Asignado', 'Nombre', 'Cédula', 'Email'], filas));
    }

    function renderAsignaciones(data) {
        const medico = data.medicos.find((m) => String(m.id) === medicoId);
        if (!medico) {
            medicoId = '';
        }
        pasoMedico.hidden = !!medicoId;
        pasoPacientes.hidden = !medicoId;
        if (!medicoId) {
            renderMedicos(data);
            return;
        }
        document.getElementById('asignacion-medico-titulo').textContent = `Médico: ${nombreCompleto(medico)}`;
        renderPacientes(data);
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
        datos = data;
        renderCuentas(data);
        renderAsignaciones(data);
        poblarEspecializaciones(data.estudios);
        poblarCuentasCambioRol(data);
    }

    botonGuardarPacientes.addEventListener('click', async () => {
        botonGuardarPacientes.disabled = true;
        const fallos = [];
        for (const [pacienteId, quiere] of cambiosPacientes) {
            const resultado = await accionFila({
                accion: quiere ? 'asignar_paciente' : 'quitar_asignacion',
                medico_id: medicoId,
                paciente_id: String(pacienteId),
            });
            if (!resultado.ok) {
                fallos.push(resultado.error || ERROR_CONEXION);
            }
        }
        cambiosPacientes.clear();
        formPacientes.reset();
        formMedicos.reset();
        medicoId = '';
        if (fallos.length) {
            mostrarMensaje(seccionAsignaciones, [...new Set(fallos)].join(' '), true);
        } else {
            mostrarMensaje(seccionAsignaciones, 'Cambios guardados.', false);
        }
        cargarDatos();
    });

    document.getElementById('asignacion-cambiar').addEventListener('click', () => {
        medicoId = '';
        cambiosPacientes.clear();
        formMedicos.reset();
        if (datos) {
            renderAsignaciones(datos);
        }
    });
    [formMedicos, formPacientes].forEach((form) => {
        form.addEventListener('submit', (evento) => {
            evento.preventDefault();
            if (datos) {
                renderAsignaciones(datos);
            }
        });
        filtrarAlCambiar(form);
    });
    formFiltros.addEventListener('submit', (evento) => {
        evento.preventDefault();
        if (datos) {
            renderCuentas(datos);
        }
    });
    filtrarAlCambiar(formFiltros);

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
