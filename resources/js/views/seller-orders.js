function openHandover(orderId) {
    document.getElementById('handoverForm').action = '/seller/orders/' + orderId + '/handover';
    document.getElementById('handoverModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});
window.openHandover = openHandover;
