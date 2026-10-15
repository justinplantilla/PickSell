function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('mini');
    document.getElementById('main').classList.toggle('mini');
    localStorage.setItem('logisticsSidebarMini', document.querySelector('.sidebar').classList.contains('mini'));
}
function toggleDark() {
    document.body.classList.toggle('dark');
    localStorage.setItem('logisticsDark', document.body.classList.contains('dark'));
}
if (localStorage.getItem('logisticsSidebarMini') === 'true') { document.querySelector('.sidebar').classList.add('mini'); document.getElementById('main').classList.add('mini'); }
if (localStorage.getItem('logisticsDark') === 'true') document.body.classList.add('dark');
window.toggleSidebar = toggleSidebar;
window.toggleDark = toggleDark;

// Hub subdomain fields: keep the value DNS-safe and preview the resulting portal address as the user types.
document.querySelectorAll('[data-subdomain-input]').forEach(input => {
    const preview = document.getElementById(input.dataset.previewTarget);
    const template = input.dataset.hostTemplate || '{subdomain}';
    const slugify = value => value.toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/-{2,}/g, '-').replace(/^-/, '');
    const render = () => {
        if (preview) preview.textContent = template.replace('{subdomain}', input.value.replace(/-$/, '') || '<subdomain>');
    };
    input.addEventListener('input', () => {
        const cleaned = slugify(input.value);
        if (cleaned !== input.value) input.value = cleaned;
        render();
    });
    input.addEventListener('blur', () => { input.value = input.value.replace(/-+$/, ''); render(); });
    const source = input.dataset.slugSource && document.getElementById(input.dataset.slugSource);
    source?.addEventListener('input', () => {
        if (input.dataset.touched === 'true') return;
        input.value = slugify(source.value).replace(/-+$/, '');
        render();
    });
    input.addEventListener('keydown', () => { input.dataset.touched = 'true'; });
    if (input.value) input.dataset.touched = 'true';
    render();
});
