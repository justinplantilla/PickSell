// Post-login welcome: click or Esc skips it, and the node is removed once its exit animation ends.
const splash = document.querySelector('[data-welcome-splash]');
if (splash) {
    const skip = () => splash.classList.add('is-skipping');
    const onKey = event => { if (event.key === 'Escape') skip(); };

    splash.addEventListener('click', skip);
    document.addEventListener('keydown', onKey);
    splash.addEventListener('animationend', event => {
        if (event.target !== splash) return;
        document.removeEventListener('keydown', onKey);
        splash.remove();
    });
}
