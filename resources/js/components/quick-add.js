/**
 * Quick-add modal for product cards (home, browse, categories, storefront, wishlist, cart
 * recommendations). A card has no option picker, so a product sold in options opens this modal:
 * photo (follows the picked option's image), price, one row of chips per option name, quantity,
 * and one button that adds to the cart or, from Buy Now, goes on to checkout. Products without
 * options skip it.
 *
 * openQuickAdd(product) takes BuyerController::productOptions() and resolves with
 * { variationId, quantity } once the buyer confirms, or null when they close the modal.
 */
import { createVariantPicker } from './variant-picker.js';

const peso = value => `₱${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

let dialog;

function build() {
    dialog = document.createElement('dialog');
    dialog.className = 'quick-add';
    dialog.setAttribute('aria-labelledby', 'quickAddTitle');
    dialog.innerHTML = `
        <form method="dialog" class="quick-add-body" data-no-confirm>
            <button type="button" class="quick-add-close" aria-label="Close" data-quick-add-close>×</button>
            <div class="quick-add-media"><img alt="" data-quick-add-image></div>
            <div class="quick-add-info">
                <h2 id="quickAddTitle" class="quick-add-name" data-quick-add-name></h2>
                <div class="quick-add-price" data-quick-add-price></div>
                <div data-quick-add-groups></div>
                <div class="quick-add-qty">
                    <span class="quick-add-label" id="quickAddQtyLabel">Quantity</span>
                    <div class="qty-control">
                        <button type="button" class="qty-btn" data-quick-add-step="-1" aria-label="Decrease quantity">-</button>
                        <input type="number" class="qty-input" value="1" min="1" aria-labelledby="quickAddQtyLabel" data-quick-add-qty>
                        <button type="button" class="qty-btn" data-quick-add-step="1" aria-label="Increase quantity">+</button>
                    </div>
                    <small data-quick-add-stock></small>
                </div>
                <p class="quick-add-error" role="alert" data-quick-add-error></p>
                <button type="submit" class="btn btn-coral quick-add-submit" data-quick-add-submit></button>
            </div>
        </form>`;
    document.body.appendChild(dialog);
}

export function openQuickAdd(product, { buyNow = false } = {}) {
    if (!dialog) build();
    const image = dialog.querySelector('[data-quick-add-image]');
    const price = dialog.querySelector('[data-quick-add-price]');
    const qty = dialog.querySelector('[data-quick-add-qty]');
    const stock = dialog.querySelector('[data-quick-add-stock]');
    const error = dialog.querySelector('[data-quick-add-error]');
    const submit = dialog.querySelector('[data-quick-add-submit]');
    const { combinations } = product.choices;

    image.src = product.image || '';
    image.hidden = !product.image;
    image.alt = product.name;
    dialog.querySelector('[data-quick-add-name]').textContent = product.name;
    const prices = combinations.map(combo => combo.price);
    const low = Math.min(...prices);
    const high = Math.max(...prices);
    const range = high > low ? `${peso(low)} – ${peso(high)}` : peso(low);
    submit.textContent = buyNow ? 'Buy Now' : 'Add to Cart';
    error.textContent = '';
    qty.value = 1;

    let chosen = null;
    const picker = createVariantPicker(dialog.querySelector('[data-quick-add-groups]'), product.choices, {
        onChange({ combination, image: photo }) {
            chosen = combination;
            price.textContent = combination ? peso(combination.price) : range;
            qty.max = combination ? combination.stock : product.stock;
            if (Number(qty.value) > Number(qty.max)) qty.value = qty.max;
            stock.textContent = combination ? `${combination.stock} available` : 'Choose your options';
            if (photo) { image.src = photo; image.hidden = false; }
            if (combination) error.textContent = '';
        },
    });

    return new Promise(resolve => {
        const step = event => {
            const button = event.target.closest('[data-quick-add-step]');
            if (!button) return;
            const max = Number(qty.max) || 1;
            qty.value = Math.min(max, Math.max(1, (Number(qty.value) || 1) + Number(button.dataset.quickAddStep)));
        };
        const close = event => {
            if (event.target.closest('[data-quick-add-close]') || event.target === dialog) finish(null);
        };
        const onSubmit = event => {
            event.preventDefault();
            if (!chosen) {
                error.textContent = 'Choose an option for each row first.';
                return;
            }
            const quantity = Math.min(Number(qty.max) || 1, Math.max(1, Number(qty.value) || 1));
            finish({ variationId: chosen.id, quantity });
        };
        const onCancel = event => { event.preventDefault(); finish(null); };
        const finish = result => {
            dialog.removeEventListener('click', step);
            dialog.removeEventListener('click', close);
            dialog.removeEventListener('submit', onSubmit);
            dialog.removeEventListener('cancel', onCancel);
            dialog.close();
            resolve(result);
        };
        dialog.addEventListener('click', step);
        dialog.addEventListener('click', close);
        dialog.addEventListener('submit', onSubmit);
        dialog.addEventListener('cancel', onCancel);
        dialog.showModal();
        picker.focusFirst();
    });
}
