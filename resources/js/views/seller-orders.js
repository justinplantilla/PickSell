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
    if (event.target.closest('[data-open-bulk-handover]')) return document.getElementById('bulkHandoverModal').classList.add('open');
    const close = event.target.closest('[data-close-handover]');
    if (close) close.closest('.modal-overlay').classList.remove('open');
});
window.openHandover = openHandover;

// Bulk actions: count the ticked orders and show only the steps at least one of them is at.
const bulkBoxes = [...document.querySelectorAll('[data-bulk-order]')];
const bulkAll = document.querySelector('[data-bulk-all]');
function syncBulk() {
    const ticked = bulkBoxes.filter(box => box.checked);
    const steps = new Set(ticked.flatMap(box => box.dataset.bulkOrder.split(' ').filter(Boolean)));
    document.querySelector('[data-bulk-count]')?.replaceChildren(String(ticked.length));
    document.querySelectorAll('[data-bulk-action]').forEach(button => { button.hidden = ! steps.has(button.dataset.bulkAction); });
    const handoverCount = ticked.filter(box => box.dataset.bulkOrder.split(' ').includes('handover')).length;
    document.querySelector('[data-bulk-handover-count]')?.replaceChildren(String(handoverCount));
    if (bulkAll) {
        bulkAll.checked = ticked.length > 0 && ticked.length === bulkBoxes.length;
        bulkAll.indeterminate = ticked.length > 0 && ticked.length < bulkBoxes.length;
    }
}
bulkAll?.addEventListener('change', () => { bulkBoxes.forEach(box => { box.checked = bulkAll.checked; }); syncBulk(); });
bulkBoxes.forEach(box => box.addEventListener('change', syncBulk));
syncBulk();
