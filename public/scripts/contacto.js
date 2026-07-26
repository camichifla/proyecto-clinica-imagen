document.addEventListener('DOMContentLoaded', () => {
    const formContacto = document.getElementById('form-contacto');

    if (!formContacto) return;

    formContacto.addEventListener('submit', async function (e) {
        e.preventDefault();

        const form = this;
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');

        submitBtn.disabled = true;
        const textoOriginal = submitBtn.textContent;
        submitBtn.textContent = 'Enviando...';

        try {
            // Apuntamos a la carpeta api/
            const response = await fetch('/api/guardar_consulta.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert(result.message);
                form.reset();
            } else {
                alert('Error: ' + result.message);
            }
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = textoOriginal;
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-contacto');

    form.addEventListener('submit', async (e) => {
        e.preventDefault(); // Evita que salte a la pantalla negra del PHP

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form)
            });

            const data = await response.json();

            // Buscamos si ya existe un mensaje anterior o creamos uno nuevo
            let alerta = document.getElementById('alerta-contacto');
            if (!alerta) {
                alerta = document.createElement('div');
                alerta.id = 'alerta-contacto';
                alerta.style.padding = '12px';
                alerta.style.marginTop = '15px';
                alerta.style.borderRadius = '5px';
                alerta.style.fontWeight = 'bold';
                form.appendChild(alerta);
            }

            if (data.success) {
                alerta.style.backgroundColor = '#d4edda';
                alerta.style.color = '#155724';
                alerta.textContent = data.message;
                form.reset(); // Limpia los campos
            } else {
                alerta.style.backgroundColor = '#f8d7da';
                alerta.style.color = '#721c24';
                alerta.textContent = data.message;
            }
        } catch (error) {
            console.error('Error:', error);
        }
    });
});