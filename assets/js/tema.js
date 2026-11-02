(() => {
    const CLAVE = 'tema';
    const raiz = document.documentElement;
    const sistema = window.matchMedia('(prefers-color-scheme: dark)');

    function guardado() {
        try {
            return localStorage.getItem(CLAVE);
        } catch {
            return null;
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
            }
            aplicarGradual(tema);
        },
    };
})();
