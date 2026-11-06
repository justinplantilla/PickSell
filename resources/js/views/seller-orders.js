function openHandover(orderId) {
    document.getElementById('handoverForm').action = '/seller/orders/' + orderId + '/handover';
    document.getElementById('handoverModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});
document.addEventListener('click', event => {
    const open = event.target.closest('[data-open-handover]');
    if (open) return openHandover(open.dataset.openHandover);
    if (event.target.closest('[data-close-handover]')) document.getElementById('handoverModal').classList.remove('open');
});
window.openHandover = openHandover;

// Bulk actions: count the ticked orders and keep the buttons off until one is picked.
const bulkBoxes = [...document.querySelectorAll('[data-bulk-order]')];
const bulkAll = document.querySelector('[data-bulk-all]');
function syncBulk() {
    const picked = bulkBoxes.filter(box => box.checked).length;
    document.querySelector('[data-bulk-count]')?.replaceChildren(String(picked));
    document.querySelectorAll('[data-bulk-action]').forEach(button => { button.disabled = picked === 0; });
    if (bulkAll) {
        bulkAll.checked = picked > 0 && picked === bulkBoxes.length;
        bulkAll.indeterminate = picked > 0 && picked < bulkBoxes.length;
    }
}
bulkAll?.addEventListener('change', () => { bulkBoxes.forEach(box => { box.checked = bulkAll.checked; }); syncBulk(); });
bulkBoxes.forEach(box => box.addEventListener('change', syncBulk));
syncBulk();
