const feedbackModal = document.getElementById('feedbackModal');
const feedbackForm = document.getElementById('feedbackForm');

/** Opens the rating modal for one order, showing the product being rated. */
function openFeedback(button) {
    feedbackForm.action = '/buyer/orders/' + button.dataset.openFeedback + '/feedback';
    feedbackForm.reset();
    feedbackForm.querySelectorAll('[data-star-group] input').forEach(input => { input.value = ''; });
    feedbackModal.querySelectorAll('.star').forEach(s => s.classList.remove('active'));
    feedbackModal.querySelector('[data-rate-error]').hidden = true;
    feedbackModal.querySelector('[data-rate-photo-previews]').innerHTML = '';

    const image = feedbackModal.querySelector('[data-rate-image]');
    image.hidden = !button.dataset.image;
    image.src = button.dataset.image || '';
    image.alt = button.dataset.product || '';
    feedbackModal.querySelector('[data-rate-product]').textContent = button.dataset.product || '';
    feedbackModal.querySelector('[data-rate-option]').textContent = button.dataset.option || '';
    feedbackModal.querySelector('[data-rate-store]').textContent = button.dataset.store || 'the seller';
    feedbackModal.classList.add('open');
}
function setRating(star) {
    const group = star.closest('[data-star-group]');
    const val = Number(star.dataset.setRating);
    group.querySelector('input').value = val;
    group.querySelectorAll('.star').forEach(s => s.classList.toggle('active', Number(s.dataset.val) <= val));
}
const MAX_REVIEW_PHOTOS = 3;
const photoInput = feedbackForm.querySelector('[data-rate-photos]');
// Previews of the chosen photos; more than three keeps the first three.
photoInput?.addEventListener('change', () => {
    if (photoInput.files.length > MAX_REVIEW_PHOTOS) {
        const keep = new DataTransfer();
        [...photoInput.files].slice(0, MAX_REVIEW_PHOTOS).forEach(file => keep.items.add(file));
        photoInput.files = keep.files;
    }
    const previews = feedbackModal.querySelector('[data-rate-photo-previews]');
    previews.innerHTML = '';
    [...photoInput.files].forEach(file => {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.alt = file.name;
        previews.appendChild(img);
    });
});
feedbackForm.addEventListener('submit', event => {
    const missing = [...feedbackForm.querySelectorAll('[data-star-group] input')].some(input => !input.value);
    feedbackModal.querySelector('[data-rate-error]').hidden = !missing;
    if (missing) event.preventDefault();
});
feedbackModal.addEventListener('click', e => {
    if (e.target === feedbackModal) feedbackModal.classList.remove('open');
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
    if (feedback) return openFeedback(feedback);
    const star = event.target.closest('[data-set-rating]');
    if (star) return setRating(star);
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
