// comparable: staff view adds a "Comparar" checkbox under each image (see visor-imagenes.js).
function crearTarjetaResultado(resultado, comparable = false) {
    const articulo = document.createElement('article');
    articulo.className = 'tarjeta-resultado';

    const header = document.createElement('div');
    header.className = 'tarjeta-resultado-header';
    const h2 = document.createElement('h2');
    h2.textContent = resultado.nombre_estudio;
    const fecha = document.createElement('span');
    fecha.className = 'tarjeta-resultado-fecha';
    fecha.textContent = fechaHora(resultado.fecha_estudio);
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

    if (resultado.informe) {
        const enlace = document.createElement('a');
        enlace.className = 'tarjeta-resultado-informe';
        enlace.href = resultado.informe.url;
        enlace.target = '_blank';
        enlace.rel = 'noopener';
        enlace.textContent = 'Ver informe (PDF)';
        const descarga = document.createElement('a');
        descarga.className = 'tarjeta-resultado-informe';
        descarga.href = resultado.informe.url;
        descarga.download = 'informe-' + resultado.id + '.pdf';
        descarga.textContent = 'Descargar informe';
        articulo.append(enlace, ' · ', descarga);
    }

    if (resultado.imagenes.length) {
        const divImagenes = document.createElement('div');
        divImagenes.className = 'tarjeta-resultado-imagenes';
        const total = resultado.imagenes.length;
        resultado.imagenes.forEach((imagen, i) => {
            const img = document.createElement('img');
            img.src = imagen.url;
            img.alt = `Imagen de ${resultado.nombre_estudio} del ${fechaHora(resultado.fecha_estudio)}` + (total > 1 ? ` (${i + 1} de ${total})` : '');
            img.dataset.estudio = resultado.nombre_estudio;
            img.dataset.fecha = fechaHora(resultado.fecha_estudio);
            img.loading = 'lazy';
            if (!comparable) {
                divImagenes.appendChild(img);
                return;
            }
            const figura = document.createElement('div');
            figura.className = 'imagen-comparable';
            const label = document.createElement('label');
            const check = document.createElement('input');
            check.type = 'checkbox';
            check.className = 'comparar-check';
            label.append(check, ' Comparar');
            figura.append(img, label);
            divImagenes.appendChild(figura);
        });
        articulo.appendChild(divImagenes);
    }

    return articulo;
}

function renderListaResultados(destino, resultados, mensajeVacio, comparable = false) {
    if (!resultados.length) {
        destino.appendChild(parrafo(mensajeVacio));
        return;
    }
    const lista = document.createElement('div');
    lista.className = 'lista-resultados';
    if (comparable && resultados.some((r) => r.imagenes.length)) {
        const barra = document.createElement('div');
        barra.className = 'barra-comparar';
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn-secundario comparar-abrir';
        boton.textContent = 'Comparar imágenes seleccionadas';
        boton.disabled = true;
        const aviso = document.createElement('p');
        aviso.className = 'campo-hint comparar-aviso';
        aviso.setAttribute('role', 'status');
        aviso.textContent = 'Marcá 2 imágenes con "Comparar" para verlas lado a lado.';
        barra.append(boton, aviso);
        lista.appendChild(barra);
    }
    resultados.forEach((resultado) => lista.appendChild(crearTarjetaResultado(resultado, comparable)));
    destino.appendChild(lista);
}
