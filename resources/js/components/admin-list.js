// Shared admin list behaviour (orders, products): row preview drawer, copy buttons, checkbox selection,
// the bulk action bar with its dialogs, and toolbar popovers. Markup contract: see admin-list.css and the list views.
const table = document.querySelector('[data-list-table]');
const drawer = document.querySelector('[data-list-drawer]');
const backdrop = document.querySelector('[data-drawer-backdrop]');
const drawerBody = drawer?.querySelector('[data-drawer-body]');
let drawerReturnFocus = null;
let drawerRequest = 0;

function openPreview(row) {
    if (!drawer || !row?.dataset.previewUrl) return;
    drawerReturnFocus = document.activeElement;
    table?.querySelectorAll('.list-row.is-previewing').forEach(other => other.classList.remove('is-previewing'));
    row.classList.add('is-previewing');
    drawerBody.innerHTML = '<p class="oversight-empty">Loading…</p>';
    drawer.hidden = false;
    backdrop.hidden = false;
    document.body.classList.add('list-drawer-open');
    drawer.querySelector('[data-drawer-close]').focus();

    const request = ++drawerRequest;
    fetch(row.dataset.previewUrl, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(response => {
            if (!response.ok) throw new Error(`Preview failed (${response.status})`);
            return response.text();
        })
        .then(html => {
            if (request === drawerRequest) drawerBody.innerHTML = html;
        })
        .catch(() => {
            if (request !== drawerRequest) return;
            const link = row.querySelector('[data-row-link]');
            drawerBody.innerHTML = '<p class="oversight-empty"><strong>Preview unavailable</strong></p>';
            if (link) {
                const open = document.createElement('a');
                open.href = link.href;
                open.className = 'btn btn-coral list-preview-open';
                open.textContent = 'Open full page';
                drawerBody.append(open);
            }
        });
}

function closePreview() {
    if (!drawer || drawer.hidden) return false;
    drawer.hidden = true;
    backdrop.hidden = true;
    document.body.classList.remove('list-drawer-open');
    table?.querySelectorAll('.list-row.is-previewing').forEach(row => row.classList.remove('is-previewing'));
    drawerReturnFocus?.focus?.();
    return true;
}

drawer?.querySelector('[data-drawer-close]')?.addEventListener('click', closePreview);
backdrop?.addEventListener('click', closePreview);

table?.addEventListener('click', event => {
    const copy = event.target.closest('[data-copy]');
    if (copy) {
        const label = copy.title;
        navigator.clipboard?.writeText(copy.dataset.copy).then(() => {
            copy.classList.add('is-copied');
            copy.title = 'Copied';
            setTimeout(() => { copy.classList.remove('is-copied'); copy.title = label; }, 1500);
        });
        return;
    }
    const row = event.target.closest('[data-list-row]');
    if (!row) return;
    const link = event.target.closest('[data-row-link]');
    if (link) {
        // Modified clicks keep their normal "open in new tab" behaviour.
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;
        event.preventDefault();
        openPreview(row);
        return;
    }
    if (event.target.closest('a, button, input, label, select, textarea, form')) return;
    if (window.getSelection()?.toString()) return;
    openPreview(row);
});

// Selection and bulk bar ------------------------------------------------------------------
const bulkbar = document.querySelector('[data-bulkbar]');
const pageToggle = table?.querySelector('[data-select-page]');
const rowBoxes = () => [...(table?.querySelectorAll('[data-select-row]') || [])];
const matchingCount = Number(bulkbar?.dataset.matchingCount || 0);
const noun = bulkbar?.dataset.noun || 'item';
const plural = count => `${noun}${count === 1 ? '' : 's'}`;
let allMatching = false;

function selectedIds() {
    return rowBoxes().filter(box => box.checked).map(box => box.value);
}

function refreshBulkbar() {
    if (!bulkbar) return;
    const ids = selectedIds();
    const boxes = rowBoxes();
    if (pageToggle) {
        pageToggle.checked = boxes.length > 0 && ids.length === boxes.length;
        pageToggle.indeterminate = ids.length > 0 && ids.length < boxes.length;
    }
    boxes.forEach(box => box.closest('tr')?.classList.toggle('is-selected', box.checked));
    if (!ids.length) allMatching = false;
    bulkbar.hidden = ids.length === 0;
    document.body.classList.toggle('list-bulk-open', ids.length > 0);

    const count = allMatching ? matchingCount : ids.length;
    bulkbar.querySelector('[data-bulk-count]').textContent = allMatching
        ? `All ${count.toLocaleString()} matching ${plural(count)} selected`
        : `${count.toLocaleString()} selected`;
    const selectAll = bulkbar.querySelector('[data-select-all-matching]');
    if (selectAll) selectAll.hidden = allMatching || ids.length !== boxes.length;

    const exportLink = bulkbar.querySelector('[data-bulk-export]');
    if (exportLink) {
        const url = new URL(exportLink.dataset.exportBase, window.location.origin);
        if (!allMatching) url.searchParams.set(exportLink.dataset.idsParam, ids.join(','));
        exportLink.href = url.toString();
        exportLink.textContent = allMatching ? `Export ${count.toLocaleString()}` : 'Export selected';
    }
}

pageToggle?.addEventListener('change', () => {
    rowBoxes().forEach(box => { box.checked = pageToggle.checked; });
    allMatching = false;
    refreshBulkbar();
});
table?.addEventListener('change', event => {
    if (!event.target.matches('[data-select-row]')) return;
    allMatching = false;
    refreshBulkbar();
});
bulkbar?.querySelector('[data-select-all-matching]')?.addEventListener('click', () => {
    allMatching = true;
    refreshBulkbar();
});
bulkbar?.querySelector('[data-bulk-clear]')?.addEventListener('click', () => {
    rowBoxes().forEach(box => { box.checked = false; });
    allMatching = false;
    refreshBulkbar();
});

// Each bulk button opens its own dialog; the dialog is told which rows (or "all matching") it applies to.
bulkbar?.querySelectorAll('[data-bulk-open]').forEach(button => button.addEventListener('click', () => {
    const dialog = document.getElementById(button.dataset.bulkOpen);
    if (!dialog) return;
    const ids = allMatching ? [] : selectedIds();
    const holder = dialog.querySelector('[data-bulk-ids]');
    holder.replaceChildren(...ids.map(id => Object.assign(document.createElement('input'), { type: 'hidden', name: holder.dataset.idsName, value: id })));
    const count = allMatching ? matchingCount : ids.length;
    dialog.querySelector('[data-bulk-matching]').value = String(count);
    const scope = allMatching
        ? `all ${count.toLocaleString()} ${plural(count)} matching these filters`
        : `${count.toLocaleString()} selected ${plural(count)}`;
    dialog.querySelectorAll('[data-bulk-scope]').forEach(node => { node.textContent = `Applies to ${scope}.`; });
    dialog.querySelectorAll('[data-bulk-confirm-text]').forEach(node => { node.textContent = `Apply to ${scope}.`; });
    dialog.showModal();
}));
document.querySelectorAll('[data-bulk-dialog] [data-bulk-cancel]').forEach(button => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});

refreshBulkbar();

// Popovers (More filters, Views): one open at a time, closed by an outside click or Escape.
const popovers = () => [...document.querySelectorAll('.list-toolbar details.list-popover')];
document.addEventListener('toggle', event => {
    if (!event.target.matches?.('details.list-popover') || !event.target.open) return;
    popovers().filter(other => other !== event.target).forEach(other => { other.open = false; });
}, true);
document.addEventListener('click', event => {
    popovers().filter(popover => popover.open && !popover.contains(event.target)).forEach(popover => { popover.open = false; });
});
document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    if (closePreview()) return;
    popovers().filter(popover => popover.open).forEach(popover => {
        popover.open = false;
        popover.querySelector('summary')?.focus();
    });
});
