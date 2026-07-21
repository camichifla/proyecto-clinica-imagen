function showForm(formId) {
    document.querySelectorAll(".form-box").forEach(form => form.classList.remove("active"));
    document.getElementById(formId).classList.add("active");
}

function showFormError(form, message) {
    const slot = form.querySelector('[data-error-slot]');
    if (!slot) return;
    slot.textContent = message;
    slot.classList.remove('hidden');
}

function clearFormError(form) {
    const slot = form.querySelector('[data-error-slot]');
    if (!slot) return;
    slot.textContent = '';
    slot.classList.add('hidden');
}

function setFormLoading(form, isLoading) {
    const button = form.querySelector('button[type="submit"]');
    if (!button) return;
    button.disabled = isLoading;
    button.dataset.originalText ??= button.textContent;
    button.textContent = isLoading ? 'Enviando...' : button.dataset.originalText;
}

async function handleAjaxSubmit(event) {
    const form = event.target;
    event.preventDefault();
    clearFormError(form);
    setFormLoading(form, true);

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        // Si la respuesta no es JSON (ej: sesión caída, error 500), no reventar en silencio.
        let data;
        try {
            data = await response.json();
        } catch {
            showFormError(form, 'Ocurrió un error inesperado. Intentá de nuevo.');
            return;
        }

        if (data.ok) {
            window.location.href = data.redirect || 'login.php';
            return;
        }

        showFormError(form, data.message || 'Ocurrió un error. Intentá de nuevo.');
    } catch (err) {
        showFormError(form, 'No se pudo conectar con el servidor. Revisá tu conexión.');
    } finally {
        setFormLoading(form, false);
    }
}

async function loadCsrfToken() {
    try {
        const response = await fetch('csrf-token.php', { credentials: 'same-origin' });
        const data = await response.json();
        document.querySelectorAll('[data-csrf-field]').forEach(field => {
            field.value = data.csrf_token;
        });
    } catch (err) {
        // Si falla, los forms quedan sin token y el backend los va a rechazar con
        // un mensaje claro en vez de fallar en silencio.
        console.error('No se pudo obtener el token de seguridad.', err);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadCsrfToken();
    document.querySelectorAll('form[data-ajax-form]').forEach(form => {
        form.addEventListener('submit', handleAjaxSubmit);
    });
});
