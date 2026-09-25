function showConfirm(message, { okText = 'Confirm', cancelText = 'Cancel' } = {}) {
    return new Promise(resolve => {
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:9998;display:flex;align-items:center;justify-content:center;';
        overlay.innerHTML = `<div style="background:#fff;border-radius:12px;padding:1.5rem;max-width:360px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,0.18);">
            <p style="font-size:0.9rem;margin-bottom:1.2rem;color:#333;">${message}</p>
            <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
                <button id="confirmCancel" type="button" style="padding:0.45rem 1rem;font-size:0.82rem;border:1.5px solid #ddd6c8;border-radius:6px;background:transparent;cursor:pointer;">${cancelText}</button>
                <button id="confirmOk" type="button" style="padding:0.45rem 1rem;font-size:0.82rem;border:none;border-radius:6px;background:#dc2626;color:#fff;cursor:pointer;">${okText}</button>
            </div>
        </div>`;
        document.body.appendChild(overlay);
        overlay.querySelector('#confirmCancel').addEventListener('click', () => { overlay.remove(); resolve(false); });
        overlay.querySelector('#confirmOk').addEventListener('click', () => { overlay.remove(); resolve(true); });
        const close = () => { overlay.remove(); resolve(false); };
        overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
    });
}
window.showConfirm = showConfirm;

document.addEventListener('change', event => {
    if (event.target.matches('[data-submit-on-change]')) event.target.form?.submit();
});

document.addEventListener('click', event => {
    const logoutButton = event.target.closest('[data-logout-form] button[type="submit"]');
    if (logoutButton) {
        event.preventDefault();
        const form = logoutButton.form;
        if (!form || form.dataset.logoutConfirmed === 'true') return;
        showConfirm(form.dataset.confirmMessage || 'Are you sure you want to log out?').then(confirmed => {
            if (!confirmed) return;
            form.dataset.logoutConfirmed = 'true';
            form.removeAttribute('data-confirm');
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else HTMLFormElement.prototype.submit.call(form);
        });
        return;
    }

    const themeToggle = event.target.closest('[data-theme-toggle]');
    if (themeToggle) window.dispatchEvent(new CustomEvent('picksell:theme-toggle', { detail: themeToggle }));

    const parentToggle = event.target.closest('[data-toggle-parent-class]');
    if (parentToggle) parentToggle.parentElement.classList.toggle(parentToggle.dataset.toggleParentClass);

    const passwordToggle = event.target.closest('[data-password-toggle]');
    if (passwordToggle && typeof window.togglePw === 'function') window.togglePw(passwordToggle);
});

document.addEventListener('submit', event => {
    const form = event.target.closest('[data-confirm]');
    if (form?.dataset.confirmed === 'true') return;
    if (form) {
        event.preventDefault();
        showConfirm(form.dataset.confirm).then(confirmed => {
            if (!confirmed) return;
            form.dataset.confirmed = 'true';
            form.removeAttribute('data-confirm');
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                HTMLFormElement.prototype.submit.call(form);
            }
        });
    }
});

document.addEventListener('click', event => {
    const confirmTarget = event.target.closest('[data-confirm]');
    if (confirmTarget && !confirmTarget.form) {
        event.preventDefault();
        showConfirm(confirmTarget.dataset.confirm).then(confirmed => {
            if (!confirmed) return;
            if (confirmTarget.tagName === 'A') {
                window.location.href = confirmTarget.href;
            } else {
                confirmTarget.click();
            }
        });
    }
});
