// Listbox propio disparado por boton sobre un <select class="selector-combo"> real, que sigue oculto como fuente de
// verdad (los scripts de cascada lo manipulan igual que antes; este solo lo espeja). Se resincroniza con cada
// 'change' -propio o disparado por la cascada- y con un MutationObserver para las reescrituras completas
// (hora/cita_id, reconstruidos con innerHTML). Los <select class="selector-combo"> que los scripts agregan despues
// (filas de horario, reprogramar en turnos) se toman solos. La lista flota en posicion fija junto al boton, asi
// ninguna tabla con scroll la recorta.
(() => {
  if (window.selectorComboActivo) {
    return; // the script may be loaded both statically and by cargarScripts()
  }
  window.selectorComboActivo = true;

  const widgets = [];
  const resincronizarTodos = () => widgets.forEach((w) => w.actualizar());
  let contador = 0;

  // Searchable variant (<select class="selector-combo" data-buscable>, for people: profesionales, cuentas): a text
  // field instead of the button; nothing is listed until something is typed, then only matching visible options.
  // The <select> stays the source of truth, so cascades that hide options keep working. Empty text = the '' option.
  const normalizar = (texto) => texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLocaleLowerCase().trim();

  function mejorarBuscable(select) {
    const wrapper = document.createElement('div');
    wrapper.className = 'buscador-combo';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    const input = document.createElement('input');
    input.type = 'text';
    input.id = (select.id || 'selector-' + ++contador) + '_buscar';
    input.className = 'selector-combo-buscar';
    input.autocomplete = 'off';
    input.placeholder = ' ';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    wrapper.appendChild(input);

    const lista = document.createElement('ul');
    lista.className = 'buscador-combo-lista';
    lista.setAttribute('role', 'listbox');
    lista.id = input.id + '_opciones';
    lista.hidden = true;
    wrapper.appendChild(lista);
    input.setAttribute('aria-controls', lista.id);

    const etiqueta = select.id ? document.querySelector('label[for="' + select.id + '"]') : null;
    if (etiqueta) {
      etiqueta.htmlFor = input.id;
    } else if (select.getAttribute('aria-label')) {
      input.setAttribute('aria-label', select.getAttribute('aria-label'));
    }

    let activa = -1;
    let coincidencias = [];

    const textoElegido = () => {
      const opcion = select.options[select.selectedIndex];
      return opcion && opcion.value !== '' ? opcion.textContent.trim() : '';
    };

    const ubicar = () => {
      const r = input.getBoundingClientRect();
      const alto = lista.offsetHeight;
      const arriba = r.bottom + 5 + alto > window.innerHeight && r.top - 5 - alto > 0;
      lista.style.position = 'fixed';
      lista.style.top = (arriba ? r.top - 5 - alto : r.bottom + 5) + 'px';
      lista.style.left = r.left + 'px';
      lista.style.right = 'auto';
      lista.style.width = r.width + 'px';
    };

    const cerrar = () => {
      lista.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      activa = -1;
      window.removeEventListener('scroll', ubicar, true);
      window.removeEventListener('resize', ubicar);
    };

    const fijarValor = (valor) => {
      if (select.value !== valor) {
        select.value = valor;
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
    };

    const marcarActiva = (indice) => {
      const items = lista.querySelectorAll('li[role="option"]');
      items.forEach((li, i) => li.setAttribute('aria-selected', i === indice ? 'true' : 'false'));
      activa = indice;
      if (items[indice]) {
        input.setAttribute('aria-activedescendant', items[indice].id);
        items[indice].scrollIntoView({ block: 'nearest' });
      }
    };

    const elegir = (opcion) => {
      fijarValor(opcion.value);
      input.value = opcion.textContent.trim();
      cerrar();
    };

    const mostrar = () => {
      const texto = normalizar(input.value);
      if (texto === '') {
        cerrar();
        return;
      }
      coincidencias = Array.from(select.options)
        .filter((o) => o.value !== '' && !o.hidden && normalizar(o.textContent).includes(texto));
      lista.innerHTML = '';
      if (!coincidencias.length) {
        const vacio = document.createElement('li');
        vacio.className = 'buscador-combo-vacio';
        vacio.textContent = 'Sin resultados';
        lista.appendChild(vacio);
      }
      coincidencias.forEach((opcion, indice) => {
        const li = document.createElement('li');
        li.id = lista.id + '_' + indice;
        li.setAttribute('role', 'option');
        li.textContent = opcion.textContent.trim();
        li.addEventListener('mousedown', (evento) => {
          evento.preventDefault();
          elegir(opcion);
          input.blur(); // picked with the mouse: the box deselects (keyboard picks keep focus)
        });
        lista.appendChild(li);
      });
      lista.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      ubicar();
      window.addEventListener('scroll', ubicar, true);
      window.addEventListener('resize', ubicar);
      marcarActiva(-1);
    };

    input.addEventListener('input', () => {
      if (input.value.trim() === '') {
        fijarValor('');
      }
      mostrar();
    });

    input.addEventListener('keydown', (evento) => {
      if (evento.key === 'ArrowDown') {
        evento.preventDefault();
        if (lista.hidden) {
          mostrar();
        }
        marcarActiva(Math.min(activa + 1, coincidencias.length - 1));
      } else if (evento.key === 'ArrowUp') {
        evento.preventDefault();
        marcarActiva(Math.max(activa - 1, 0));
      } else if (evento.key === 'Enter') {
        if (!lista.hidden && coincidencias[activa]) {
          evento.preventDefault();
          elegir(coincidencias[activa]);
        }
      } else if (evento.key === 'Escape') {
        cerrar();
      }
    });

    // Leaving with half-typed text goes back to the current choice.
    input.addEventListener('blur', () => {
      cerrar();
      input.value = textoElegido();
    });

    const actualizar = () => {
      input.disabled = select.disabled;
      if (select.hasAttribute('aria-invalid')) {
        input.setAttribute('aria-invalid', 'true');
      } else {
        input.removeAttribute('aria-invalid');
      }
      if (document.activeElement !== input) {
        input.value = textoElegido();
      }
    };

    select.addEventListener('change', resincronizarTodos);
    new MutationObserver(actualizar).observe(select, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['disabled', 'aria-invalid'],
    });
    widgets.push({ actualizar });
    actualizar();
  }

  function mejorar(select) {
    if (select.dataset.selectorCombo) {
      return;
    }
    select.dataset.selectorCombo = '1';
    if (select.dataset.buscable !== undefined) {
      mejorarBuscable(select);
      return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'buscador-combo';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    const boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'selector-combo-boton';
    wrapper.appendChild(boton);

    const lista = document.createElement('ul');
    lista.className = 'buscador-combo-lista';
    lista.setAttribute('role', 'listbox');
    lista.id = (select.id || 'selector-' + ++contador) + '_opciones';
    lista.hidden = true;
    wrapper.appendChild(lista);

    // The native <select> is hidden, so the button and the list need the label's (or aria-label's) name.
    const etiqueta = select.id ? document.querySelector('label[for="' + select.id + '"]') : null;
    if (etiqueta) {
      etiqueta.id = etiqueta.id || select.id + '_etiqueta';
      boton.id = boton.id || select.id + '_boton';
      boton.setAttribute('aria-labelledby', etiqueta.id + ' ' + boton.id);
      lista.setAttribute('aria-labelledby', etiqueta.id);
      etiqueta.addEventListener('click', () => boton.focus());
    } else if (select.getAttribute('aria-label')) {
      boton.setAttribute('aria-label', select.getAttribute('aria-label'));
      lista.setAttribute('aria-label', select.getAttribute('aria-label'));
    }

    boton.setAttribute('aria-haspopup', 'listbox');
    boton.setAttribute('aria-expanded', 'false');
    boton.setAttribute('aria-controls', lista.id);

    let activa = -1;

    const opcionesVisibles = () => Array.from(select.options).filter((o) => !o.hidden);

    // Fixed position under (or above, if there is no room) the button.
    const ubicar = () => {
      const r = boton.getBoundingClientRect();
      const alto = lista.offsetHeight;
      const arriba = r.bottom + 5 + alto > window.innerHeight && r.top - 5 - alto > 0;
      lista.style.position = 'fixed';
      lista.style.top = (arriba ? r.top - 5 - alto : r.bottom + 5) + 'px';
      lista.style.left = r.left + 'px';
      lista.style.right = 'auto';
      lista.style.width = r.width + 'px';
    };

    const cerrar = () => {
      lista.hidden = true;
      boton.setAttribute('aria-expanded', 'false');
      boton.removeAttribute('aria-activedescendant');
      activa = -1;
      window.removeEventListener('scroll', ubicar, true);
      window.removeEventListener('resize', ubicar);
    };

    const actualizar = () => {
      const elegida = select.options[select.selectedIndex];
      boton.textContent = elegida ? elegida.textContent.trim() : '';
      boton.disabled = select.disabled;
      // A filter's "Todos"/"Todas" default is shown as a real choice; other '' options ("Elegí ...") are hints.
      const enDefault = select.value === '';
      boton.classList.toggle('selector-combo-en-default', enDefault);
      boton.classList.toggle('selector-combo-placeholder', enDefault && !/^tod[ao]s$/i.test(boton.textContent));
      if (select.hasAttribute('aria-invalid')) {
        boton.setAttribute('aria-invalid', 'true');
      } else {
        boton.removeAttribute('aria-invalid');
      }
      if (!lista.hidden) {
        cerrar();
      }
    };

    const marcarActiva = (indice) => {
      const items = lista.querySelectorAll('li');
      items.forEach((li, i) => li.setAttribute('aria-selected', i === indice ? 'true' : 'false'));
      activa = indice;
      const li = items[indice];
      if (li) {
        boton.setAttribute('aria-activedescendant', li.id);
        li.scrollIntoView({ block: 'nearest' });
      }
    };

    const elegir = (opcion) => {
      if (select.value !== opcion.value) {
        select.value = opcion.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
      actualizar();
      cerrar();
      boton.focus();
    };

    const abrir = () => {
      const visibles = opcionesVisibles();
      lista.innerHTML = '';
      visibles.forEach((opcion, indice) => {
        const li = document.createElement('li');
        li.id = lista.id + '_' + indice;
        li.setAttribute('role', 'option');
        li.textContent = opcion.textContent.trim();
        li.setAttribute('aria-selected', opcion.value === select.value ? 'true' : 'false');
        li.addEventListener('mousedown', (evento) => {
          evento.preventDefault();
          elegir(opcion);
        });
        lista.appendChild(li);
      });
      lista.hidden = false;
      ubicar();
      window.addEventListener('scroll', ubicar, true);
      window.addEventListener('resize', ubicar);
      boton.setAttribute('aria-expanded', 'true');
      marcarActiva(visibles.findIndex((o) => o.value === select.value));
    };

    boton.addEventListener('click', () => {
      if (boton.disabled) {
        return;
      }
      if (lista.hidden) {
        abrir();
      } else {
        cerrar();
      }
    });

    boton.addEventListener('keydown', (evento) => {
      if (evento.key === 'ArrowDown') {
        evento.preventDefault();
        if (lista.hidden) {
          abrir();
        } else {
          marcarActiva(Math.min(activa + 1, lista.children.length - 1));
        }
      } else if (evento.key === 'ArrowUp') {
        evento.preventDefault();
        if (!lista.hidden) {
          marcarActiva(Math.max(activa - 1, 0));
        }
      } else if (evento.key === 'Enter' || evento.key === ' ') {
        evento.preventDefault();
        if (!lista.hidden && activa >= 0) {
          elegir(opcionesVisibles()[activa]);
        } else if (lista.hidden) {
          abrir();
        }
      } else if (evento.key === 'Escape') {
        cerrar();
      }
    });

    boton.addEventListener('blur', cerrar);
    select.addEventListener('change', resincronizarTodos);

    const observer = new MutationObserver(actualizar);
    observer.observe(select, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['disabled', 'aria-invalid'],
    });

    widgets.push({ actualizar });
    actualizar();
  }

  const buscar = (raiz) => {
    if (raiz.matches && raiz.matches('select.selector-combo')) {
      mejorar(raiz);
    }
    if (raiz.querySelectorAll) {
      raiz.querySelectorAll('select.selector-combo').forEach(mejorar);
    }
  };

  buscar(document);
  new MutationObserver((cambios) => {
    cambios.forEach((cambio) => cambio.addedNodes.forEach((nodo) => {
      if (nodo.nodeType === Node.ELEMENT_NODE) {
        buscar(nodo);
      }
    }));
  }).observe(document.body, { childList: true, subtree: true });
})();
