(() => {
    const contenedor = document.getElementById('ordenes');
    if (!contenedor) {
        return;
    }

    function crearOrden(orden) {
        const articulo = document.createElement('article');
        articulo.className = 'orden-propia';

        const h3 = document.createElement('h3');
        h3.textContent = `${orden.tipo_label} - ${orden.fecha}`;
        articulo.appendChild(h3);

        const datos = [];
        if (orden.cita_estudio) {
            datos.push(['Estudio de la cita', `${orden.cita_estudio} (${orden.cita_fecha})`]);
        }
        datos.push(['Indicada por', orden.medico ? orden.medico + (orden.medico_email ? ` (${orden.medico_email})` : '') : '-']);
        datos.push(...orden.detalle.map((d) => [d.etiqueta, d.valor]));

        const dl = document.createElement('dl');
        datos.forEach(([etiqueta, valor]) => {
            const dt = document.createElement('dt');
            dt.textContent = etiqueta;
            const dd = document.createElement('dd');
            dd.textContent = valor;
            dl.append(dt, dd);
        });
        articulo.appendChild(dl);
        return articulo;
    }

    async function init() {
        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }
        const data = await apiJson('api/ordenes-propias.php');
        if (!data) {
            return;
        }
        if (!data.ordenes || !data.ordenes.length) {
            const p = document.createElement('p');
            p.textContent = 'Todavía no tenés órdenes de estudio.';
            contenedor.appendChild(p);
            return;
        }
        data.ordenes.forEach((orden) => contenedor.appendChild(crearOrden(orden)));
    }

    init();
})();
