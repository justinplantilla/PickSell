// Rows (cards on phones) that open a page: a click or tap anywhere on a [data-row-href] row follows its
// link, like clicking the title. Links, buttons and form fields inside keep their own behaviour, a text
// selection is not a click, and Ctrl/Cmd/middle click opens the page in a new tab.
document.addEventListener('click', event => {
    const row = event.target.closest('[data-row-href]');
    if (!row || event.target.closest('a, button, input, label, select, textarea, summary')) return;
    if (window.getSelection()?.toString()) return;
    if (event.ctrlKey || event.metaKey) window.open(row.dataset.rowHref, '_blank', 'noopener');
    else window.location.href = row.dataset.rowHref;
});
document.addEventListener('auxclick', event => {
    const row = event.target.closest('[data-row-href]');
    if (row && event.button === 1 && !event.target.closest('a')) window.open(row.dataset.rowHref, '_blank', 'noopener');
});
