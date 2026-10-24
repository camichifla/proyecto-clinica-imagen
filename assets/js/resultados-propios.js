(() => {
    const contenedor = document.getElementById('resultados');
    if (!contenedor) {
        return;
    }

    function renderNotas(notas) {
        const destino = document.getElementById('historial-notas');
        if (notas === null) {
            destino.replaceChildren(parrafo('No se pudo mostrar el historial clínico en este momento.'));
            return;
        }
        if (!notas.length) {
            destino.replaceChildren(parrafo('Todavía no hay notas en tu historial.'));
            return;
        }
        destino.replaceChildren(...notas.map((nota) => {
            const item = document.createElement('article');
            item.className = 'nota-clinica';
            const meta = parrafo(`${nota.fecha} - ${nota.autor}`);
            meta.className = 'nota-clinica-meta';
            const texto = parrafo(nota.texto);
            texto.className = 'nota-clinica-texto';
            item.append(meta, texto);
            return item;
        }));
    }

    function renderCitas(citas) {
        const destino = document.getElementById('historial-citas');
        if (!citas.length) {
            destino.replaceChildren(parrafo('Todavía no tenés citas.'));
            return;
        }
        const filas = citas.map((cita) => {
            const tr = document.createElement('tr');
            tr.appendChild(celda('Fecha y hora', cita.fecha));
            tr.appendChild(celda('Estudio', cita.estudio));
            tr.appendChild(celda('Sede', cita.sede || '-'));
            const td = document.createElement('td');
            td.dataset.label = 'Estado';
            const span = document.createElement('span');
            span.className = 'estado-badge estado-' + cita.estado;
            span.textContent = cita.estado_label;
            td.appendChild(span);
            tr.appendChild(td);
            return tr;
        });
        destino.replaceChildren(tabla(['Fecha y hora', 'Estudio', 'Sede', 'Estado'], filas));
    }

    async function init() {
        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }
        const data = await apiJson('api/resultados-propios.php');
        if (!data) {
            return;
        }

        renderListaResultados(contenedor, data.resultados, 'Todavía no tenés resultados cargados.');
        renderNotas(data.notas);
        renderCitas(data.citas || []);
    }

    init();
})();
