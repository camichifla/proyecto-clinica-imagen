(() => {
    const contenedor = document.getElementById('resultados');
    if (!contenedor) {
        return;
    }

    function crearTarjetaResultado(resultado) {
        const articulo = document.createElement('article');
        articulo.className = 'tarjeta-resultado';

        const header = document.createElement('div');
        header.className = 'tarjeta-resultado-header';
        const h2 = document.createElement('h2');
        h2.textContent = resultado.nombre_estudio;
        const fecha = document.createElement('span');
        fecha.className = 'tarjeta-resultado-fecha';
        fecha.textContent = resultado.fecha_estudio;
        header.append(h2, fecha);
        articulo.appendChild(header);

        if (resultado.observaciones) {
            const p = document.createElement('p');
            p.className = 'tarjeta-resultado-observaciones';
            resultado.observaciones.split('\n').forEach((linea, i) => {
                if (i > 0) {
                    p.appendChild(document.createElement('br'));
                }
                p.appendChild(document.createTextNode(linea));
            });
            articulo.appendChild(p);
        }

        if (resultado.imagenes.length) {
            const divImagenes = document.createElement('div');
            divImagenes.className = 'tarjeta-resultado-imagenes';
            resultado.imagenes.forEach((imagen) => {
                const img = document.createElement('img');
                img.src = imagen.url;
                img.alt = imagen.nombre_original;
                img.loading = 'lazy';
                divImagenes.appendChild(img);
            });
            articulo.appendChild(divImagenes);
        }

        return articulo;
    }

    async function init() {
        const sesion = await window.sesionLista;
        if (!sesion) {
            return;
        }
        let respuesta;
        try {
            respuesta = await fetch('api/resultados-propios.php');
        } catch {
            return;
        }
        if (respuesta.status === 401 || respuesta.status === 403 || respuesta.redirected) {
            window.location.href = 'login.html';
            return;
        }
        const data = await respuesta.json();

        if (!data.resultados.length) {
            const p = document.createElement('p');
            p.textContent = 'Todavia no tenes resultados cargados.';
            contenedor.appendChild(p);
            return;
        }

        const lista = document.createElement('div');
        lista.className = 'lista-resultados';
        data.resultados.forEach((resultado) => lista.appendChild(crearTarjetaResultado(resultado)));
        contenedor.appendChild(lista);
    }

    init();
})();
