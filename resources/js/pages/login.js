import { initializeTheme } from '../shared/theme';

initializeTheme();

const passwordToggle = document.querySelector('[data-password-toggle]');
passwordToggle?.addEventListener('click', () => {
    const input = document.getElementById(passwordToggle.dataset.passwordToggle);
    if (!input) return;

    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    passwordToggle.style.color = isText ? '' : 'var(--coral)';
});

const loginForm = document.getElementById('loginForm');
loginForm?.addEventListener('submit', () => {
    const submitButton = loginForm.querySelector('.btn-submit');
    submitButton?.classList.add('is-submitting');
    if (submitButton) submitButton.textContent = 'Logging in...';
});
