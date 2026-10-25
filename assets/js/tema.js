// Tema claro/oscuro. Se carga sin defer en el <head> para fijar data-theme antes
// del primer pintado (sin destello claro). Sin eleccion guardada sigue al sistema.
// El interruptor del menu de usuario (sesion.js) usa window.tema para cambiarlo.
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

    function preferido() {
        const tema = guardado();
        return tema === 'dark' || tema === 'light' ? tema : (sistema.matches ? 'dark' : 'light');
    }

    function aplicar(tema) {
        raiz.dataset.theme = tema;
        raiz.dispatchEvent(new CustomEvent('cambio-tema', { detail: tema }));
    }

    // Cross-fades the whole page into the new theme (View Transitions API); browsers without
    // it, or with reduced motion requested, switch instantly.
    function aplicarGradual(tema) {
        const reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (document.startViewTransition && !reducido) {
            document.startViewTransition(() => aplicar(tema));
        } else {
            aplicar(tema);
        }
    }

    aplicar(preferido());
    sistema.addEventListener('change', () => {
        if (!guardado()) {
            aplicarGradual(preferido());
        }
    });

    window.tema = {
        actual: () => raiz.dataset.theme,
        cambiar(tema) {
            try {
                localStorage.setItem(CLAVE, tema);
            } catch {
                // sin storage el cambio dura hasta recargar
            }
            aplicarGradual(tema);
        },
    };
})();
