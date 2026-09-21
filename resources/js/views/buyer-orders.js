function openFeedback(orderId) {
    document.getElementById('feedbackForm').action = '/buyer/orders/' + orderId + '/feedback';
    document.getElementById('ratingInput').value = '';
    document.querySelectorAll('.star').forEach(s => s.classList.remove('active'));
    document.getElementById('feedbackModal').classList.add('open');
}
function setRating(val) {
    document.getElementById('ratingInput').value = val;
    document.querySelectorAll('.star').forEach(s => {
        s.classList.toggle('active', parseInt(s.dataset.val) <= val);
    });
}
document.getElementById('feedbackModal').addEventListener('click', e => {
    if (e.target === document.getElementById('feedbackModal')) document.getElementById('feedbackModal').classList.remove('open');
});
window.openFeedback = openFeedback;
window.setRating = setRating;
