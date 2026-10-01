// Reemplaza la lista nativa de un <select> (rol, estudio, sede,
// profesional, hora, cita) por un listbox propio con la misma estetica que
// buscador-combo.js -pero disparado por un boton, no por texto tipeado: no
// es un buscador-. El <select> real sigue existiendo, oculto, como fuente
// de verdad: scripts de cascada (agendado-cascada.js, citas-por-paciente.js)
// lo siguen manipulando exactamente igual que antes (.value, .disabled,
// options[].hidden, dispatchEvent), y este script solo lo espeja.
//
// Por eso el <script> de este archivo debe ir DESPUES de esos scripts de
// cascada en cada vista: al cargar, ya arranca del estado inicial que
// ellos dejaron filtrado. Despues se resincroniza con cada 'change' -propio
// o disparado por la cascada- y con un MutationObserver para las
// reescrituras completas (hora/cita_id, reconstruidos con innerHTML).
(() => {
  const widgets = [];
  const resincronizarTodos = () => widgets.forEach((w) => w.actualizar());

  document.querySelectorAll('select.selector-combo').forEach((select) => {
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
    lista.id = (select.id || 'selector') + '_opciones';
    lista.hidden = true;
    wrapper.appendChild(lista);

    boton.setAttribute('aria-haspopup', 'listbox');
    boton.setAttribute('aria-expanded', 'false');
    boton.setAttribute('aria-controls', lista.id);

    let activa = -1;

    const opcionesVisibles = () => Array.from(select.options).filter((o) => !o.hidden);

    const cerrar = () => {
      lista.hidden = true;
      boton.setAttribute('aria-expanded', 'false');
      boton.removeAttribute('aria-activedescendant');
      activa = -1;
    };

    const actualizar = () => {
      const elegida = select.options[select.selectedIndex];
      boton.textContent = elegida ? elegida.textContent.trim() : '';
      boton.disabled = select.disabled;
      boton.classList.toggle('selector-combo-placeholder', select.value === '');
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
  });
})();
