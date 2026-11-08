const calendarIcon = `
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <rect x="3.5" y="4.5" width="17" height="16" rx="2" stroke="currentColor" stroke-width="1.75"/>
        <path d="M3.5 9.5h17M8 3v3.5M16 3v3.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
    </svg>
`;

const chevronIcon = `
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
`;

const checkIcon = `
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M5 12.5 9.2 17 19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
`;

let controlSeq = 0;

function nextId(prefix) {
    controlSeq += 1;
    return `${prefix}-${controlSeq}`;
}

function openDatePicker(input) {
    if (!(input instanceof HTMLInputElement) || input.disabled || input.readOnly) {
        return;
    }

    if (typeof input.showPicker === 'function') {
        try {
            input.showPicker();
            return;
        } catch {
            // El calendario ya está abierto o el navegador no aceptó el gesto.
        }
    }

    input.focus({ preventScroll: true });
}

export function initDateInputs(root = document) {
    root.querySelectorAll('input[type="date"]').forEach((input) => {
        if (!(input instanceof HTMLInputElement) || input.dataset.dateEnhanced === '1') {
            return;
        }
        if (input.disabled || input.readOnly || input.closest('.date-input-wrap')) {
            return;
        }

        input.dataset.dateEnhanced = '1';
        if (!input.id) {
            input.id = nextId('fecha');
        }

        const label = input.previousElementSibling?.tagName === 'LABEL'
            ? input.previousElementSibling
            : null;
        if (label instanceof HTMLLabelElement && !label.htmlFor) {
            label.htmlFor = input.id;
            label.classList.add('date-input-label');
        }

        const canShowPicker = typeof input.showPicker === 'function';
        const wrap = document.createElement('div');
        wrap.className = 'date-input-wrap';
        wrap.classList.add(canShowPicker ? 'date-input-wrap--api' : 'date-input-wrap--overlay');

        input.parentNode?.insertBefore(wrap, input);
        wrap.appendChild(input);
        input.classList.add('date-input-wrap__input');

        const icon = document.createElement('span');
        icon.className = 'date-input-wrap__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = calendarIcon;
        wrap.appendChild(icon);

        if (canShowPicker) {
            input.addEventListener('pointerdown', (event) => {
                if (event.button !== 0) {
                    return;
                }
                openDatePicker(input);
            });
        }

        if (label instanceof HTMLLabelElement) {
            label.addEventListener('click', (event) => {
                event.preventDefault();
                openDatePicker(input);
            });
        }
    });
}

function optionLabel(option) {
    return (option?.textContent || '').trim();
}

export function initCustomSelects(root = document) {
    const controls = [];
    let active = null;
    let listening = false;

    root.querySelectorAll('#wizard-form select').forEach((select) => {
        if (!(select instanceof HTMLSelectElement) || select.dataset.customSelect === '1') {
            return;
        }
        if (select.multiple || select.size > 1) {
            return;
        }

        select.dataset.customSelect = '1';
        const listId = nextId(select.id || 'opciones');
        const wrap = document.createElement('div');
        wrap.className = 'custom-select';
        select.parentNode?.insertBefore(wrap, select);
        wrap.appendChild(select);
        select.classList.add('custom-select__native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        const siblingLabel = wrap.previousElementSibling?.tagName === 'LABEL'
            ? wrap.previousElementSibling
            : null;
        const explicitLabel = select.id
            ? document.querySelector(`label[for="${CSS.escape(select.id)}"]`)
            : null;
        const label = explicitLabel || siblingLabel;
        if (label && !label.id) {
            label.id = `${listId}-etiqueta`;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'custom-select__trigger';
        button.setAttribute('aria-haspopup', 'listbox');
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-controls', listId);
        if (label?.id) {
            button.setAttribute('aria-labelledby', label.id);
        }
        if (select.required) {
            button.setAttribute('aria-required', 'true');
        }

        const valueEl = document.createElement('span');
        valueEl.className = 'custom-select__value';
        const chevron = document.createElement('span');
        chevron.className = 'custom-select__chevron';
        chevron.setAttribute('aria-hidden', 'true');
        chevron.innerHTML = chevronIcon;
        button.append(valueEl, chevron);
        button.id = `${listId}-boton`;
        if (siblingLabel instanceof HTMLLabelElement && !siblingLabel.htmlFor) {
            siblingLabel.htmlFor = button.id;
        }

        const list = document.createElement('ul');
        list.className = 'custom-select__menu';
        list.id = listId;
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        if (label?.id) {
            list.setAttribute('aria-labelledby', label.id);
        }

        wrap.append(button, list);

        let isOpen = false;
        let activeIndex = -1;
        let query = '';
        let queryTimer = 0;

        const control = {
            contains(node) {
                return wrap.contains(node)
                    || list.contains(node)
                    || (label instanceof Node && (label === node || label.contains(node)));
            },
            position() {
                const rect = button.getBoundingClientRect();
                const gap = 6;
                const spaceBelow = window.innerHeight - rect.bottom - 12;
                const spaceAbove = rect.top - 12;
                const openUp = spaceBelow < 220 && spaceAbove > spaceBelow;
                const available = Math.max(140, openUp ? spaceAbove - gap : spaceBelow - gap);
                const width = Math.max(rect.width, Math.min(280, window.innerWidth - 16));
                let left = rect.left;
                if (left + width > window.innerWidth - 8) {
                    left = Math.max(8, window.innerWidth - width - 8);
                }

                list.style.left = `${left}px`;
                list.style.width = `${width}px`;
                list.style.maxHeight = `${Math.min(520, available)}px`;
                if (openUp) {
                    list.style.top = 'auto';
                    list.style.bottom = `${window.innerHeight - rect.top + gap}px`;
                } else {
                    list.style.bottom = 'auto';
                    list.style.top = `${rect.bottom + gap}px`;
                }
            },
            close() {
                if (!isOpen) {
                    return;
                }
                isOpen = false;
                list.hidden = true;
                wrap.appendChild(list);
                wrap.classList.remove('is-open');
                button.setAttribute('aria-expanded', 'false');
                button.removeAttribute('aria-activedescendant');
                list.querySelectorAll('.is-active').forEach((item) => item.classList.remove('is-active'));
                if (active === control) {
                    active = null;
                }
            },
        };

        function menuItems() {
            return [...list.querySelectorAll('[role="option"]')];
        }

        function syncValue() {
            const option = select.options[select.selectedIndex] ?? null;
            const text = optionLabel(option) || 'Seleccionar';
            valueEl.textContent = text;
            valueEl.title = text;
            valueEl.classList.toggle('is-placeholder', !select.value);
            button.disabled = select.disabled;
            wrap.classList.toggle('is-disabled', select.disabled);
            if (select.value) {
                wrap.classList.remove('is-invalid');
            }
        }

        function highlight(index) {
            const options = menuItems();
            if (!options.length) {
                activeIndex = -1;
                button.removeAttribute('aria-activedescendant');
                return;
            }

            const next = Math.max(0, Math.min(options.length - 1, index));
            options.forEach((item, itemIndex) => {
                item.classList.toggle('is-active', itemIndex === next);
            });
            activeIndex = next;
            const current = options[next];
            button.setAttribute('aria-activedescendant', current.id);
            const top = current.offsetTop;
            const bottom = top + current.offsetHeight;
            if (top < list.scrollTop) {
                list.scrollTop = top;
            } else if (bottom > list.scrollTop + list.clientHeight) {
                list.scrollTop = bottom - list.clientHeight;
            }
        }

        function render() {
            const hasPlaceholder = [...select.options].some((option) => option.value === '');
            let placedSeparator = false;
            list.replaceChildren();

            [...select.options].forEach((option, index) => {
                if (!placedSeparator && hasPlaceholder && option.value !== '') {
                    const separator = document.createElement('li');
                    separator.className = 'custom-select__separator';
                    separator.setAttribute('role', 'presentation');
                    list.appendChild(separator);
                    placedSeparator = true;
                }

                const item = document.createElement('li');
                item.className = 'custom-select__option';
                item.id = `${listId}-opcion-${index}`;
                item.setAttribute('role', 'option');
                const selected = option.value === select.value;
                item.setAttribute('aria-selected', selected ? 'true' : 'false');
                item.classList.toggle('is-selected', selected);
                item.classList.toggle('is-placeholder', option.value === '');
                if (option.disabled) {
                    item.setAttribute('aria-disabled', 'true');
                    item.classList.add('is-disabled');
                }

                const labelEl = document.createElement('span');
                labelEl.className = 'custom-select__option-label';
                labelEl.textContent = optionLabel(option) || 'Seleccionar';
                item.appendChild(labelEl);

                const hint = option.dataset.hint || '';
                if (hint) {
                    const hintEl = document.createElement('span');
                    hintEl.className = 'custom-select__option-hint';
                    hintEl.textContent = hint;
                    item.appendChild(hintEl);
                }

                if (selected && option.value !== '') {
                    const check = document.createElement('span');
                    check.className = 'custom-select__check';
                    check.innerHTML = checkIcon;
                    item.appendChild(check);
                }

                item.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                });
                item.addEventListener('click', () => choose(index));
                list.appendChild(item);
            });

            syncValue();
        }

        function open() {
            if (select.disabled) {
                return;
            }
            controls.forEach((item) => {
                if (item !== control) {
                    item.close();
                }
            });
            active = control;
            isOpen = true;
            list.hidden = false;
            document.body.appendChild(list);
            wrap.classList.add('is-open');
            button.setAttribute('aria-expanded', 'true');
            control.position();
            highlight(select.selectedIndex >= 0 ? select.selectedIndex : 0);
        }

        function choose(index) {
            const option = select.options[index];
            if (!option || option.disabled) {
                return;
            }
            control.close();
            if (select.value !== option.value) {
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                render();
            }
            button.focus();
        }

        function move(step) {
            const total = select.options.length;
            if (!total) {
                return;
            }
            let index = activeIndex;
            for (let attempt = 0; attempt < total; attempt += 1) {
                index = (index + step + total) % total;
                if (!select.options[index]?.disabled) {
                    highlight(index);
                    return;
                }
            }
        }

        button.addEventListener('click', () => {
            if (isOpen) {
                control.close();
            } else {
                open();
            }
        });

        button.addEventListener('keydown', (event) => {
            if (select.disabled) {
                return;
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (!isOpen) {
                    open();
                    return;
                }
                move(event.key === 'ArrowDown' ? 1 : -1);
                return;
            }

            if (event.key === 'Home' || event.key === 'End') {
                event.preventDefault();
                if (!isOpen) {
                    open();
                }
                highlight(event.key === 'Home' ? 0 : select.options.length - 1);
                return;
            }

            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                if (!isOpen) {
                    open();
                } else if (activeIndex >= 0) {
                    choose(activeIndex);
                }
                return;
            }

            if (event.key === 'Escape') {
                if (!isOpen) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();
                control.close();
                return;
            }

            if (event.key === 'Tab') {
                control.close();
                return;
            }

            if (event.key.length !== 1 || event.ctrlKey || event.metaKey || event.altKey) {
                return;
            }

            query += event.key.toLocaleLowerCase('es');
            window.clearTimeout(queryTimer);
            queryTimer = window.setTimeout(() => {
                query = '';
            }, 550);

            const total = select.options.length;
            const start = isOpen ? activeIndex + 1 : 0;
            for (let offset = 0; offset < total; offset += 1) {
                const index = (start + offset) % total;
                const text = optionLabel(select.options[index]).toLocaleLowerCase('es');
                if (!select.options[index].disabled && text.startsWith(query)) {
                    if (!isOpen) {
                        open();
                    }
                    highlight(index);
                    break;
                }
            }
        });

        select.addEventListener('change', render);
        select.addEventListener('invalid', () => {
            wrap.classList.add('is-invalid');
            button.focus();
        });

        render();
        controls.push(control);
    });

    if (listening || controls.length === 0) {
        return;
    }
    listening = true;

    document.addEventListener('click', (event) => {
        if (!active || !(event.target instanceof Node)) {
            return;
        }
        if (controls.some((control) => control.contains(event.target))) {
            return;
        }
        active.close();
    });

    window.addEventListener('resize', () => active?.position());
    window.addEventListener('scroll', () => active?.position(), true);
}
