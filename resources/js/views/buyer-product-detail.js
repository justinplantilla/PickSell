function selectVariation(btn, typeSlug) {
    document.querySelectorAll(`.var-btn[data-type="${btn.dataset.type}"]`).forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    document.getElementById('selected_' + typeSlug).textContent = btn.dataset.value;
    // Use last selected variation id (simplified — single variation support)
    document.getElementById('variation_id').value = btn.dataset.id;
}
function changeQty(delta) {
    const input = document.getElementById('qtyInput');
    const max   = parseInt(input.max);
    let val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > max) val = max;
    input.value = val;
}
window.selectVariation = selectVariation;
window.changeQty = changeQty;
