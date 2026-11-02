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

// Cancel order — system confirm modal only (no browser confirm)
const cancelOverlay = document.createElement('div');
cancelOverlay.className = 'cancel-confirm-overlay';
cancelOverlay.innerHTML = `
<div class="cancel-confirm-box">
    <h3>Cancel this order?</h3>
    <p>Are you sure you want to cancel this order before pickup? This action cannot be undone.</p>
    <div class="cancel-confirm-actions">
        <button type="button" class="btn btn-outline" id="cancelNo">Keep Order</button>
        <button type="button" class="btn btn-danger" id="cancelYes">Yes, Cancel</button>
    </div>
</div>`;
document.body.appendChild(cancelOverlay);

let pendingCancelForm = null;

function confirmCancel(btn) {
    pendingCancelForm = btn.closest('form');
    cancelOverlay.classList.add('open');
}

cancelOverlay.querySelector('#cancelNo').addEventListener('click', () => {
    cancelOverlay.classList.remove('open');
    pendingCancelForm = null;
});
cancelOverlay.querySelector('#cancelYes').addEventListener('click', () => {
    cancelOverlay.classList.remove('open');
    if (pendingCancelForm) pendingCancelForm.submit();
});
cancelOverlay.addEventListener('click', e => {
    if (e.target === cancelOverlay) {
        cancelOverlay.classList.remove('open');
        pendingCancelForm = null;
    }
});

window.confirmCancel = confirmCancel;

document.addEventListener('click', event => {
    const cancel = event.target.closest('[data-cancel-order]');
    if (cancel) return confirmCancel(cancel);
    const feedback = event.target.closest('[data-open-feedback]');
    if (feedback) return openFeedback(feedback.dataset.openFeedback);
    const star = event.target.closest('[data-set-rating]');
    if (star) return setRating(Number(star.dataset.setRating));
    if (event.target.closest('[data-close-feedback]')) document.getElementById('feedbackModal').classList.remove('open');
});

// Clicking an order's summary (not its buttons, links or forms) opens its details.
document.addEventListener('click', event => {
    const card = event.target.closest('[data-order-card]');
    if (!card) return;
    const toggle = event.target.closest('[data-order-toggle]');
    const inSummary = event.target.closest('.order-card-header, .order-card-body');
    const interactive = event.target.closest('a, button, form, input, select, textarea, label, summary, details');
    if (!toggle && (!inSummary || interactive)) return;

    const button = card.querySelector('[data-order-toggle]');
    const details = document.getElementById(button.getAttribute('aria-controls'));
    const open = details.hidden;
    details.hidden = !open;
    button.setAttribute('aria-expanded', String(open));
    card.classList.toggle('is-open', open);
});
