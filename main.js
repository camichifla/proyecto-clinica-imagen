
const traducciones = {
    es: {
        bienvenida: "¡Hola, manín!",
        descripcion: "Esta es una página multiidioma."
    },
    en: {
        bienvenida: "Hey bro!",
        descripcion: "This is a multi-language website."
    }
};

function cambiarIdioma(idioma) {
    // Buscamos todos los elementos que tengan el atributo data-key
    const elementos = document.querySelectorAll('[data-key]');
    
    elementos.forEach(elemento => {
        const clave = elemento.getAttribute('data-key');
        // Cambiamos el texto interno por el del idioma seleccionado
        elemento.textContent = traducciones[idioma][clave];
    });

    // Guarda la selección del usuario para la próxima vez que entre
    localStorage.setItem('idiomaPreferido', idioma);
}

// Cargar el idioma guardado o usar español por defecto al abrir la página
const idiomaGuardado = localStorage.getItem('idiomaPreferido') || 'es';
cambiarIdioma(idiomaGuardado);

document.addEventListener('DOMContentLoaded', () => {
    const formTurno = document.getElementById('form-turno');
    const inputFecha = document.getElementById('fecha_hora');
    const modal = document.getElementById('modal-feedback');

    // 1. Validación en tiempo real 
    inputFecha.addEventListener('input', () => {
        const fechaSeleccionada = new Date(inputFecha.value);
        const ahora = new Date();
        const errorSpan = document.getElementById('error-fecha');

        if (fechaSeleccionada < ahora) {
            inputFecha.classList.add('input-error');
            errorSpan.textContent = "No puedes seleccionar una fecha u hora pasada.";
            errorSpan.style.display = "block";
        } else {
            inputFecha.classList.remove('input-error');
            inputFecha.classList.add('input-success');
            errorSpan.style.display = "none";
        }
    });

    // 2. Envío con fetch() cargando JSON
    if (formTurno) {
        formTurno.addEventListener('submit', async (e) => {
            e.preventDefault(); // Evita la recarga de página

            // Validación final de seguridad en frontend
            if (inputFecha.classList.contains('input-error')) {
                mostrarModal("Error", "Por favor, corrige los campos inválidos antes de continuar.");
                return;
            }

            // Preparación del payload JSON
            const datosTurno = {
                sucursal_id: document.getElementById('sucursal').value,
                estudio_id: document.getElementById('estudio').value,
                fecha_hora: inputFecha.value
            };

            try {
                // Simulación de endpoint del controlador Backend
                const response = await fetch('api/crear_cita.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(datosTurno)
                });

                const resultado = await response.json();

                if (resultado.status === 'success') {
                    mostrarModal("¡Cita Solicitada!", `Tu cita ha sido registrada. Estado actual: ${resultado.nuevo_estado}. Pendiente de validación por recepción.`);
                    formTurno.reset();
                } else {
                    mostrarModal("Error del Sistema", resultado.message);
                }

            } catch (error) {
                // Manejo de contingencia por desconexión de red o caída del servidor
                console.error("Error en la petición fetch:", error);
                mostrarModal("Error de Red", "No se pudo conectar con el servidor de la clínica. El turno se procesará en modo local simulado.");
            }
        });
    }

    // Funciones auxiliares para controlar el Modal de Feedback
    function mostrarModal(titulo, mensaje) {
        document.getElementById('modal-title').textContent = titulo;
        document.getElementById('modal-message').textContent = mensaje;
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
    }

    document.getElementById('btn-close-modal')?.addEventListener('click', () => {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
    });
});