const earningsFilter = document.querySelector('[data-earnings-filter]');
const earningsPreset = earningsFilter?.querySelector('[name="preset"]');

earningsFilter?.querySelectorAll('[name="from"], [name="to"]').forEach(input => {
    input.addEventListener('change', () => {
        if (earningsPreset) earningsPreset.value = 'custom';
    });
});

document.querySelectorAll('[data-earnings-disclosure]').forEach(button => {
    button.addEventListener('click', () => {
        const isExpanded = button.getAttribute('aria-expanded') === 'true';
        const panelId = button.getAttribute('aria-controls');
        const panel = panelId ? document.getElementById(panelId) : null;

        if (!panel) {
            console.error('Unable to find the earnings disclosure panel.', panelId);
            return;
        }

        button.setAttribute('aria-expanded', String(!isExpanded));
        panel.hidden = isExpanded;
    });
});
