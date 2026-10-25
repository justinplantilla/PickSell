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
