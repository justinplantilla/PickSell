// Sign out asks first. Without JS (or <dialog> support) the form simply submits as before.
const dialog = document.getElementById('logoutDialog');

if (dialog && typeof dialog.showModal === 'function') {
    let pendingForm = null;

    document.addEventListener('submit', event => {
        const form = event.target.closest('form[data-confirm-logout]');
        if (!form || form.dataset.confirmed === 'true') return;
        event.preventDefault();
        pendingForm = form;
        dialog.showModal();
        dialog.querySelector('[data-dialog-cancel]')?.focus();
    });

    dialog.addEventListener('close', () => {
        if (dialog.returnValue === 'confirm' && pendingForm) {
            pendingForm.dataset.confirmed = 'true';
            HTMLFormElement.prototype.submit.call(pendingForm);
        }
        pendingForm = null;
        dialog.returnValue = '';
    });

    // A click on the backdrop (outside the panel) cancels.
    dialog.addEventListener('click', event => {
        if (event.target === dialog) dialog.close('cancel');
    });
}

// Small page helpers, declared in the markup instead of inline handlers.
// data-check-all="<selector>": a checkbox that ticks or clears every matching checkbox.
document.addEventListener('change', event => {
    const master = event.target.closest('input[type="checkbox"][data-check-all]');
    if (master) document.querySelectorAll(master.dataset.checkAll).forEach(box => { box.checked = master.checked; });
});
// data-filter-list="<selector>": a search box that hides the matching items whose data-brgy does not contain the text.
document.addEventListener('input', event => {
    const box = event.target.closest('[data-filter-list]');
    if (!box) return;
    const query = box.value.toLowerCase();
    document.querySelectorAll(box.dataset.filterList).forEach(item => { item.style.display = (item.dataset.brgy || '').includes(query) ? '' : 'none'; });
});
document.addEventListener('click', event => {
    // data-check-visible="<selector>": tick the unchecked, not-taken boxes among the items still shown.
    const tick = event.target.closest('[data-check-visible]');
    if (tick) {
        document.querySelectorAll(tick.dataset.checkVisible).forEach(item => {
            if (item.style.display === 'none') return;
            const input = item.querySelector('input:not(:checked)');
            if (input && !input.dataset.taken) input.checked = true;
        });
    }
    // data-confirm on a submit button: ask before the form is sent.
    const ask = event.target.closest('button[data-confirm]');
    if (ask && !window.confirm(ask.dataset.confirm)) event.preventDefault();
});
document.addEventListener('change', event => {
    // data-submit-on-change: a filter select that applies itself.
    if (event.target.matches('[data-submit-on-change]')) event.target.form?.submit();
    // data-fleet-picker: choosing a fleet vehicle fills the trip's plate and driver fields.
    const picker = event.target.closest('select[data-fleet-picker]');
    if (picker) {
        const option = picker.selectedOptions[0];
        picker.form.vehicle_plate.value = option?.dataset.plate || '';
        picker.form.driver_name.value = option?.dataset.driver || '';
    }
});

