// Light/dark theme. Loaded without defer in the <head> to set data-theme before
// the first paint (no light flash). With no saved choice it follows the system.
// The user menu's switch (sesion.js) uses window.tema to change it.
(() => {
    const CLAVE = 'tema';
    const raiz = document.documentElement;
    const sistema = window.matchMedia('(prefers-color-scheme: dark)');

    function guardado() {
        try {
            return localStorage.getItem(CLAVE);
        } catch {
            return null; // storage blocked: follow the system
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
                // without storage the change lasts until reload
            }
            aplicarGradual(tema);
        },
    };
})();
