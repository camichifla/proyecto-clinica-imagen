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
        // Buscamos el botón que disparó el submit; si event.submitter
        // viene null (pasa al enviar con Enter en algunos navegadores),
        // usamos el botón de submit del formulario como respaldo.
        const submitter = event.submitter || form.querySelector('button[type="submit"]');
        const formData = new FormData(form);

        if (submitter && submitter.name) {
            formData.append(submitter.name, submitter.value || '1');
        }

        const response = await fetch(form.action, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        let data;
        try {
            data = await response.json();
        } catch {
            showFormError(form, 'Ocurrió un error inesperado. Intentá de nuevo.');
            return;
        }

        if (data.ok) {
            window.location.href = data.redirect || '/public/login.html';
            return;
        }

        showFormError(form, data.message || 'Ocurrió un error. Intentá de nuevo.');
    } catch (err) {
        showFormError(form, 'No se pudo conectar con el servidor. Revisá tu conexión.');
    } finally {
        setFormLoading(form, false);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-ajax-form]').forEach(form => {
        form.addEventListener('submit', handleAjaxSubmit);
    });
});