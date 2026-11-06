const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

const showToast = (...args) => window.showToast(...args);

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
    document.querySelectorAll('[data-cart-store]').forEach(store => {
        const items = [...store.querySelectorAll('.item-check')];
        const picked = items.filter(item => item.checked).length;
        const box = store.querySelector('[data-cart-select-store]');
        box.checked = picked > 0 && picked === items.length;
        box.indeterminate = picked > 0 && picked < items.length;
    });

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
document.querySelectorAll('[data-cart-select-store]').forEach(box => box.addEventListener('change', () => {
    box.closest('[data-cart-store]').querySelectorAll('.item-check').forEach(item => { item.checked = box.checked; });
    updateSummary();
}));
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
