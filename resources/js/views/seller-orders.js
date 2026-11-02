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
