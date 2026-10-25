import { initializeTheme } from '../shared/theme';

initializeTheme();

// form-behaviors.js calls this for every [data-password-toggle] button on the page.
window.togglePw = button => {
    const input = button.dataset.passwordToggle
        ? document.getElementById(button.dataset.passwordToggle)
        : button.parentElement.querySelector('input');
    if (!input) return;

    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    button.style.color = isText ? '' : 'var(--coral)';
};

const loginForm = document.getElementById('loginForm');
loginForm?.addEventListener('submit', () => {
    const submitButton = loginForm.querySelector('.btn-submit');
    submitButton?.classList.add('is-submitting');
    if (submitButton) submitButton.textContent = 'Logging in...';
});

// Switching between /login and /register slides the brand panel instead of loading a new page; the URL and title follow,
// and Back / Forward switch too. The leaving form is hidden once it has faded out so it drops out of the tab order and layout.
const split = document.querySelector('[data-auth-split]');
if (split) {
    const panes = {
        login: split.querySelector('[data-auth-pane="login"]'),
        register: split.querySelector('[data-auth-pane="register"]'),
    };
    const paths = {
        login: new URL(split.dataset.loginUrl, location.href).pathname,
        register: new URL(split.dataset.registerUrl, location.href).pathname,
    };
    const titles = { login: 'PickSell — Log In', register: 'PickSell — Create Account' };
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let hideTimer;

    const setMode = (mode, { push = true } = {}) => {
        if (!panes[mode] || split.dataset.mode === mode) return;
        const leaving = panes[mode === 'login' ? 'register' : 'login'];
        const entering = panes[mode];

        clearTimeout(hideTimer);
        entering.hidden = false;
        void entering.offsetWidth; // start the entering pane from its hidden pose so it transitions in
        split.dataset.mode = mode;
        entering.classList.add('is-active');
        entering.inert = false;
        leaving.classList.remove('is-active');
        leaving.inert = true;
        document.title = titles[mode];
        if (push) history.pushState({ authMode: mode }, '', paths[mode]);

        restartStagger(entering, '.42s');
        playRoute();

        hideTimer = setTimeout(() => {
            leaving.hidden = true;
            entering.querySelector('.auth-title')?.focus({ preventScroll: true });
        }, reducedMotion.matches ? 250 : 600);
    };

    // ---- Motion (decorative only; none of it changes what the forms do) ----

    // Tagline words rise one by one. The text stays the same, only wrapped in spans.
    const tagline = split.querySelector('.auth-brand-tagline');
    if (tagline && !reducedMotion.matches) {
        const words = tagline.textContent.trim().split(/\s+/);
        tagline.replaceChildren(...words.flatMap((word, index) => {
            const span = document.createElement('span');
            span.className = 'auth-word';
            span.style.setProperty('--i', index);
            span.textContent = word;
            return index ? [document.createTextNode(' '), span] : [span];
        }));
    }

    // Form items enter one by one; the order is the order on the page.
    Object.values(panes).forEach(pane => {
        const inner = pane.querySelector('.auth-pane-inner');
        const items = [...inner.children].flatMap(child => {
            if (child.id === 'loginForm') return [...child.children].filter(item => item.type !== 'hidden');
            if (child.id === 'registerForm') return [...child.querySelectorAll(':scope > .wizard-progress')];
            return [child];
        });
        items.forEach((item, index) => { item.dataset.stagger = ''; item.style.setProperty('--i', index); });
    });
    const restartStagger = (pane, base) => {
        if (reducedMotion.matches) return;
        split.style.setProperty('--auth-base', base);
        pane.classList.remove('is-staggering');
        void pane.offsetWidth;
        pane.classList.add('is-staggering');
    };
    const activePane = panes[split.dataset.mode];
    if (activePane) restartStagger(activePane, '.15s');

    // Delivery route drawn across the brand panel while it switches sides.
    const routeDot = split.querySelector('.auth-route-dot animateMotion');
    let routeTimer;
    const playRoute = () => {
        if (reducedMotion.matches || !split.classList.contains('is-ready')) return;
        clearTimeout(routeTimer);
        split.classList.remove('is-routing');
        void split.offsetWidth;
        split.classList.add('is-routing');
        try { routeDot?.beginElementAt(0.15); } catch { /* SMIL unsupported: the line still draws */ }
        routeTimer = setTimeout(() => split.classList.remove('is-routing'), 1900);
    };

    // Floating objects lean toward the pointer (desktop, fine pointer only).
    const brand = split.querySelector('.auth-brand');
    const finePointer = window.matchMedia('(hover: hover) and (pointer: fine) and (min-width: 901px)');
    let tiltFrame = 0;
    split.addEventListener('pointermove', event => {
        if (reducedMotion.matches || !finePointer.matches || tiltFrame) return;
        tiltFrame = requestAnimationFrame(() => {
            tiltFrame = 0;
            const rect = split.getBoundingClientRect();
            brand.style.setProperty('--px', (((event.clientX - rect.left) / rect.width) * 2 - 1).toFixed(3));
            brand.style.setProperty('--py', (((event.clientY - rect.top) / rect.height) * 2 - 1).toFixed(3));
        });
    });
    split.addEventListener('pointerleave', () => { brand.style.setProperty('--px', 0); brand.style.setProperty('--py', 0); });

    // A field that fails validation (browser check or server error) shakes once.
    const shake = element => {
        if (!element || reducedMotion.matches) return;
        element.classList.remove('is-shaking');
        void element.offsetWidth;
        element.classList.add('is-shaking');
    };
    split.addEventListener('animationend', event => {
        if (event.animationName === 'auth-shake') event.target.classList.remove('is-shaking');
    });
    split.addEventListener('invalid', event => shake(event.target.closest('.form-group, .role-selector, .confirm-row') || event.target), true);
    if (activePane) {
        setTimeout(() => activePane.querySelectorAll('.alert-error, .form-group:has(> .field-error)').forEach(shake), 700);
    }

    // Finished wizard steps get a drawn check mark.
    split.querySelectorAll('.wizard-progress-item').forEach(item => {
        item.insertAdjacentHTML('afterbegin', '<svg class="wizard-check" viewBox="0 0 12 12" aria-hidden="true"><path d="M2 6.5l2.5 2.5L10 3.5" pathLength="1"/></svg>');
    });

    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target) return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || url.search) return;
        const mode = Object.keys(paths).find(key => paths[key] === url.pathname);
        if (!mode) return;
        event.preventDefault();
        setMode(mode);
    });

    window.addEventListener('popstate', () => {
        const mode = Object.keys(paths).find(key => paths[key] === location.pathname);
        if (mode) setMode(mode, { push: false });
    });

    requestAnimationFrame(() => requestAnimationFrame(() => split.classList.add('is-ready')));
}
