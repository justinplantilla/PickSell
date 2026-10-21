function savedFilterKey(form) {
    return `picksell:admin:saved-filters:${form.dataset.savedFilterUser}:${form.dataset.savedFilterScope}`;
}

function readSavedFilters(storageKey) {
    const saved = JSON.parse(localStorage.getItem(storageKey) || '[]');
    if (!Array.isArray(saved) || saved.some(filter => typeof filter?.name !== 'string' || typeof filter?.query !== 'string')) {
        throw new Error('Saved filter data has an invalid format.');
    }
    return saved;
}

// Compact "Views ▾" dropdown: one control that lists, applies, saves and deletes saved views.
function renderSavedFilterMenu(form) {
    const mount = form.querySelector('[data-saved-filter-mount]') || form;
    const storageKey = savedFilterKey(form);
    const menu = document.createElement('details');
    menu.className = 'list-popover saved-filter-menu';
    menu.innerHTML = '<summary class="btn btn-outline btn-sm"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg> Views</summary>'
        + '<div class="list-popover-panel saved-filter-panel"><p class="saved-filter-heading">Saved views</p><ul class="saved-filter-list" role="list"></ul>'
        + '<button type="button" class="btn btn-outline btn-sm saved-filter-save">Save current view</button><p class="saved-filter-status" role="status" aria-live="polite"></p></div>';
    mount.append(menu);
    const list = menu.querySelector('.saved-filter-list');
    const status = menu.querySelector('.saved-filter-status');
    const saveButton = menu.querySelector('.saved-filter-save');
    let saved = [];

    const persist = () => localStorage.setItem(storageKey, JSON.stringify(saved));
    const render = () => {
        list.replaceChildren();
        if (!saved.length) {
            const empty = document.createElement('li');
            empty.className = 'saved-filter-empty';
            empty.textContent = 'No saved views yet.';
            list.append(empty);
        }
        saved.forEach((filter, index) => {
            const item = document.createElement('li');
            const link = document.createElement('a');
            const destination = new URL(form.action || window.location.href, window.location.origin);
            destination.search = filter.query;
            link.href = destination.toString();
            link.textContent = filter.name;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'saved-filter-remove';
            remove.setAttribute('aria-label', `Delete saved view ${filter.name}`);
            remove.textContent = '✕';
            remove.addEventListener('click', () => {
                saved.splice(index, 1);
                try {
                    persist();
                    status.textContent = 'Saved view deleted.';
                } catch (error) {
                    status.textContent = `Could not delete view: ${error.message}`;
                }
                render();
            });
            item.append(link, remove);
            list.append(item);
        });
    };

    try {
        saved = readSavedFilters(storageKey);
    } catch (error) {
        status.textContent = `Saved views unavailable: ${error.message}`;
        saveButton.disabled = true;
    }
    render();

    saveButton.addEventListener('click', () => {
        const name = window.prompt('Name this view:')?.trim();
        if (!name) return;
        if (name.length > 60) {
            status.textContent = 'View names must be 60 characters or fewer.';
            return;
        }
        // Save what is applied (the URL), not unsubmitted edits in the form.
        const query = new URLSearchParams(window.location.search);
        query.delete('page');
        saved = saved.filter(filter => filter.name !== name);
        saved.push({ name, query: query.toString() });
        try {
            persist();
            status.textContent = 'View saved on this browser.';
        } catch (error) {
            status.textContent = `Could not save view: ${error.message}`;
        }
        render();
    });
}

document.querySelectorAll('form[data-saved-filter-scope]').forEach(form => {
    if (form.dataset.savedFilterMode === 'menu') {
        renderSavedFilterMenu(form);
        return;
    }
    const controls = document.createElement('div');
    controls.className = 'saved-filter-controls';
    controls.setAttribute('aria-label', 'Saved filters');

    const select = document.createElement('select');
    select.className = 'filter-select';
    select.setAttribute('aria-label', 'Choose a saved filter');
    select.innerHTML = '<option value="">Saved filters…</option>';

    const apply = document.createElement('button');
    apply.type = 'button';
    apply.className = 'btn btn-outline btn-sm';
    apply.textContent = 'Apply saved filter';
    apply.disabled = true;

    const save = document.createElement('button');
    save.type = 'button';
    save.className = 'btn btn-outline btn-sm';
    save.textContent = 'Save current filter';

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'btn btn-outline btn-sm';
    remove.textContent = 'Delete saved filter';
    remove.disabled = true;

    const status = document.createElement('span');
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');

    controls.append(select, apply, save, remove, status);
    form.insertAdjacentElement('afterend', controls);

    const storageKey = `picksell:admin:saved-filters:${form.dataset.savedFilterUser}:${form.dataset.savedFilterScope}`;
    let savedFilters = [];
    try {
        savedFilters = JSON.parse(localStorage.getItem(storageKey) || '[]');
        if (!Array.isArray(savedFilters)) throw new Error('Saved filter data has an invalid format.');
        savedFilters.forEach((filter, index) => {
            if (typeof filter?.name !== 'string' || typeof filter?.query !== 'string') {
                throw new Error('Saved filter data has an invalid format.');
            }
            const option = document.createElement('option');
            option.value = String(index);
            option.textContent = filter.name;
            select.appendChild(option);
        });
    } catch (error) {
        status.textContent = `Saved filters unavailable: ${error.message}`;
        save.disabled = true;
    }

    select.addEventListener('change', () => {
        const index = Number(select.value);
        const filter = savedFilters[index];
        apply.disabled = !filter;
        remove.disabled = !filter;
    });

    apply.addEventListener('click', () => {
        const filter = savedFilters[Number(select.value)];
        if (!filter) return;
        const destination = new URL(form.action || window.location.href, window.location.origin);
        destination.search = filter.query;
        window.location.assign(destination.toString());
    });

    save.addEventListener('click', () => {
        const name = window.prompt('Name this saved filter:')?.trim();
        if (!name) return;
        if (name.length > 60) {
            status.textContent = 'Filter names must be 60 characters or fewer.';
            return;
        }

        const query = new URLSearchParams(new FormData(form));
        savedFilters = savedFilters.filter(filter => filter.name !== name);
        savedFilters.push({ name, query: query.toString() });
        try {
            localStorage.setItem(storageKey, JSON.stringify(savedFilters));
            const option = document.createElement('option');
            option.value = String(savedFilters.length - 1);
            option.textContent = name;
            select.appendChild(option);
            select.value = option.value;
            apply.disabled = false;
            remove.disabled = false;
            status.textContent = 'Filter saved on this browser.';
        } catch (error) {
            status.textContent = `Could not save filter: ${error.message}`;
        }
    });

    remove.addEventListener('click', () => {
        const index = Number(select.value);
        if (!savedFilters[index]) return;
        savedFilters.splice(index, 1);
        try {
            localStorage.setItem(storageKey, JSON.stringify(savedFilters));
            select.options[index + 1]?.remove();
            select.value = '';
            apply.disabled = true;
            remove.disabled = true;
            status.textContent = 'Saved filter deleted.';
        } catch (error) {
            status.textContent = `Could not delete filter: ${error.message}`;
        }
    });
});
