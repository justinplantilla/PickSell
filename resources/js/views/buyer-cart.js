const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position:fixed;left:1rem;bottom:1rem;z-index:99999;display:flex;flex-direction:column;gap:0.6rem;align-items:flex-start;pointer-events:none;';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.style.cssText = `padding:0.75rem 1.1rem;border-radius:10px;font-size:0.82rem;font-weight:600;color:#fff;background:${type === 'success' ? '#16a34a' : '#dc2626'};box-shadow:0 10px 24px rgba(0,0,0,0.18);animation:toast-in 0.25s ease;border:1px solid rgba(255,255,255,0.12);max-width:min(320px, calc(100vw - 2rem));pointer-events:auto;`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 3000);
}

function showConfirm(message, onConfirm) {
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:9998;display:flex;align-items:center;justify-content:center;';
    overlay.innerHTML = `<div style="background:#fff;border-radius:12px;padding:1.5rem;max-width:360px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,0.18);">
        <p style="font-size:0.9rem;margin-bottom:1.2rem;color:#333;">${message}</p>
        <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
            <button id="confirmCancel" class="btn btn-outline" style="padding:0.45rem 1rem;font-size:0.82rem;">Cancel</button>
            <button id="confirmOk" class="btn btn-danger" style="padding:0.45rem 1rem;font-size:0.82rem;">Remove</button>
        </div>
    </div>`;
    document.body.appendChild(overlay);
    overlay.querySelector('#confirmCancel').addEventListener('click', () => overlay.remove());
    overlay.querySelector('#confirmOk').addEventListener('click', () => { overlay.remove(); onConfirm(); });
}

const style = document.createElement('style');
style.textContent = '@keyframes toast-in{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}';
document.head.appendChild(style);

function updateSummary() {
    const checked = document.querySelectorAll('.item-check:checked');
    let total = 0;
    checked.forEach(item => {
        const row = item.closest('tr');
        total += parseFloat(row.querySelector('[data-subtotal]').dataset.subtotal);
    });
    document.getElementById('selectedCount').textContent = checked.length;
    document.getElementById('totalAmount').textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    document.getElementById('placeOrderBtn').disabled = checked.length === 0;
    const selectAll = document.querySelector('[data-cart-select-all]');
    if (selectAll) selectAll.checked = checked.length > 0 && checked.length === document.querySelectorAll('.item-check').length;

    // Spinning border on summary card
    document.getElementById('cartSummaryCard')?.classList.toggle('has-selection', checked.length > 0);

    // Sync hidden inputs for checkout form
    const container = document.getElementById('selectedItemsInputs');
    if (container) {
        container.innerHTML = '';
        checked.forEach(item => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'item_ids[]';
            input.value = item.value;
            container.appendChild(input);
        });
    }
}

// Validate before proceeding to checkout
document.getElementById('checkoutForm')?.addEventListener('submit', function (e) {
    const checked = document.querySelectorAll('.item-check:checked');
    if (checked.length === 0) {
        e.preventDefault();
        showToast('Please select at least one item before proceeding to checkout.', 'error');
    }
});

document.querySelector('[data-cart-select-all]')?.addEventListener('change', event => {
    document.querySelectorAll('.item-check').forEach(item => { item.checked = event.target.checked; });
    updateSummary();
});
document.querySelectorAll('.item-check').forEach(item => item.addEventListener('change', updateSummary));
updateSummary();

function updateQty(itemId, quantity) {
    fetch(`/buyer/cart/item/${itemId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
        body: JSON.stringify({ quantity })
    }).then(response => {
        if (!response.ok) throw new Error();
        window.location.reload();
    }).catch(() => showToast('Unable to update quantity. Please try again.', 'error'));
}

function syncStepperState(input) {
    const val = parseInt(input.value) || 1;
    const max = parseInt(input.max) || 9999;
    const itemId = input.dataset.itemId;
    const dec = document.querySelector(`.qty-dec[data-item-id="${itemId}"]`);
    const inc = document.querySelector(`.qty-inc[data-item-id="${itemId}"]`);
    if (dec) dec.disabled = val <= 1;
    if (inc) inc.disabled = val >= max;
}

document.querySelectorAll('.qty-input').forEach(input => syncStepperState(input));

document.querySelectorAll('.qty-btn').forEach(btn => btn.addEventListener('click', function () {
    const itemId = this.dataset.itemId;
    const input = document.querySelector(`.qty-input[data-item-id="${itemId}"]`);
    if (!input) return;
    const max = parseInt(input.max) || 9999;
    let val = parseInt(input.value) || 1;
    if (this.classList.contains('qty-inc')) val = Math.min(val + 1, max);
    else val = Math.max(val - 1, 1);
    input.value = val;
    syncStepperState(input);
    updateQty(itemId, val);
}));

document.querySelectorAll('.qty-input').forEach(input => input.addEventListener('change', function () {
    const itemId = this.dataset.itemId;
    const max = parseInt(this.max) || 9999;
    let val = Math.max(1, Math.min(parseInt(this.value) || 1, max));
    this.value = val;
    syncStepperState(this);
    updateQty(itemId, val);
}));

document.querySelectorAll('.remove-item-btn').forEach(button => button.addEventListener('click', function () {
    const itemId = this.dataset.itemId;
    if (!itemId) return;
    fetch(`/buyer/cart/item/${itemId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }
    }).then(response => {
        if (!response.ok) throw new Error();
        window.location.reload();
    }).catch(() => showToast('Unable to remove this item. Please try again.', 'error'));
}));
