// assets/js/agenda-ajax.js — intercepts nav links inside #agenda-calendario
// (toggle mes/semana, prev/next, Hoy, dia, "+N mas") so cambiar de mes o
// semana solo reemplaza ese bloque via fetch en vez de recargar el sitio
// entero. El backend (agenda.php / ver-agenda.php) detecta el header
// X-Requested-With y devuelve unicamente el fragmento del calendario.
// Delegacion de eventos sobre el contenedor estable: sigue funcionando
// despues de cada reemplazo de innerHTML sin tener que re-bindear nada,
// y cubre tambien el toggle de las tarjetas de la vista semana (antes un
// <script> inline que no se re-ejecutaria tras un swap por innerHTML).
(() => {
    const calendario = document.getElementById('agenda-calendario');
    if (!calendario) {
        return;
    }

    async function cargar(url, agregarHistorial) {
        let respuesta;
        try {
            respuesta = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        } catch {
            window.location.href = url;
            return;
        }
        // auth_guard.php redirige a login.php cuando la sesion vencio; fetch()
        // sigue ese redirect solo (respuesta.ok queda en true), asi que sin este
        // chequeo el HTML entero del login terminaria metido dentro del div
        // chico del calendario. respuesta.redirected detecta ese caso y fuerza
        // una navegacion real para que el cierre de sesion afecte todo el sitio.
        if (!respuesta.ok || respuesta.redirected) {
            window.location.href = respuesta.url || url;
            return;
        }
        calendario.innerHTML = await respuesta.text();
        if (agregarHistorial) {
            history.pushState(null, '', url);
        }
    }

    calendario.addEventListener('click', (evento) => {
        const enlace = evento.target.closest('a[href]');
        if (enlace) {
            evento.preventDefault();
            cargar(enlace.getAttribute('href'), true);
            return;
        }
        const tarjeta = evento.target.closest('.agenda-cita-card');
        if (tarjeta) {
            const expandida = tarjeta.getAttribute('aria-expanded') === 'true';
            tarjeta.setAttribute('aria-expanded', String(!expandida));
        }
    });

    window.addEventListener('popstate', () => cargar(location.href, false));
})();
