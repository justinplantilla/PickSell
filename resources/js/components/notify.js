/**
 * The one notification toast for every portal (buyer, seller, admin, auth pages, Pickrush).
 *
 *   notify.success('Saved.')   notify.error('Could not save.')
 *   notify.warning('…')        notify.info('…')
 *   notify('Saved.', 'success')   // window.notify and the older window.showToast are the same function
 *
 * Toasts stack in the top-right corner with an icon and a close button. Success and info leave
 * after a few seconds; errors and warnings stay longer. Hovering or focusing a toast pauses it.
 * Errors and warnings are announced right away (role="alert"), the rest politely (role="status").
 *
 * Server messages (session success/info/warning/error and the first validation error) reach the
 * page through partials/flash-toasts, which this module turns into toasts on load.
 */
import '../../css/components/toasts.css';

const TYPES = {
    success: { duration: 3500, role: 'status', icon: '<path d="M20 6 9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>' },
    info: { duration: 3500, role: 'status', icon: '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 11v5M12 8h.01" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>' },
    warning: { duration: 6500, role: 'alert', icon: '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0ZM12 9v4M12 17h.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' },
    error: { duration: 6500, role: 'alert', icon: '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="m15 9-6 6M9 9l6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>' },
};
const MAX_VISIBLE = 4;

function stack() {
    let container = document.querySelector('.ui-toasts');
    if (!container) {
        container = document.createElement('div');
        container.className = 'ui-toasts';
        document.body.appendChild(container);
    }
    return container;
}

function dismiss(toast) {
    if (toast.classList.contains('is-leaving')) return;
    clearTimeout(toast.timer);
    toast.classList.add('is-leaving');
    setTimeout(() => toast.remove(), 220);
}

export function notify(message, type = 'success', { duration } = {}) {
    if (!message) return null;
    const kind = TYPES[type] ? type : 'info';
    const { role, icon } = TYPES[kind];
    const container = stack();

    const toast = document.createElement('div');
    toast.className = `ui-toast ui-toast-${kind}`;
    toast.setAttribute('role', role);
    toast.innerHTML = `<svg class="ui-toast-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">${icon}</svg>`
        + '<span class="ui-toast-text"></span>'
        + '<button type="button" class="ui-toast-close" aria-label="Dismiss notification">×</button>';
    toast.querySelector('.ui-toast-text').textContent = message;
    toast.querySelector('.ui-toast-close').addEventListener('click', () => dismiss(toast));

    const wait = duration ?? TYPES[kind].duration;
    const start = () => { clearTimeout(toast.timer); toast.timer = setTimeout(() => dismiss(toast), wait); };
    const pause = () => clearTimeout(toast.timer);
    toast.addEventListener('mouseenter', pause);
    toast.addEventListener('mouseleave', start);
    toast.addEventListener('focusin', pause);
    toast.addEventListener('focusout', start);

    container.appendChild(toast);
    const visible = [...container.querySelectorAll('.ui-toast:not(.is-leaving)')];
    visible.slice(0, Math.max(0, visible.length - MAX_VISIBLE)).forEach(dismiss);
    start();
    return toast;
}

Object.keys(TYPES).forEach(kind => { notify[kind] = (message, options) => notify(message, kind, options); });

/** Show the messages partials/flash-toasts rendered for this page load. */
function showFlashToasts() {
    document.querySelectorAll('[data-flash-toasts]').forEach(source => {
        source.querySelectorAll('[data-flash]').forEach(item => notify(item.textContent.trim(), item.dataset.flash));
        source.remove();
    });
}

window.notify = notify;
window.showToast = notify;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showFlashToasts);
} else {
    showFlashToasts();
}
