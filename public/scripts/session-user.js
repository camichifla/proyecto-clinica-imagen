document.addEventListener('DOMContentLoaded', async function () {
    const welcomeLabel = document.getElementById('welcomeLabel');
    if (!welcomeLabel) return;

    try {
        const response = await fetch('/clinica-imagen/api/ajax/get_session_user.php', {credentials: 'include'});
        const data = await response.json();
        if (data.success && data.user && data.user.name) {
            welcomeLabel.textContent = `Bienvenido ${data.user.name}`;
        }
    } catch (error) {
        console.error('No se pudo cargar el usuario de la sesión.', error);
    }
});