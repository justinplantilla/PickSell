/**
 * Options editor for the seller Add/Edit product form.
 *
 * The seller names up to three options (e.g. Color, Size) and lists each one's values. Every
 * combination of values (Red / S, Red / M, …) becomes a row with its own price (blank = the
 * product's price) and stock; "Set all" fills every row at once. Rows keep their price, stock and
 * id when values are added or removed elsewhere. Each value of the first option can show one of
 * the product's gallery images, which every combination with that value uses.
 *
 * It posts option_names[i] and variations[n][id|values][]|price|stock|image], plus
 * variations_managed so the server knows the list is complete (a no-JS submit never wipes it).
 * While any combination exists the product's Stock field is read-only and shows their total.
 * Images are gallery keys ("existing:{id}" / "upload:{token}"); the gallery's "gallery:change"
 * event keeps the pickers current, and a value whose image left the gallery goes back to none.
 */
const NO_IMAGE_ICON = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 5h16v14H4Zm2 2v8l3-3 3 3 2-2 4 4V7Zm9 1.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Z" fill="currentColor"/></svg>';

const comboKey = values => values.map(v => v.toLowerCase()).join('\u001f');

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

function hidden(name, value) {
    const input = el('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    return input;
}

class ProductVariations {
    constructor(root) {
        this.root = root;
        this.form = root.closest('form');
        this.maxNames = Number(root.dataset.maxNames) || 3;
        this.maxValues = Number(root.dataset.maxValues) || 10;
        this.maxCombinations = Number(root.dataset.maxCombinations) || 50;
        this.blocks = root.querySelector('[data-option-names]');
        this.addName = root.querySelector('[data-add-option-name]');
        this.combos = root.querySelector('[data-combos]');
        this.rowsEl = root.querySelector('[data-variation-rows]');
        this.count = root.querySelector('[data-combo-count]');
        this.message = root.querySelector('[data-variations-message]');
        this.stock = this.form.querySelector('[name=stock]');
        this.stockHint = this.form.querySelector('[data-variation-stock-hint]');
        const gallery = this.form.querySelector('[data-product-gallery]');
        this.images = gallery?.productGallery?.images ?? [];

        /** @type {{ name: string, values: { value: string, image: string }[] }[]} */
        this.options = [];
        /** combination key → { id, price, stock } */
        this.rows = new Map();

        root.querySelector('[data-variations-managed]').disabled = false;
        this.addName.addEventListener('click', () => {
            if (this.options.length >= this.maxNames) return;
            this.options.push({ name: '', values: [] });
            this.render({ focus: `name:${this.options.length - 1}` });
        });
        root.querySelector('[data-apply-all]').addEventListener('click', () => this.applyAll());
        gallery?.addEventListener('gallery:change', event => {
            this.images = event.detail.images;
            this.options[0]?.values.forEach(v => { if (!this.images.some(img => img.key === v.image)) v.image = ''; });
            this.renderBlocks();
        });
        document.addEventListener('click', event => {
            this.blocks.querySelectorAll('[data-variation-image-picker]:not([hidden])').forEach(picker => {
                if (!picker.parentElement.contains(event.target)) this.closePicker(picker);
            });
        });
        this.form.addEventListener('submit', event => this.onSubmit(event));
        this.render();
    }

    say(text) {
        this.message.textContent = text;
    }

    /** Replace everything with a product's saved combinations (Edit modal). */
    load(variations = []) {
        this.options = [];
        this.rows = new Map();
        variations.forEach(v => {
            const pairs = v.options?.length ? v.options : [[v.type, v.value]];
            pairs.forEach(([name, value], i) => {
                this.options[i] ??= { name, values: [] };
                let entry = this.options[i].values.find(x => x.value.toLowerCase() === String(value).toLowerCase());
                if (!entry) {
                    entry = { value: String(value), image: '' };
                    this.options[i].values.push(entry);
                }
                if (i === 0 && v.image_id && !entry.image) entry.image = `existing:${v.image_id}`;
            });
            this.rows.set(comboKey(pairs.map(p => String(p[1]))), { id: v.id, price: v.price ?? '', stock: v.stock ?? 0 });
        });
        this.say('');
        this.render();
    }

    /** Every combination of the options' values, in the seller's order. */
    combinations() {
        if (!this.options.length || this.options.some(o => !o.values.length)) return [];
        return this.options.reduce((combos, option) => combos.flatMap(combo => option.values.map(v => [...combo, v.value])), [[]]);
    }

    addValue(index, input) {
        const option = this.options[index];
        const value = input.value.trim();
        if (!value) return;
        if (option.values.some(v => v.value.toLowerCase() === value.toLowerCase())) {
            this.say(`${option.name || 'This option'} already has "${value}".`);
            return;
        }
        if (option.values.length >= this.maxValues) {
            this.say(`An option can have up to ${this.maxValues} values.`);
            return;
        }
        const total = this.options.reduce((n, o, i) => n * (i === index ? o.values.length + 1 : Math.max(o.values.length, 1)), 1);
        if (total > this.maxCombinations) {
            this.say(`That would make ${total} combinations; a product can have up to ${this.maxCombinations}.`);
            return;
        }
        // The very first value of a product without options starts with its current stock, so
        // adding options never empties it.
        if (!this.rows.size && this.options.length === 1 && !option.values.length) {
            this.rows.set(comboKey([value]), { id: '', price: '', stock: parseInt(this.stock.value, 10) || 0 });
        }
        option.values.push({ value, image: '' });
        input.value = '';
        this.say('');
        this.render({ focus: `value:${index}` });
    }

    render({ focus } = {}) {
        this.renderBlocks();
        this.renderRows();
        if (focus) {
            const [kind, index] = focus.split(':');
            this.blocks.querySelector(`[data-option-block="${index}"] ${kind === 'name' ? '[data-option-name]' : '[data-value-input]'}`)?.focus();
        }
    }

    renderBlocks() {
        this.blocks.replaceChildren();
        this.options.forEach((option, index) => {
            const block = el('div', 'option-name-block');
            block.dataset.optionBlock = String(index);

            const head = el('div', 'option-name-row');
            const name = el('input', 'form-control');
            name.type = 'text';
            name.name = `option_names[${index}]`;
            name.maxLength = 30;
            name.required = true;
            name.placeholder = index === 0 ? 'Option name, e.g. Color' : 'Option name, e.g. Size';
            name.value = option.name;
            name.dataset.optionName = '';
            name.setAttribute('aria-label', `Option ${index + 1} name`);
            name.addEventListener('input', () => { option.name = name.value.trim(); this.renderRowLabels(); });
            const remove = el('button', 'btn btn-outline btn-sm', 'Remove');
            remove.type = 'button';
            remove.setAttribute('aria-label', `Remove option ${option.name || index + 1}`);
            remove.addEventListener('click', () => {
                this.options.splice(index, 1);
                this.say('');
                this.render();
            });
            head.append(name, remove);

            const chips = el('div', 'option-values');
            option.values.forEach((entry, v) => {
                const chip = el('span', 'option-value-chip');
                if (index === 0) chip.appendChild(this.imageControl(entry));
                chip.appendChild(el('span', 'option-value-text', entry.value));
                const x = el('button', 'option-value-remove', '×');
                x.type = 'button';
                x.setAttribute('aria-label', `Remove ${entry.value}`);
                x.addEventListener('click', () => {
                    option.values.splice(v, 1);
                    this.say('');
                    this.render({ focus: `value:${index}` });
                });
                chip.appendChild(x);
                chips.appendChild(chip);
            });

            const add = el('div', 'option-value-add');
            const input = el('input', 'form-control');
            input.type = 'text';
            input.maxLength = 50;
            input.placeholder = index === 0 ? 'Add a value, e.g. Red' : 'Add a value, e.g. Medium';
            input.dataset.valueInput = '';
            input.setAttribute('aria-label', `Add a value to option ${index + 1}`);
            input.addEventListener('keydown', event => {
                if (event.key === 'Enter') { event.preventDefault(); this.addValue(index, input); }
            });
            const addButton = el('button', 'btn btn-outline btn-sm', 'Add');
            addButton.type = 'button';
            addButton.addEventListener('click', () => this.addValue(index, input));
            add.append(input, addButton);

            block.append(head, chips, add);
            if (index === 0 && option.values.length) block.appendChild(el('small', 'option-image-hint', 'Tap a value’s image box to pick which product image buyers see for it.'));
            this.blocks.appendChild(block);
        });
        this.addName.hidden = this.options.length >= this.maxNames;
        this.addName.textContent = this.options.length ? '+ Add another option' : '+ Add option (e.g. Color or Size)';
    }

    /** The image box on a first-option value: opens a picker of the product's gallery images. */
    imageControl(entry) {
        const wrap = el('span', 'variation-image');
        const toggle = el('button', 'variation-image-toggle');
        toggle.type = 'button';
        toggle.setAttribute('aria-haspopup', 'true');
        toggle.setAttribute('aria-expanded', 'false');
        const image = this.images.find(img => img.key === entry.image);
        toggle.classList.toggle('has-image', Boolean(image));
        if (image) {
            const img = el('img');
            img.src = image.url;
            img.alt = '';
            toggle.appendChild(img);
        } else {
            toggle.innerHTML = NO_IMAGE_ICON;
        }
        toggle.setAttribute('aria-label', image ? `Change image for ${entry.value} (now: ${image.alt})` : `Choose an image for ${entry.value}`);
        toggle.title = image ? 'Change image' : 'Choose image';

        const picker = el('div', 'variation-image-picker');
        picker.hidden = true;
        picker.dataset.variationImagePicker = '';
        picker.setAttribute('role', 'group');
        picker.setAttribute('aria-label', `Image for ${entry.value}`);
        toggle.addEventListener('click', () => {
            if (!picker.hidden) return this.closePicker(picker, true);
            this.blocks.querySelectorAll('[data-variation-image-picker]:not([hidden])').forEach(p => this.closePicker(p));
            picker.replaceChildren();
            if (!this.images.length) picker.appendChild(el('p', 'variation-image-empty', 'Add product images below first, then pick one for this value.'));
            const choose = key => { entry.image = key; this.closePicker(picker); this.renderBlocks(); };
            this.images.forEach((img, i) => {
                const choice = el('button', 'variation-image-choice');
                choice.type = 'button';
                choice.setAttribute('aria-pressed', String(img.key === entry.image));
                choice.setAttribute('aria-label', `Image ${i + 1} (${img.alt})`);
                const thumb = el('img');
                thumb.src = img.url;
                thumb.alt = '';
                choice.appendChild(thumb);
                choice.addEventListener('click', () => choose(img.key));
                picker.appendChild(choice);
            });
            const none = el('button', 'variation-image-choice variation-image-none', 'No image');
            none.type = 'button';
            none.setAttribute('aria-pressed', String(!entry.image));
            none.addEventListener('click', () => choose(''));
            picker.appendChild(none);
            picker.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            (picker.querySelector('[aria-pressed="true"]') ?? picker.querySelector('button'))?.focus();
        });
        picker.addEventListener('keydown', event => {
            if (event.key === 'Escape') { event.preventDefault(); this.closePicker(picker, true); }
        });
        wrap.append(toggle, picker);
        return wrap;
    }

    closePicker(picker, returnFocus = false) {
        if (picker.hidden) return;
        picker.hidden = true;
        const toggle = picker.parentElement.querySelector('.variation-image-toggle');
        toggle?.setAttribute('aria-expanded', 'false');
        if (returnFocus) toggle?.focus();
    }

    renderRows() {
        const combos = this.combinations();
        this.rowsEl.replaceChildren();
        combos.forEach(values => {
            const key = comboKey(values);
            if (!this.rows.has(key)) this.rows.set(key, { id: '', price: '', stock: 0 });
            const data = this.rows.get(key);
            const row = el('div', 'product-variation-row');
            row.dataset.variationRow = key;
            const label = el('span', 'variation-combo-label');
            label.dataset.values = JSON.stringify(values);
            const price = el('input', 'form-control');
            price.type = 'number';
            price.min = '0';
            price.step = '0.01';
            price.placeholder = 'Same price';
            price.value = data.price ?? '';
            price.addEventListener('input', () => { data.price = price.value; });
            const stock = el('input', 'form-control');
            stock.type = 'number';
            stock.min = '0';
            stock.required = true;
            stock.value = data.stock;
            stock.dataset.variationStock = '';
            stock.addEventListener('input', () => { data.stock = stock.value; this.refreshStock(); });
            row.append(label, price, stock);
            this.rowsEl.appendChild(row);
        });
        this.combos.hidden = !combos.length;
        this.count.textContent = combos.length ? `${combos.length} of ${this.maxCombinations}` : '';
        this.renderRowLabels();
        this.refreshStock();
    }

    /** Row labels and input names follow the option names ("Color: Red · Size: M"). */
    renderRowLabels() {
        this.rowsEl.querySelectorAll('[data-variation-row]').forEach(row => {
            const label = row.querySelector('.variation-combo-label');
            const values = JSON.parse(label.dataset.values);
            label.textContent = values.join(' / ');
            const named = values.map((v, i) => `${this.options[i]?.name || `Option ${i + 1}`} ${v}`).join(', ');
            const [price, stock] = row.querySelectorAll('input');
            price.setAttribute('aria-label', `Price for ${named} (blank: the product's price)`);
            stock.setAttribute('aria-label', `Stock for ${named}`);
        });
    }

    refreshStock() {
        const stocks = [...this.rowsEl.querySelectorAll('[data-variation-stock]')];
        const hasOptions = stocks.length > 0;
        this.stock.readOnly = hasOptions;
        if (this.stockHint) this.stockHint.hidden = !hasOptions;
        if (hasOptions) this.stock.value = stocks.reduce((sum, input) => sum + (parseInt(input.value, 10) || 0), 0);
    }

    applyAll() {
        const price = this.root.querySelector('[data-apply-price]').value;
        const stock = this.root.querySelector('[data-apply-stock]').value;
        if (price === '' && stock === '') return this.say('Enter a price or a stock to set on every combination.');
        this.combinations().forEach(values => {
            const data = this.rows.get(comboKey(values));
            if (price !== '') data.price = price;
            if (stock !== '') data.stock = stock;
        });
        this.say('');
        this.renderRows();
    }

    onSubmit(event) {
        this.root.querySelectorAll('[data-variation-post]').forEach(node => node.remove());
        if (this.options.length && !this.combinations().length) {
            event.preventDefault();
            event.stopPropagation();
            this.say('Add at least one value to every option, or remove the options you do not need.');
            this.blocks.querySelector('[data-value-input]')?.focus();
            return;
        }
        const fields = el('div');
        fields.hidden = true;
        fields.dataset.variationPost = '';
        this.combinations().forEach((values, n) => {
            const data = this.rows.get(comboKey(values));
            if (data.id) fields.appendChild(hidden(`variations[${n}][id]`, data.id));
            values.forEach((v, i) => fields.appendChild(hidden(`variations[${n}][values][${i}]`, v)));
            fields.appendChild(hidden(`variations[${n}][price]`, data.price ?? ''));
            fields.appendChild(hidden(`variations[${n}][stock]`, data.stock || 0));
            const image = this.options[0].values.find(v => v.value === values[0])?.image;
            if (image && this.images.some(img => img.key === image)) fields.appendChild(hidden(`variations[${n}][image]`, image));
        });
        this.root.appendChild(fields);
    }
}

export function initProductVariations() {
    return [...document.querySelectorAll('[data-product-variations]')].map(root => {
        const editor = new ProductVariations(root);
        root.productVariations = editor;
        return editor;
    });
}
