document.querySelectorAll('form[data-saved-filter-scope]').forEach(form => {
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
