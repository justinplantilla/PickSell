const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

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
    if (selectAll) selectAll.checked = checked.length === document.querySelectorAll('.item-check').length;
}

document.querySelector('[data-cart-select-all]')?.addEventListener('change', event => {
    document.querySelectorAll('.item-check').forEach(item => { item.checked = event.target.checked; });
    updateSummary();
});
document.querySelectorAll('.item-check').forEach(item => item.addEventListener('change', updateSummary));

document.querySelectorAll('.qty-input').forEach(input => input.addEventListener('change', function () {
    const itemId = this.dataset.itemId;
    const quantity = Number(this.value || 1);
    if (!itemId || quantity < 1) return;
    fetch(`/buyer/cart/item/${itemId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
        body: JSON.stringify({ quantity })
    }).then(response => {
        if (!response.ok) throw new Error('Unable to update quantity');
        window.location.reload();
    }).catch(() => alert('Unable to update quantity. Please try again.'));
}));

document.querySelectorAll('.remove-item-btn').forEach(button => button.addEventListener('click', function () {
    const itemId = this.dataset.itemId;
    if (!itemId || !confirm('Remove this item from your cart?')) return;
    fetch(`/buyer/cart/item/${itemId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }
    }).then(response => {
        if (!response.ok) throw new Error('Unable to remove item');
        window.location.reload();
    }).catch(() => alert('Unable to remove this item. Please try again.'));
}));
