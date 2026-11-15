/**
 * Options editor for the seller Add/Edit product form: one option name (e.g. Size) and rows of
 * image + value + price (blank = the product's price) + stock. It posts variation_type and
 * variations[n][id|image|value|price|stock], plus
 * variations_managed so the server knows the list is complete (a no-JS submit never wipes it).
 * While any option exists the product's Stock field is read-only and shows their total.
 *
 * An option's image is one of the product's gallery images, posted as its gallery key
 * ("existing:{id}" / "upload:{token}"); the gallery's "gallery:change" event keeps the pickers
 * current, and an option whose image left the gallery goes back to no image.
 */
let rowSequence = 0;

const NO_IMAGE_ICON = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 5h16v14H4Zm2 2v8l3-3 3 3 2-2 4 4V7Zm9 1.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Z" fill="currentColor"/></svg>';

class ProductVariations {
    constructor(root) {
        this.root = root;
        this.form = root.closest('form');
        this.rows = root.querySelector('[data-variation-rows]');
        this.typeWrap = root.querySelector('[data-variation-type-wrap]');
        this.type = root.querySelector('[data-variation-type]');
        this.stock = this.form.querySelector('[name=stock]');
        this.stockHint = this.form.querySelector('[data-variation-stock-hint]');
        const gallery = this.form.querySelector('[data-product-gallery]');
        this.images = gallery?.productGallery?.images ?? [];

        root.querySelector('[data-variations-managed]').disabled = false;
        root.querySelector('[data-add-variation]').addEventListener('click', () => {
            // The first option starts with the product's current stock, so adding options never empties it.
            const first = !this.rows.querySelector('[data-variation-row]');
            this.addRow({ stock: first ? (parseInt(this.stock.value, 10) || 0) : 0 }).querySelector('[data-variation-value]').focus();
        });
        this.rows.addEventListener('input', () => this.refresh());
        this.rows.addEventListener('click', event => {
            const remove = event.target.closest('[data-remove-variation]');
            if (remove) {
                remove.closest('[data-variation-row]').remove();
                this.refresh();
                return;
            }
            const toggle = event.target.closest('[data-variation-image-toggle]');
            if (toggle) return this.togglePicker(toggle.closest('[data-variation-row]'));
            const choice = event.target.closest('[data-image-choice]');
            if (choice) {
                const row = choice.closest('[data-variation-row]');
                this.setImage(row, choice.dataset.imageChoice);
                this.closePicker(row, true);
            }
        });
        gallery?.addEventListener('gallery:change', event => {
            this.images = event.detail.images;
            this.rows.querySelectorAll('[data-variation-row]').forEach(row => this.setImage(row, row.querySelector('[data-variation-image]').value));
        });
        document.addEventListener('click', event => {
            this.rows.querySelectorAll('[data-variation-image-picker]:not([hidden])').forEach(picker => {
                if (!picker.closest('[data-variation-row]').contains(event.target)) this.closePicker(picker.closest('[data-variation-row]'));
            });
        });
        this.rows.addEventListener('keydown', event => {
            if (event.key !== 'Escape') return;
            const row = event.target.closest('[data-variation-row]');
            if (row && !row.querySelector('[data-variation-image-picker]').hidden) {
                event.preventDefault();
                this.closePicker(row, true);
            }
        });
    }

    /** Replace the rows with a product's saved options (Edit modal). */
    load(variations = []) {
        this.rows.innerHTML = '';
        this.type.value = variations[0]?.type ?? '';
        variations.forEach(v => this.addRow({ ...v, image: v.image_id ? `existing:${v.image_id}` : '' }));
        this.refresh();
    }

    addRow({ id = '', value = '', price = null, stock = 0, image = '' } = {}) {
        const n = rowSequence++;
        const row = document.createElement('div');
        row.className = 'product-variation-row';
        row.dataset.variationRow = '';
        row.innerHTML = `
            <input type="hidden" name="variations[${n}][id]">
            <input type="hidden" name="variations[${n}][image]" data-variation-image>
            <div class="variation-image">
                <button type="button" class="variation-image-toggle" aria-haspopup="true" aria-expanded="false" data-variation-image-toggle></button>
                <div class="variation-image-picker" role="group" aria-label="Image for this option" hidden data-variation-image-picker></div>
            </div>
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
        this.setImage(row, image);
        this.rows.appendChild(row);
        this.refresh();
        return row;
    }

    /** Point the option at a gallery image (or none, also when that image is no longer in the gallery). */
    setImage(row, key) {
        const image = this.images.find(img => img.key === key);
        const input = row.querySelector('[data-variation-image]');
        input.value = image ? key : '';
        input.disabled = !image;
        const toggle = row.querySelector('[data-variation-image-toggle]');
        const value = row.querySelector('[data-variation-value]')?.value.trim();
        const name = value ? `option ${value}` : 'this option';
        toggle.classList.toggle('has-image', Boolean(image));
        toggle.innerHTML = image ? '<img alt="">' : NO_IMAGE_ICON;
        if (image) toggle.querySelector('img').src = image.url;
        toggle.setAttribute('aria-label', image ? `Change image for ${name} (now: ${image.alt})` : `Choose an image for ${name}`);
        toggle.title = image ? 'Change image' : 'Choose image';
    }

    togglePicker(row) {
        const picker = row.querySelector('[data-variation-image-picker]');
        if (!picker.hidden) return this.closePicker(row, true);
        this.rows.querySelectorAll('[data-variation-row]').forEach(other => other !== row && this.closePicker(other));

        const current = row.querySelector('[data-variation-image]').value;
        picker.replaceChildren();
        if (!this.images.length) {
            const hint = document.createElement('p');
            hint.className = 'variation-image-empty';
            hint.textContent = 'Add product images below first, then pick one for this option.';
            picker.appendChild(hint);
        }
        this.images.forEach((image, index) => {
            const choice = document.createElement('button');
            choice.type = 'button';
            choice.className = 'variation-image-choice';
            choice.dataset.imageChoice = image.key;
            choice.setAttribute('aria-pressed', String(image.key === current));
            choice.setAttribute('aria-label', `Image ${index + 1} (${image.alt})`);
            const img = document.createElement('img');
            img.src = image.url;
            img.alt = '';
            choice.appendChild(img);
            picker.appendChild(choice);
        });
        const none = document.createElement('button');
        none.type = 'button';
        none.className = 'variation-image-choice variation-image-none';
        none.dataset.imageChoice = '';
        none.setAttribute('aria-pressed', String(!current));
        none.textContent = 'No image';
        picker.appendChild(none);

        picker.hidden = false;
        row.querySelector('[data-variation-image-toggle]').setAttribute('aria-expanded', 'true');
        (picker.querySelector('[aria-pressed="true"]') ?? picker.querySelector('button'))?.focus();
    }

    closePicker(row, returnFocus = false) {
        const picker = row.querySelector('[data-variation-image-picker]');
        if (picker.hidden) return;
        picker.hidden = true;
        const toggle = row.querySelector('[data-variation-image-toggle]');
        toggle.setAttribute('aria-expanded', 'false');
        if (returnFocus) toggle.focus();
    }

    refresh() {
        const stocks = [...this.rows.querySelectorAll('[data-variation-stock]')];
        const hasOptions = stocks.length > 0;
        this.typeWrap.hidden = !hasOptions;
        this.type.required = hasOptions;
        this.stock.readOnly = hasOptions;
        if (this.stockHint) this.stockHint.hidden = !hasOptions;
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
