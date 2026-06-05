/**
 * ========================================
 * MÓDULO DE SEGURIDAD - Sanitización XSS
 * ========================================
 * 
 * Este módulo contiene funciones para prevenir ataques XSS
 * en toda la aplicación. Proporciona:
 * - Sanitización de HTML
 * - Validación de entrada
 * - Escapado de caracteres especiales
 */

/**
 * Escapa caracteres HTML especiales para evitar XSS
 * @param {string} texto - Texto a escapar
 * @returns {string} Texto escapado
 */
function escapeHTML(texto) {
    if (typeof texto !== 'string') {
        return '';
    }
    
    const mapa = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#x27;',
        '/': '&#x2F;'
    };
    
    return texto.replace(/[&<>"'\/]/g, (char) => mapa[char]);
}

/**
 * Sanitiza un objeto de datos removiendo caracteres peligrosos
 * @param {Object} datos - Objeto a sanitizar
 * @returns {Object} Objeto sanitizado
 */
function sanitizarDatos(datos) {
    if (!datos || typeof datos !== 'object') {
        return datos;
    }
    
    const datosLimpios = {};
    
    for (const clave in datos) {
        if (Object.prototype.hasOwnProperty.call(datos, clave)) {
            const valor = datos[clave];
            
            // Sanitizar strings
            if (typeof valor === 'string') {
                datosLimpios[clave] = valor.trim();
            } 
            // Recursivamente sanitizar objetos
            else if (typeof valor === 'object' && valor !== null) {
                datosLimpios[clave] = sanitizarDatos(valor);
            } 
            // Mantener otros tipos de datos
            else {
                datosLimpios[clave] = valor;
            }
        }
    }
    
    return datosLimpios;
}

/**
 * Valida una cédula: solo números, máximo 20 caracteres
 * @param {string} cedula - Cédula a validar
 * @returns {boolean} True si es válida
 */
function validarCedula(cedula) {
    if (typeof cedula !== 'string') return false;
    return /^\d{1,20}$/.test(cedula.trim());
}

/**
 * Valida que un rol sea válido
 * @param {string} rol - Rol a validar
 * @returns {boolean} True si es válido
 */
function validarRol(rol) {
    const rolesValidos = ['Socio', 'Empleado', 'Paciente', 'Recepcionista', 'Doctor'];
    return typeof rol === 'string' && rolesValidos.includes(rol);
}

/**
 * Valida una contraseña: mínimo 6 caracteres, máximo 128
 * @param {string} password - Contraseña a validar
 * @returns {boolean} True si es válida
 */
function validarPassword(password) {
    if (typeof password !== 'string') return false;
    return password.length >= 6 && password.length <= 128;
}

/**
 * Valida un email 
 * @param {string} email - Email a validar
 * @returns {boolean} True si tiene formato válido
 */
function validarEmail(email) {
    if (typeof email !== 'string') return false;
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

/**
 * Sanitiza un URL para evitar javascript: u otros protocolos peligrosos
 * @param {string} url - URL a sanitizar
 * @returns {string} URL sanitizada o cadena vacía
 */
function sanitizarURL(url) {
    if (typeof url !== 'string') return '';
    
    const urlTrimmed = url.trim().toLowerCase();
    
    // Rechazar URLs con protocolos peligrosos
    const protocolosPeligrosos = ['javascript:', 'data:', 'vbscript:', 'file:'];
    
    for (const protocolo of protocolosPeligrosos) {
        if (urlTrimmed.startsWith(protocolo)) {
            return '';
        }
    }
    
    return url;
}

/**
 * Crea un elemento DOM de forma segura con textContent
 * @param {string} etiqueta - Nombre de la etiqueta
 * @param {string} contenido - Contenido de texto (será escapado)
 * @param {Object} atributos - Atributos a añadir
 * @returns {Element} Elemento creado
 */
function crearElementoSeguro(etiqueta, contenido = '', atributos = {}) {
    const elemento = document.createElement(etiqueta);
    
    // Usar textContent para evitar XSS
    if (typeof contenido === 'string') {
        elemento.textContent = contenido;
    }
    
    // Añadir atributos de forma segura
    for (const [clave, valor] of Object.entries(atributos)) {
        // No permitir event handlers
        if (clave.toLowerCase().startsWith('on')) {
            console.warn(`No se permite el atributo: ${clave}`);
            continue;
        }
        
        elemento.setAttribute(clave, String(valor));
    }
    
    return elemento;
}

/**
 * Obtiene datos del localStorage de forma segura
 * @param {string} clave - Clave del localStorage
 * @returns {any} Datos parseados o null
 */
function obtenerDelStorage(clave) {
    try {
        const datos = localStorage.getItem(clave);
        return datos ? JSON.parse(datos) : null;
    } catch (error) {
        console.error(`Error al obtener datos de storage: ${clave}`, error);
        return null;
    }
}

/**
 * Guarda datos en localStorage de forma segura
 * @param {string} clave - Clave del localStorage
 * @param {any} datos - Datos a guardar
 * @returns {boolean} True si fue exitoso
 */
function guardarEnStorage(clave, datos) {
    try {
        localStorage.setItem(clave, JSON.stringify(datos));
        return true;
    } catch (error) {
        console.error(`Error al guardar en storage: ${clave}`, error);
        return false;
    }
}

/**
 * Limpia todos los datos de usuario del storage (logout seguro)
 */
function limpiarStorage() {
    try {
        localStorage.removeItem('user');
        localStorage.removeItem('sessionToken');
        localStorage.removeItem('userRole');
    } catch (error) {
        console.error('Error al limpiar storage:', error);
    }
}

/**
 * Obtiene un token CSRF del servidor (para futuras implementaciones)
 * @returns {Promise<string>} Token CSRF
 */
async function obtenerTokenCSRF() {
    try {
        const response = await fetch('api/csrf-token.php', {
            method: 'GET',
            credentials: 'same-origin'
        });
        
        if (!response.ok) return '';
        
        const data = await response.json();
        return data.token || '';
    } catch (error) {
        console.error('Error al obtener token CSRF:', error);
        return '';
    }
}

// Exportar para uso en módulos (si se usa con módulos ES6)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        escapeHTML,
        sanitizarDatos,
        validarCedula,
        validarRol,
        validarPassword,
        validarEmail,
        sanitizarURL,
        crearElementoSeguro,
        obtenerDelStorage,
        guardarEnStorage,
        limpiarStorage,
        obtenerTokenCSRF
    };
}
