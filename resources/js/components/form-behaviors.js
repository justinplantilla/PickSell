import '../../css/components/ui-feedback.css';
import '../../css/components/table-cards.css';

/** Confirm dialog for every portal; resolves true on OK. Escape or a click outside cancels. */
function showConfirm(message, { okText = 'Confirm', cancelText = 'Cancel' } = {}) {
    return new Promise(resolve => {
        const overlay = document.createElement('div');
        overlay.className = 'ui-dialog-overlay';
        overlay.innerHTML = `<div class="ui-dialog" role="alertdialog" aria-modal="true" aria-describedby="uiDialogMessage">
            <p id="uiDialogMessage"></p>
            <div class="ui-dialog-actions"><button type="button" class="ui-dialog-cancel"></button><button type="button" class="ui-dialog-ok"></button></div>
        </div>`;
        overlay.querySelector('#uiDialogMessage').textContent = message;
        overlay.querySelector('.ui-dialog-cancel').textContent = cancelText;
        overlay.querySelector('.ui-dialog-ok').textContent = okText;
        const returnFocus = document.activeElement;
        const done = answer => { overlay.remove(); document.removeEventListener('keydown', onKey); returnFocus?.focus?.(); resolve(answer); };
        const onKey = event => { if (event.key === 'Escape') done(false); };
        overlay.querySelector('.ui-dialog-cancel').addEventListener('click', () => done(false));
        overlay.querySelector('.ui-dialog-ok').addEventListener('click', () => done(true));
        overlay.addEventListener('click', event => { if (event.target === overlay) done(false); });
        document.addEventListener('keydown', onKey);
        document.body.appendChild(overlay);
        overlay.querySelector('.ui-dialog-ok').focus();
    });
}
window.showConfirm = showConfirm;

/** Short status message in the corner; type is 'success' or 'error'. */
function showToast(message, type = 'success') {
    let container = document.querySelector('.ui-toasts');
    if (!container) {
        container = document.createElement('div');
        container.className = 'ui-toasts';
        container.setAttribute('role', 'status');
        container.setAttribute('aria-live', 'polite');
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `ui-toast ui-toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('is-leaving');
        setTimeout(() => toast.remove(), 220);
    }, 2800);
}
window.showToast = showToast;

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
    const form = event.target.closest('form');
    if (!form || form.dataset.confirmed === 'true' || form.hasAttribute('data-no-confirm') || form.hasAttribute('data-logout-form')) return;

    const submitMethod = (event.submitter?.formMethod || form.method).toLowerCase();
    const explicitMessage = event.submitter?.dataset.confirm || form.dataset.confirm;
    const shouldConfirmMutation = ['post', 'put', 'patch', 'delete'].includes(submitMethod)
        && Boolean(form.closest('[data-confirm-mutations]'));
    const action = event.submitter?.textContent.trim();
    const message = explicitMessage
        || form.dataset.confirmMessage
        || `Are you sure you want to ${action || 'submit this change'}?`;
    if (explicitMessage || shouldConfirmMutation) {
        event.preventDefault();
        showConfirm(message).then(confirmed => {
            if (!confirmed) return;
            form.dataset.confirmed = 'true';
            form.removeAttribute('data-confirm');
            if (typeof form.requestSubmit === 'function') {
                // Resubmit with the same button, so its name/value (e.g. vacation_mode=1) is still sent.
                const submitter = event.submitter?.form === form ? event.submitter : undefined;
                form.requestSubmit(submitter);
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

// Product photo placeholders shimmer until their image has loaded (or failed).
const markLoaded = img => img.closest('.product-img-wrap, .product-detail-img')?.classList.add('is-loaded');
document.addEventListener('load', event => { if (event.target.tagName === 'IMG') markLoaded(event.target); }, true);
document.addEventListener('error', event => { if (event.target.tagName === 'IMG') markLoaded(event.target); }, true);
const markReady = () => document.querySelectorAll('.product-img-wrap img, .product-detail-img img').forEach(img => { if (img.complete) markLoaded(img); });
document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', markReady) : markReady();
