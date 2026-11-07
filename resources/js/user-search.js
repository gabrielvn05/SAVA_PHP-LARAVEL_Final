function normalizar(texto) {
    return (texto || '')
        .toLocaleLowerCase('es')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function coincide(persona, consulta) {
    const q = normalizar(consulta).trim();
    if (!q) {
        return false;
    }
    const texto = normalizar(`${persona.nombre} ${persona.rol} ${persona.email} ${persona.cedula}`);
    if (texto.includes(q)) {
        return true;
    }
    const digitos = String(persona.cedula || '').replace(/\D/g, '');
    const qDigitos = q.replace(/\D/g, '');

    return qDigitos.length > 0 && digitos.includes(qDigitos);
}

function linea(clase, texto) {
    const nodo = document.createElement('span');
    nodo.className = clase;
    nodo.textContent = texto;

    return nodo;
}

function aplicarAyuda(hint, plantilla, persona) {
    if (!hint) {
        return;
    }
    if (!plantilla) {
        return;
    }
    if (!persona) {
        hint.textContent = plantilla;

        return;
    }
    hint.textContent = plantilla
        .replaceAll('{nombre}', persona.nombre)
        .replaceAll('{email}', persona.email || '')
        .replaceAll('{rol}', persona.rol || '')
        .replaceAll('{estado}', persona.activo ? 'Activa' : 'Inactiva');
}

function setupUserSearch(root) {
    if (root.dataset.userSearchReady === '1') {
        return;
    }
    root.dataset.userSearchReady = '1';

    const input = root.querySelector('[data-user-search-input]');
    const hidden = root.querySelector('[data-user-search-value]');
    const list = root.querySelector('[data-user-search-list]');
    const dataEl = root.querySelector('[data-user-search-data]');
    if (!input || !hidden || !list || !dataEl) {
        return;
    }

    const personas = JSON.parse(dataEl.textContent || '[]');
    const allowAll = root.dataset.allowAll === '1';
    const allLabel = root.dataset.allLabel || 'Todos';
    const required = root.dataset.required === '1';
    const hint = root.dataset.hint ? document.querySelector(root.dataset.hint) : null;
    const hintEmpty = root.dataset.hintEmpty || '';
    const hintSelected = root.dataset.hintSelected || '';
    const form = root.closest('form');
    let activo = -1;
    let seleccionado = null;

    function cerrar() {
        list.hidden = true;
        root.classList.remove('is-open');
        input.setAttribute('aria-expanded', 'false');
        activo = -1;
    }

    function abrir() {
        list.hidden = false;
        root.classList.add('is-open');
        input.setAttribute('aria-expanded', 'true');
    }

    function elegir(persona) {
        seleccionado = persona;
        hidden.value = persona ? persona.id : '';
        input.value = persona ? persona.nombre : '';
        input.setCustomValidity('');
        aplicarAyuda(hint, persona ? hintSelected : hintEmpty, persona);
        cerrar();
    }

    function elegirTodos() {
        seleccionado = null;
        hidden.value = '';
        input.value = '';
        input.setCustomValidity('');
        aplicarAyuda(hint, hintEmpty, null);
        cerrar();
    }

    function consultaActual() {
        if (seleccionado && input.value === seleccionado.nombre) {
            return '';
        }

        return input.value;
    }

    function opcionPersona(persona) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'user-search__option user-search__option--person';
        boton.setAttribute('role', 'option');
        boton.appendChild(linea('user-search__avatar', persona.iniciales || '?'));
        const datos = document.createElement('span');
        datos.className = 'user-search__person';
        datos.appendChild(linea('user-search__name', persona.nombre));
        let detalle = persona.email || '';
        if (persona.cedula) {
            detalle += (detalle ? ' · ' : '') + persona.cedula;
        }
        datos.appendChild(linea('user-search__meta', detalle));
        boton.appendChild(datos);
        const rol = linea('user-search__role', persona.rol || '');
        if (!persona.activo) {
            rol.classList.add('is-inactive');
        }
        boton.appendChild(rol);
        boton.addEventListener('mousedown', (evento) => {
            evento.preventDefault();
            elegir(persona);
        });

        return boton;
    }

    function render() {
        const consulta = consultaActual();
        list.replaceChildren();
        activo = -1;
        const q = normalizar(consulta).trim();

        if (q === '') {
            if (allowAll) {
                const item = document.createElement('li');
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 'user-search__option';
                boton.setAttribute('role', 'option');
                boton.textContent = allLabel;
                boton.addEventListener('mousedown', (evento) => {
                    evento.preventDefault();
                    elegirTodos();
                });
                item.appendChild(boton);
                list.appendChild(item);
            }
            const aviso = document.createElement('li');
            aviso.className = 'user-search__empty';
            aviso.textContent = 'Escriba un nombre, correo o cédula.';
            list.appendChild(aviso);
            abrir();

            return;
        }

        const visibles = personas.filter((persona) => coincide(persona, consulta)).slice(0, 8);
        if (visibles.length === 0) {
            const vacio = document.createElement('li');
            vacio.className = 'user-search__empty';
            vacio.textContent = 'Ninguna cuenta coincide con esa búsqueda.';
            list.appendChild(vacio);
            abrir();

            return;
        }

        visibles.forEach((persona) => {
            const item = document.createElement('li');
            item.appendChild(opcionPersona(persona));
            list.appendChild(item);
        });
        abrir();
    }

    function marcarActivo(delta) {
        const opciones = list.querySelectorAll('.user-search__option');
        if (!opciones.length) {
            return;
        }
        activo = (activo + delta + opciones.length) % opciones.length;
        opciones.forEach((opcion, indice) => {
            opcion.classList.toggle('is-active', indice === activo);
        });
        opciones[activo].scrollIntoView({ block: 'nearest' });
    }

    input.addEventListener('focus', render);
    input.addEventListener('input', () => {
        seleccionado = null;
        hidden.value = '';
        input.setCustomValidity('');
        aplicarAyuda(hint, hintEmpty, null);
        render();
    });
    input.addEventListener('keydown', (evento) => {
        if (evento.key === 'ArrowDown') {
            evento.preventDefault();
            if (list.hidden) {
                render();
            }
            marcarActivo(1);
        } else if (evento.key === 'ArrowUp') {
            evento.preventDefault();
            marcarActivo(-1);
        } else if (evento.key === 'Enter' && !list.hidden) {
            const opciones = list.querySelectorAll('.user-search__option');
            if (opciones.length) {
                evento.preventDefault();
                opciones[activo >= 0 ? activo : 0].dispatchEvent(new MouseEvent('mousedown'));
            }
        } else if (evento.key === 'Escape') {
            cerrar();
        }
    });
    document.addEventListener('click', (evento) => {
        if (!root.contains(evento.target)) {
            cerrar();
        }
    });

    form?.addEventListener('submit', (evento) => {
        const texto = input.value.trim();
        const coincideSeleccion = seleccionado && texto === seleccionado.nombre;
        if (required && !hidden.value) {
            evento.preventDefault();
            input.setCustomValidity('Elija una cuenta de la lista.');
            input.reportValidity();
            input.focus();

            return;
        }
        if (allowAll && texto !== '' && !coincideSeleccion && hidden.value === '') {
            evento.preventDefault();
            input.setCustomValidity('Elija una cuenta de la lista o deje el campo vacío para ver todos.');
            input.reportValidity();
            input.focus();
        }
    });

    if (hidden.value) {
        const previa = personas.find((persona) => persona.id === hidden.value);
        if (previa) {
            seleccionado = previa;
            input.value = previa.nombre;
            aplicarAyuda(hint, hintSelected, previa);
        }
    } else {
        aplicarAyuda(hint, hintEmpty, null);
    }
}

export function initUserSearch(root = document) {
    root.querySelectorAll('[data-user-search]').forEach(setupUserSearch);
}
