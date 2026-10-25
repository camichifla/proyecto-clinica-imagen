// Combobox liviano sobre <input data-buscador-combo="<id-del-hidden>" data-lista="<id-del-datalist>"> con listado propio.
// El <datalist> nunca lleva el atributo `list`: es solo fuente de datos (<option data-id value="etiqueta">).
// El <input hidden> es el que viaja en el submit: etiqueta exacta, o vacio (rechazado server-side).
(() => {
  // Accents and case are ignored: "lopez" finds "López".
  const normalizar = (texto) => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();

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
    const etiqueta = input.id ? document.querySelector('label[for="' + input.id + '"]') : null;
    if (etiqueta) {
      etiqueta.id = etiqueta.id || input.id + '_etiqueta';
      lista.setAttribute('aria-labelledby', etiqueta.id);
    }
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

    // No list until something is typed; then only the matches.
    const mostrarLista = () => {
      const texto = normalizar(input.value);
      if (texto === '') {
        cerrar();
        return;
      }
      const coincidencias = opciones.filter((opcion) => normalizar(opcion.etiqueta).includes(texto));

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
            if (evento.isTrusted) {
              input.blur(); // picked with the mouse: the box deselects (Enter, which fakes this event, keeps focus)
            }
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
