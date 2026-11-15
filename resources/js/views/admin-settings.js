const navigation = document.querySelector('[data-settings-tabs]');
if (navigation) {
    const links = [...navigation.querySelectorAll('[data-settings-tab]')];
    const panels = [...document.querySelectorAll('[data-settings-panel]')];
    const show = tab => {
        const selected = links.some(link => link.dataset.settingsTab === tab) ? tab : 'general';
        panels.forEach(panel => { panel.hidden = panel.id !== selected; });
        links.forEach(link => {
            link.classList.toggle('is-active', link.dataset.settingsTab === selected);
            if (link.dataset.settingsTab === selected) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });
    };
    navigation.addEventListener('click', event => {
        const link = event.target.closest('[data-settings-tab]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault();
        show(link.dataset.settingsTab);
        history.pushState(null, '', link.href);
    });
    window.addEventListener('popstate', () => show(new URL(location.href).searchParams.get('tab')));
    // Keep the URL aligned with the server-selected panel after validation or an invalid tab.
    const active = links.find(link => link.getAttribute('aria-current') === 'page');
    if (active) history.replaceState(null, '', active.href);
}
