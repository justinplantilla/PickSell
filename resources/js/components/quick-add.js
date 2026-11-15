/**
 * Quick-add modal for product cards (home, browse, categories, storefront, wishlist, cart
 * recommendations). A card has no option picker, so a product sold in options opens this modal:
 * photo (follows the picked option's image), price, option chips, quantity, and one button that
 * adds to the cart or, from Buy Now, goes on to checkout. Products without options skip it.
 *
 * openQuickAdd() resolves with { variationId, quantity } once the buyer confirms, or null when
 * they close the modal.
 */
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
    const groups = dialog.querySelector('[data-quick-add-groups]');
    const qty = dialog.querySelector('[data-quick-add-qty]');
    const stock = dialog.querySelector('[data-quick-add-stock]');
    const error = dialog.querySelector('[data-quick-add-error]');
    const submit = dialog.querySelector('[data-quick-add-submit]');

    let chosen = null;
    image.src = product.image || '';
    image.hidden = !product.image;
    image.alt = product.name;
    dialog.querySelector('[data-quick-add-name]').textContent = product.name;
    const prices = product.options.map(option => option.price);
    const low = Math.min(...prices);
    const high = Math.max(...prices);
    price.textContent = high > low ? `${peso(low)} – ${peso(high)}` : peso(low);
    submit.textContent = buyNow ? 'Buy Now' : 'Add to Cart';
    error.textContent = '';
    qty.value = 1;
    qty.max = product.stock;
    stock.textContent = 'Choose an option';

    groups.replaceChildren();
    const byType = new Map();
    product.options.forEach(option => {
        if (!byType.has(option.type)) byType.set(option.type, []);
        byType.get(option.type).push(option);
    });
    byType.forEach((options, type) => {
        const group = document.createElement('div');
        group.className = 'quick-add-group';
        group.setAttribute('role', 'group');
        const label = document.createElement('div');
        label.className = 'quick-add-label';
        label.textContent = type.charAt(0).toUpperCase() + type.slice(1);
        group.setAttribute('aria-label', label.textContent);
        const chips = document.createElement('div');
        chips.className = 'quick-add-options';
        options.forEach(option => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'var-btn';
            chip.textContent = option.value;
            chip.disabled = option.stock <= 0;
            if (chip.disabled) chip.title = 'Out of stock';
            chip.setAttribute('aria-pressed', 'false');
            chip.addEventListener('click', () => {
                chosen = option;
                chips.querySelectorAll('.var-btn').forEach(b => {
                    b.classList.toggle('selected', b === chip);
                    b.setAttribute('aria-pressed', String(b === chip));
                });
                price.textContent = peso(option.price);
                qty.max = option.stock;
                if (Number(qty.value) > option.stock) qty.value = option.stock;
                stock.textContent = `${option.stock} available`;
                if (option.image) { image.src = option.image; image.hidden = false; }
                error.textContent = '';
            });
            chips.appendChild(chip);
        });
        group.append(label, chips);
        groups.appendChild(group);
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
                error.textContent = 'Choose an option first.';
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
        (groups.querySelector('.var-btn:not(:disabled)') ?? submit).focus();
    });
}
