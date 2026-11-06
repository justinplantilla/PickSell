/**
 * Options editor for the seller Add/Edit product form: one option name (e.g. Size) and rows of
 * value + price (blank = the product's price) + stock. It posts variation_type and
 * variations[n][id|value|price|stock], plus
 * variations_managed so the server knows the list is complete (a no-JS submit never wipes it).
 * While any option exists the product's Stock field is read-only and shows their total.
 */
let rowSequence = 0;

class ProductVariations {
    constructor(root) {
        this.root = root;
        this.form = root.closest('form');
        this.rows = root.querySelector('[data-variation-rows]');
        this.typeWrap = root.querySelector('[data-variation-type-wrap]');
        this.type = root.querySelector('[data-variation-type]');
        this.stock = this.form.querySelector('[name=stock]');

        root.querySelector('[data-variations-managed]').disabled = false;
        root.querySelector('[data-add-variation]').addEventListener('click', () => {
            this.addRow().querySelector('[data-variation-value]').focus();
        });
        this.rows.addEventListener('input', () => this.refresh());
        this.rows.addEventListener('click', event => {
            const remove = event.target.closest('[data-remove-variation]');
            if (!remove) return;
            remove.closest('[data-variation-row]').remove();
            this.refresh();
        });
    }

    /** Replace the rows with a product's saved options (Edit modal). */
    load(variations = []) {
        this.rows.innerHTML = '';
        this.type.value = variations[0]?.type ?? '';
        variations.forEach(v => this.addRow(v));
        this.refresh();
    }

    addRow({ id = '', value = '', price = null, stock = 0 } = {}) {
        const n = rowSequence++;
        const row = document.createElement('div');
        row.className = 'product-variation-row';
        row.dataset.variationRow = '';
        row.innerHTML = `
            <input type="hidden" name="variations[${n}][id]">
            <input type="text" name="variations[${n}][value]" class="form-control" maxlength="50" required placeholder="e.g. Medium" aria-label="Option value" data-variation-value>
            <input type="number" name="variations[${n}][price]" class="form-control" min="0" step="0.01" placeholder="Same price" aria-label="Price for this option (blank: the product's price)" data-variation-price>
            <input type="number" name="variations[${n}][stock]" class="form-control" min="0" required aria-label="Stock for this option" data-variation-stock>
            <button type="button" class="btn btn-outline btn-sm" data-remove-variation aria-label="Remove option">Remove</button>`;
        row.querySelector('[type=hidden]').value = id;
        row.querySelector('[data-variation-value]').value = value;
        row.querySelector('[data-variation-price]').value = price ?? '';
        row.querySelector('[data-variation-stock]').value = stock;
        // Without an id the hidden field would post "" and fail integer validation.
        if (id === '') row.querySelector('[type=hidden]').disabled = true;
        this.rows.appendChild(row);
        this.refresh();
        return row;
    }

    refresh() {
        const stocks = [...this.rows.querySelectorAll('[data-variation-stock]')];
        const hasOptions = stocks.length > 0;
        this.typeWrap.hidden = !hasOptions;
        this.type.required = hasOptions;
        this.stock.readOnly = hasOptions;
        if (hasOptions) {
            this.stock.value = stocks.reduce((sum, input) => sum + (parseInt(input.value, 10) || 0), 0);
        }
    }
}

export function initProductVariations() {
    return [...document.querySelectorAll('[data-product-variations]')].map(root => {
        const editor = new ProductVariations(root);
        root.productVariations = editor;
        return editor;
    });
}
