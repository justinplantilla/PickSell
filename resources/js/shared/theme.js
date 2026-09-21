export function initializeTheme(storageKey = 'darkMode') {
    const isDark = localStorage.getItem(storageKey) === 'true';
    document.body.classList.toggle('dark', isDark);
}

export function toggleTheme(storageKey = 'darkMode') {
    const isDark = document.body.classList.toggle('dark');
    localStorage.setItem(storageKey, String(isDark));
}

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-theme-toggle]');
    if (!toggle) return;

    toggleTheme(toggle.dataset.themeStorageKey || 'darkMode');
});
