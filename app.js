const selector = document.getElementById('selector-idioma');

// 1. Función principal para cambiar el idioma
async function cambiarIdioma(idioma) {
    try {
        // Buscamos el archivo JSON externo
        const respuesta = await fetch('idiomas.json');
        const traducciones = await respuesta.json();

        // Actualizamos el atributo 'lang' del HTML para los navegadores y Google
        document.documentElement.lang = idioma;

        // Buscamos todos los elementos con el atributo data-key
        const elementos = document.querySelectorAll('[data-key]');
        
        elementos.forEach(elemento => {
            const clave = elemento.getAttribute('data-key');
            // Cambiamos el texto dinámicamente según el idioma
            elemento.textContent = traducciones[idioma][clave];
        });

        // Marcar que las traducciones ya fueron aplicadas para mostrarlas
        document.body.classList.add('translations-ready');

        // Guardamos la elección en la memoria del navegador
        localStorage.setItem('idiomaPreferido', idioma);
        
        // Hacemos que el menú desplegable muestre el idioma actual
        selector.value = idioma;

    } catch (error) {
        console.error("Error cargando los idiomas, manín:", error);
    }
}

// 2. Escuchar cuando el usuario seleccione otro idioma en el menú
selector.addEventListener('change', (evento) => {
    cambiarIdioma(evento.target.value);
});

// 3. Al cargar la página: usar el idioma guardado, o el español por defecto
const idiomaInicial = localStorage.getItem('idiomaPreferido') || 'es';
cambiarIdioma(idiomaInicial);

// ============================================
// LOGIN SCREEN LOGIC
// ============================================

// Variables globales
const btnSocios = document.getElementById('btn-socios');
const btnEmpleados = document.getElementById('btn-empleados');
const btnRegister = document.getElementById('btn-register');
const loginScreen = document.getElementById('login-screen');
const mainContent = document.getElementById('main-content');
const registerModal = document.getElementById('register-modal');
const loginModal = document.getElementById('login-modal');
const registerForm = document.getElementById('register-form');
const loginForm = document.getElementById('login-form');

// Variables para guardar el tipo de usuario
let currentUserRole = null;

// ============================================
// FUNCIONES AUXILIARES CON SEGURIDAD
// ============================================

function closeLoginScreen() {
    loginScreen.classList.add('hidden');
    mainContent.classList.remove('hidden');
}

function showModal(modal) {
    modal.classList.remove('hidden');
}

function hideModal(modal) {
    modal.classList.add('hidden');
}

function clearMessage(messageElement) {
    messageElement.textContent = '';
    messageElement.className = 'modal-message';
}

/**
 * Muestra un mensaje de forma segura usando textContent (no innerHTML)
 * Escapa caracteres HTML especiales
 */
function showMessage(messageElement, message, type = 'error') {
    // Sanitizar el mensaje para evitar XSS
    const mensajeSanitizado = typeof message === 'string' ? message : '';
    
    messageElement.textContent = mensajeSanitizado;
    messageElement.className = `modal-message ${type}`;
}

function clearForm(form) {
    form.reset();
    // Limpiar mensajes de error
    const errors = form.querySelectorAll('.error-tooltip');
    errors.forEach(error => error.textContent = '');
}

function isLocalFileProtocol() {
    return window.location.protocol === 'file:';
}

function getApiUrl(action) {
    // Validar el action contra una whitelist
    const accionesValidas = ['register', 'login'];
    if (!accionesValidas.includes(action)) {
        console.error('Acción no válida:', action);
        return '';
    }
    return `auth.php?action=${encodeURIComponent(action)}`;
}

function ensureHttpServer(messageEl) {
    if (isLocalFileProtocol()) {
        showMessage(messageEl, 'Abre esta página desde un servidor PHP local (http://localhost) para registrar o iniciar sesión.', 'error');
        return false;
    }
    return true;
}

// ============================================
// MANEJO DE REGISTRO
// ============================================

if (btnRegister) {
    btnRegister.addEventListener('click', () => {
        clearForm(registerForm);
        clearMessage(document.getElementById('register-message'));
        showModal(registerModal);
    });
}

document.getElementById('close-register')?.addEventListener('click', () => {
    hideModal(registerModal);
});

if (registerForm) {
    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const rol = document.getElementById('register-rol').value;
        const cedula = document.getElementById('register-ci').value.trim();
        const password = document.getElementById('register-password').value;
        const confirmPassword = document.getElementById('register-password-confirm').value;
        const messageEl = document.getElementById('register-message');
        
        // Validaciones
        clearMessage(messageEl);
        let hasErrors = false;
        
        // Validar rol con whitelist
        if (!validarRol(rol)) {
            showMessage(messageEl, 'Debes seleccionar un tipo de usuario válido', 'error');
            hasErrors = true;
        }
        
        // Validar cédula
        if (!validarCedula(cedula)) {
            document.getElementById('register-ci-error').textContent = 'La cédula debe contener solo números (1-20 dígitos)';
            hasErrors = true;
        }
        
        // Validar contraseña
        if (!validarPassword(password)) {
            document.getElementById('register-password-error').textContent = 'La contraseña debe tener entre 6 y 128 caracteres';
            hasErrors = true;
        }
        
        if (password !== confirmPassword) {
            document.getElementById('register-confirm-error').textContent = 'Las contraseñas no coinciden';
            hasErrors = true;
        }
        
        if (hasErrors) return;
        
        if (!ensureHttpServer(messageEl)) return;
        
        // Enviar datos al servidor
        try {
            const response = await fetch(getApiUrl('register'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    cedula: cedula,
                    password: password,
                    rol: rol
                })
            });
            
            if (!response.ok) {
                const text = await response.text();
                showMessage(messageEl, `Error de conexión: ${response.status}`, 'error');
                return;
            }
            
            const data = await response.json();
            
            if (data.success) {
                showMessage(messageEl, data.message, 'success');
                clearForm(registerForm);
                
                // Cerrar modal después de 2 segundos
                setTimeout(() => {
                    hideModal(registerModal);
                }, 2000);
            } else {
                showMessage(messageEl, data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage(messageEl, 'Error de conexión. Intenta nuevamente.', 'error');
        }
    });
}

// ============================================
// MANEJO DE LOGIN (Socios y Empleados)
// ============================================

if (btnSocios) {
    btnSocios.addEventListener('click', () => {
        currentUserRole = 'Socio';
        openLoginModal('Socio');
    });
}

if (btnEmpleados) {
    btnEmpleados.addEventListener('click', () => {
        currentUserRole = 'Empleado';
        openLoginModal('Empleado');
    });
}

function openLoginModal(role) {
    document.getElementById('login-modal-title').textContent = `Iniciar Sesión - ${role}`;
    clearForm(loginForm);
    clearMessage(document.getElementById('login-message'));
    showModal(loginModal);
}

document.getElementById('close-login')?.addEventListener('click', () => {
    hideModal(loginModal);
    currentUserRole = null;
});

if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const cedula = document.getElementById('login-ci').value.trim();
        const password = document.getElementById('login-password').value;
        const messageEl = document.getElementById('login-message');
        
        // Validaciones
        clearMessage(messageEl);
        let hasErrors = false;
        
        // Validar cédula
        if (!validarCedula(cedula)) {
            document.getElementById('login-ci-error').textContent = 'La cédula debe contener solo números (1-20 dígitos)';
            hasErrors = true;
        }
        
        // Validar contraseña
        if (!validarPassword(password)) {
            document.getElementById('login-password-error').textContent = 'La contraseña es requerida';
            hasErrors = true;
        }
        
        if (hasErrors) return;
        
        if (!ensureHttpServer(messageEl)) return;
        
        // Enviar datos al servidor
        try {
            const response = await fetch(getApiUrl('login'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    cedula: cedula,
                    password: password,
                    rolEsperado: currentUserRole
                })
            });
            
            if (!response.ok) {
                const text = await response.text();
                showMessage(messageEl, `Error de conexión: ${response.status}`, 'error');
                return;
            }
            
            const data = await response.json();
            
            if (data.success) {
                showMessage(messageEl, data.message, 'success');
                
                // Guardar datos del usuario de forma segura
                if (data.user && typeof data.user === 'object') {
                    // Sanitizar datos antes de guardar
                    const usuarioSanitizado = {
                        id: parseInt(data.user.id, 10) || 0,
                        rol: String(data.user.rol || ''),
                        cedula: String(data.user.cedula || '')
                    };
                    guardarEnStorage('user', usuarioSanitizado);
                }
                
                // Cerrar modal y pantalla de login
                setTimeout(() => {
                    hideModal(loginModal);
                    closeLoginScreen();
                }, 1500);
            } else {
                showMessage(messageEl, data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage(messageEl, 'Error de conexión. Intenta nuevamente.', 'error');
        }
    });
}