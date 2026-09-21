document.addEventListener('change', event => {
    if (event.target.matches('[data-submit-on-change]')) event.target.form?.submit();
});

document.addEventListener('click', event => {
    const themeToggle = event.target.closest('[data-theme-toggle]');
    if (themeToggle) window.dispatchEvent(new CustomEvent('picksell:theme-toggle', { detail: themeToggle }));

    const parentToggle = event.target.closest('[data-toggle-parent-class]');
    if (parentToggle) parentToggle.parentElement.classList.toggle(parentToggle.dataset.toggleParentClass);

    const passwordToggle = event.target.closest('[data-password-toggle]');
    if (passwordToggle && typeof window.togglePw === 'function') window.togglePw(passwordToggle);
});

document.addEventListener('submit', event => {
    const form = event.target.closest('[data-confirm]');
    if (form && !window.confirm(form.dataset.confirm)) event.preventDefault();
});

document.addEventListener('click', event => {
    const confirmTarget = event.target.closest('[data-confirm]');
    if (confirmTarget && !confirmTarget.form && !window.confirm(confirmTarget.dataset.confirm)) event.preventDefault();
});
