// Combobox liviano: toma un <input type="text"> marcado con
// data-buscador-combo="<id-del-hidden>" y data-lista="<id-del-datalist>",
// y arma un listado desplegable propio -con la misma estetica del resto
// del sitio- en lugar del popup nativo del navegador (que no se puede
// estilar). El <datalist> referenciado nunca lleva el atributo `list`:
// solo se usa como fuente de datos (sus <option data-id value="etiqueta">
// son las mismas que antes renderizaba el <select>). El <input hidden> es
// el que realmente viaja en el submit: coincide con la etiqueta completa
// tipeada, y si no hay match exacto queda vacio, rechazado server-side
// exactamente igual que un <select> sin elegir.
(() => {
  document.querySelectorAll('input[data-buscador-combo]').forEach((input) => {
    const datalist = document.getElementById(input.dataset.lista);
    const hidden = document.getElementById(input.dataset.buscadorCombo);
    if (!datalist || !hidden) {
      return;
    }

    const opciones = Array.from(datalist.querySelectorAll('option')).map((opcion) => ({
      id: opcion.dataset.id,
      etiqueta: opcion.value,
    }));

    const wrapper = document.createElement('div');
    wrapper.className = 'buscador-combo';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    const lista = document.createElement('ul');
    lista.className = 'buscador-combo-lista';
    lista.setAttribute('role', 'listbox');
    lista.id = input.id + '_opciones';
    lista.hidden = true;
    wrapper.appendChild(lista);

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', lista.id);
    input.setAttribute('aria-autocomplete', 'list');

    let activa = -1;

    const sincronizarSeleccion = () => {
      const opcion = opciones.find((o) => o.etiqueta === input.value);
      const id = opcion ? opcion.id : '';
      if (hidden.value !== id) {
        hidden.value = id;
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
      }
    };

    const cerrar = () => {
      lista.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      activa = -1;
    };

    const marcarActiva = (indice) => {
      lista.querySelectorAll('li[role="option"]').forEach((li, i) => {
        li.setAttribute('aria-selected', i === indice ? 'true' : 'false');
      });
      activa = indice;
      const li = lista.querySelectorAll('li[role="option"]')[indice];
      if (li) {
        input.setAttribute('aria-activedescendant', li.id);
        li.scrollIntoView({ block: 'nearest' });
      } else {
        input.removeAttribute('aria-activedescendant');
      }
    };

    const elegir = (opcion) => {
      input.value = opcion.etiqueta;
      sincronizarSeleccion();
      cerrar();
    };

    const mostrarLista = () => {
      const texto = input.value.trim().toLocaleLowerCase();
      const coincidencias = texto === ''
        ? opciones
        : opciones.filter((opcion) => opcion.etiqueta.toLocaleLowerCase().includes(texto));

      lista.innerHTML = '';
      if (!coincidencias.length) {
        const vacio = document.createElement('li');
        vacio.className = 'buscador-combo-vacio';
        vacio.textContent = 'Sin resultados';
        lista.appendChild(vacio);
      } else {
        coincidencias.forEach((opcion) => {
          const li = document.createElement('li');
          li.id = lista.id + '_' + opcion.id;
          li.setAttribute('role', 'option');
          li.textContent = opcion.etiqueta;
          li.addEventListener('mousedown', (evento) => {
            evento.preventDefault();
            elegir(opcion);
          });
          lista.appendChild(li);
        });
      }

      lista.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      activa = -1;
    };

    input.addEventListener('focus', mostrarLista);
    input.addEventListener('input', () => {
      mostrarLista();
      sincronizarSeleccion();
    });

    input.addEventListener('keydown', (evento) => {
      if (evento.key === 'ArrowDown') {
        evento.preventDefault();
        if (lista.hidden) {
          mostrarLista();
        }
        const opcionesVisibles = lista.querySelectorAll('li[role="option"]');
        marcarActiva(Math.min(activa + 1, opcionesVisibles.length - 1));
      } else if (evento.key === 'ArrowUp') {
        evento.preventDefault();
        marcarActiva(Math.max(activa - 1, 0));
      } else if (evento.key === 'Enter') {
        const opcionesVisibles = lista.querySelectorAll('li[role="option"]');
        if (!lista.hidden && activa >= 0 && opcionesVisibles[activa]) {
          evento.preventDefault();
          opcionesVisibles[activa].dispatchEvent(new MouseEvent('mousedown'));
        }
      } else if (evento.key === 'Escape') {
        cerrar();
      }
    });

    input.addEventListener('blur', cerrar);
  });
})();
