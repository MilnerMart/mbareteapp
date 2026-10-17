// Lista con buscador para <select data-searchable> (como select2, sin dependencias).
// El select original queda oculto y es el que se envia; los <optgroup> se muestran como titulos y tambien se buscan.
// Con <select multiple> lo elegido se muestra como etiquetas que se quitan con la x (o con borrar en el buscador vacio).

const normalize = (text) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

let listCount = 0;

export function enhanceSearchableSelect(select) {
    if (select.multiple) {
        enhanceSearchableMultiSelect(select);
        return;
    }
    const optionList = [...select.options]
        .filter((option) => option.value !== '')
        .map((option) => {
            const group = option.parentElement.tagName === 'OPTGROUP' ? option.parentElement.label : '';
            return { value: option.value, text: option.text, group, search: normalize(`${option.text} ${group}`) };
        });
    const listId = `searchable-select-list-${++listCount}`;
    const invalidMessage = select.dataset.invalidMessage || 'Elige una opcion de la lista.';

    const wrapper = document.createElement('div');
    wrapper.className = 'searchable-select';
    const input = document.createElement('input');
    input.type = 'text';
    input.className = select.className.replace('form-select', 'form-control');
    input.placeholder = select.dataset.placeholder || 'Buscar...';
    input.autocomplete = 'off';
    input.required = select.required;
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', listId);
    const list = document.createElement('ul');
    list.id = listId;
    list.className = 'searchable-select-list';
    list.setAttribute('role', 'listbox');
    list.hidden = true;

    // el label apunta al buscador; el select oculto deja de ser obligatorio (lo valida el buscador)
    input.id = select.id;
    select.id = `${select.id}_native`;
    select.required = false;
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    select.classList.add('searchable-select-native');
    select.before(wrapper);
    wrapper.append(input, list, select);

    let visibleList = [];
    let activeIndex = -1;

    const selectedOption = () => optionList.find((option) => option.value === select.value);

    const choose = (option) => {
        select.value = option.value;
        input.value = option.text;
        input.setCustomValidity('');
        select.dispatchEvent(new Event('change', { bubbles: true }));
        close();
    };

    const setActive = (index) => {
        activeIndex = index;
        list.querySelectorAll('[role="option"]').forEach((item, itemIndex) => {
            item.classList.toggle('is-active', itemIndex === index);
            item.setAttribute('aria-selected', String(itemIndex === index));
        });
        const activeItem = list.querySelector('.is-active');
        input.setAttribute('aria-activedescendant', activeItem ? activeItem.id : '');
        activeItem?.scrollIntoView({ block: 'nearest' });
    };

    const render = (query) => {
        const words = normalize(query).split(/\s+/).filter(Boolean);
        visibleList = optionList.filter((option) => words.every((word) => option.search.includes(word)));
        list.replaceChildren();
        let lastGroup = null;
        visibleList.forEach((option, index) => {
            if (option.group && option.group !== lastGroup) {
                const header = document.createElement('li');
                header.className = 'searchable-select-group';
                header.setAttribute('role', 'presentation');
                header.textContent = option.group;
                list.append(header);
                lastGroup = option.group;
            }
            const item = document.createElement('li');
            item.id = `${listId}-${index}`;
            item.className = 'searchable-select-option';
            item.setAttribute('role', 'option');
            item.textContent = option.text;
            // mousedown y no click: el buscador no pierde el foco antes de elegir
            item.addEventListener('mousedown', (event) => {
                event.preventDefault();
                choose(option);
            });
            list.append(item);
        });
        if (!visibleList.length) {
            const empty = document.createElement('li');
            empty.className = 'searchable-select-empty';
            empty.textContent = 'No encontramos resultados';
            list.append(empty);
        }
        setActive(visibleList.length ? 0 : -1);
    };

    const open = () => {
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    function close() {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    }

    input.addEventListener('focus', () => {
        render(selectedOption() ? '' : input.value);
        open();
        input.select();
    });

    input.addEventListener('input', () => {
        select.value = '';
        input.setCustomValidity(input.value ? invalidMessage : '');
        render(input.value);
        open();
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (list.hidden) {
                render(input.value);
                open();
                return;
            }
            const step = event.key === 'ArrowDown' ? 1 : -1;
            setActive(Math.min(Math.max(activeIndex + step, 0), visibleList.length - 1));
        } else if (event.key === 'Enter' && !list.hidden) {
            event.preventDefault();
            if (visibleList[activeIndex]) {
                choose(visibleList[activeIndex]);
            }
        } else if (event.key === 'Escape' && !list.hidden) {
            event.preventDefault();
            close();
        }
    });

    input.addEventListener('blur', () => {
        close();
        const selected = selectedOption();
        if (selected) {
            input.value = selected.text;
            input.setCustomValidity('');
        }
    });

    const initial = selectedOption();
    if (initial) {
        input.value = initial.text;
    }
}

function enhanceSearchableMultiSelect(select) {
    const optionList = [...select.options].map((option) => ({ option, search: normalize(option.text) }));
    const listId = `searchable-select-list-${++listCount}`;

    const wrapper = document.createElement('div');
    wrapper.className = 'searchable-select is-multiple';
    const box = document.createElement('div');
    box.className = select.className.replace('form-select', 'form-control') + ' searchable-select-box';
    const chipList = document.createElement('span');
    chipList.className = 'searchable-select-chips';
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'searchable-select-input';
    input.autocomplete = 'off';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', listId);
    const list = document.createElement('ul');
    list.id = listId;
    list.className = 'searchable-select-list';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-multiselectable', 'true');
    list.hidden = true;

    input.id = select.id;
    select.id = `${select.id}_native`;
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    select.classList.add('searchable-select-native');
    select.before(wrapper);
    box.append(chipList, input);
    wrapper.append(box, list, select);

    let visibleList = [];
    let activeIndex = -1;

    const renderChips = () => {
        chipList.replaceChildren();
        optionList.filter(({ option }) => option.selected).forEach(({ option }) => {
            const chip = document.createElement('span');
            chip.className = 'searchable-select-chip';
            chip.textContent = option.text;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'searchable-select-chip-remove';
            remove.setAttribute('aria-label', `Quitar ${option.text}`);
            remove.innerHTML = '&times;';
            remove.addEventListener('mousedown', (event) => event.preventDefault());
            remove.addEventListener('click', () => toggle(option));
            chip.append(remove);
            chipList.append(chip);
        });
        input.placeholder = select.selectedOptions.length ? '' : (select.dataset.placeholder || 'Buscar...');
    };

    const setActive = (index) => {
        activeIndex = index;
        list.querySelectorAll('[role="option"]').forEach((item, itemIndex) => item.classList.toggle('is-active', itemIndex === index));
        const activeItem = list.querySelector('.is-active');
        input.setAttribute('aria-activedescendant', activeItem ? activeItem.id : '');
        activeItem?.scrollIntoView({ block: 'nearest' });
    };

    const render = (query) => {
        const words = normalize(query).split(/\s+/).filter(Boolean);
        visibleList = optionList.filter((item) => words.every((word) => item.search.includes(word)));
        list.replaceChildren();
        visibleList.forEach(({ option }, index) => {
            const item = document.createElement('li');
            item.id = `${listId}-${index}`;
            item.className = 'searchable-select-option';
            item.classList.toggle('is-selected', option.selected);
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', String(option.selected));
            item.textContent = option.text;
            item.addEventListener('mousedown', (event) => {
                event.preventDefault();
                toggle(option);
            });
            list.append(item);
        });
        if (!visibleList.length) {
            const empty = document.createElement('li');
            empty.className = 'searchable-select-empty';
            empty.textContent = 'No encontramos resultados';
            list.append(empty);
        }
        setActive(visibleList.length ? Math.min(Math.max(activeIndex, 0), visibleList.length - 1) : -1);
    };

    // la lista queda abierta para elegir varios seguidos
    function toggle(option) {
        option.selected = !option.selected;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        renderChips();
        if (!list.hidden) {
            render(input.value);
        }
    }

    const open = () => {
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    };

    box.addEventListener('mousedown', (event) => {
        if (event.target !== input) {
            event.preventDefault();
            input.focus();
        }
    });

    input.addEventListener('focus', () => {
        activeIndex = 0;
        render(input.value);
        open();
    });

    input.addEventListener('input', () => {
        activeIndex = 0;
        render(input.value);
        open();
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (list.hidden) {
                render(input.value);
                open();
                return;
            }
            const step = event.key === 'ArrowDown' ? 1 : -1;
            setActive(Math.min(Math.max(activeIndex + step, 0), visibleList.length - 1));
        } else if (event.key === 'Enter' && !list.hidden) {
            event.preventDefault();
            if (visibleList[activeIndex]) {
                toggle(visibleList[activeIndex].option);
            }
        } else if (event.key === 'Escape' && !list.hidden) {
            event.preventDefault();
            close();
        } else if (event.key === 'Backspace' && input.value === '' && select.selectedOptions.length) {
            toggle(select.selectedOptions[select.selectedOptions.length - 1]);
        }
    });

    input.addEventListener('blur', () => {
        close();
        input.value = '';
    });

    renderChips();
}

document.querySelectorAll('select[data-searchable]').forEach(enhanceSearchableSelect);
