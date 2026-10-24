(() => {
    const lista = document.getElementById('eventos-lista');
    const susp = document.getElementById('sospechosos-lista');
    const form = document.getElementById('form-eventos');
    if (!lista || !susp || !form) {
        return;
    }
    const mensajeError = document.querySelector('[data-mensaje-error]');

    function llenarTipos(tipos) {
        const select = form.elements.tipo;
        if (select.options.length > 1) {
            return;
        }
        tipos.forEach((t) => select.appendChild(new Option(t, t)));
    }

    async function cargar() {
        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }
        const q = new URLSearchParams(new FormData(form));
        [...q.keys()].forEach((k) => q.get(k) === '' && q.delete(k));
        const data = await apiJson('api/eventos.php' + (q.size ? '?' + q : ''));
        if (!data || !data.eventos) {
            mensajeError.textContent = (data && data.error) || 'No se pudieron cargar los eventos.';
            mensajeError.hidden = false;
            return;
        }
        mensajeError.hidden = true;
        llenarTipos(data.tipos);
        lista.replaceChildren(data.eventos.length
            ? tabla(['Fecha', 'Tipo', 'Correo', 'IP', 'Cuenta', 'Detalle'], data.eventos.map((e) => {
                const tr = document.createElement('tr');
                [['Fecha', e.creado_en], ['Tipo', e.tipo], ['Correo', e.email || '-'], ['IP', e.ip],
                    ['Cuenta', e.cuenta_id == null ? '-' : e.cuenta_id], ['Detalle', e.detalle || '-']]
                    .forEach(([etiqueta, valor]) => tr.appendChild(celda(etiqueta, String(valor))));
                return tr;
            }))
            : parrafo('No hay eventos para ese filtro.'));
        susp.replaceChildren(data.sospechosos.length
            ? tabla(['Origen', 'Valor', 'Fallos (24 h)'], data.sospechosos.map((s) => {
                const tr = document.createElement('tr');
                [['Origen', s.campo === 'ip' ? 'IP' : 'Correo'], ['Valor', s.valor], ['Fallos (24 h)', String(s.cantidad)]]
                    .forEach(([etiqueta, valor]) => tr.appendChild(celda(etiqueta, valor)));
                return tr;
            }))
            : parrafo('Sin actividad sospechosa en las ultimas 24 horas.'));
    }

    form.addEventListener('submit', (evento) => {
        evento.preventDefault();
        cargar();
    });
    cargar();
})();
