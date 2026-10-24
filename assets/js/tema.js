// Tema claro/oscuro. Se carga sin defer en el <head> para fijar data-theme antes
// del primer pintado (sin destello claro). Sin eleccion guardada sigue al sistema;
// el boton del header guarda la eleccion en localStorage.
(() => {
    const CLAVE = 'tema';
    const raiz = document.documentElement;
    const sistema = window.matchMedia('(prefers-color-scheme: dark)');

    function guardado() {
        try {
            return localStorage.getItem(CLAVE);
        } catch {
            return null; // storage bloqueado: se sigue al sistema
        }
    }

    function actual() {
        const tema = guardado();
        return tema === 'dark' || tema === 'light' ? tema : (sistema.matches ? 'dark' : 'light');
    }

    function aplicar(tema) {
        raiz.dataset.theme = tema;
        const boton = document.querySelector('.tema-toggle');
        if (boton) {
            const oscuro = tema === 'dark';
            boton.setAttribute('aria-pressed', String(oscuro));
            boton.setAttribute('aria-label', oscuro ? 'Activar modo claro' : 'Activar modo oscuro');
            boton.title = boton.getAttribute('aria-label');
            boton.textContent = oscuro ? '☀' : '☾';
        }
    }

    aplicar(actual());
    sistema.addEventListener('change', () => {
        if (!guardado()) {
            aplicar(actual());
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        const header = document.querySelector('header');
        if (!header) {
            return;
        }
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'tema-toggle';
        boton.addEventListener('click', () => {
            const nuevo = raiz.dataset.theme === 'dark' ? 'light' : 'dark';
            try {
                localStorage.setItem(CLAVE, nuevo);
            } catch {
                // sin storage el cambio dura hasta recargar
            }
            aplicar(nuevo);
        });
        const usuario = header.querySelector('.user');
        header.insertBefore(boton, usuario);
        aplicar(raiz.dataset.theme);
    });
})();
