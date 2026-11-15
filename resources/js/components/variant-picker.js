/**
 * Option chips for a product sold in up to three options (e.g. Color and Size), shared by the
 * product page and the quick-add modal. choices is Product::optionChoices():
 *   { names: ['Color', 'Size'], values: [['Red', 'Blue'], ['S', 'M']],
 *     combinations: [{ id, values: ['Red', 'S'], stock, price, image }, …] }
 *
 * One row of chips per option name. A chip is disabled when no combination with the values
 * already picked in the other rows has stock. onChange gets { combination, image, complete }:
 * the combination once every row is picked (else null), and the photo to show, which follows the
 * first option (the seller sets images per first-option value).
 */
let pickerSequence = 0;

export function createVariantPicker(container, choices, { onChange = () => {} } = {}) {
    const { names = [], values = [], combinations = [] } = choices;
    const selected = names.map(() => null);
    const uid = ++pickerSequence;
    container.replaceChildren();

    const rows = names.map((name, group) => {
        const row = document.createElement('div');
        row.className = 'variation-group';
        row.setAttribute('role', 'group');
        const label = document.createElement('div');
        label.className = 'variation-label';
        label.id = `variant-${uid}-${group}`;
        const chosen = document.createElement('span');
        chosen.textContent = '—';
        label.append(`${name.charAt(0).toUpperCase()}${name.slice(1)} `, chosen);
        row.setAttribute('aria-labelledby', label.id);
        const chips = document.createElement('div');
        chips.className = 'variation-options';
        const buttons = values[group].map(value => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'var-btn';
            chip.textContent = value;
            chip.dataset.value = value;
            chip.setAttribute('aria-pressed', 'false');
            chip.addEventListener('click', () => pick(group, value));
            chips.appendChild(chip);
            return chip;
        });
        row.append(label, chips);
        container.appendChild(row);
        return { chosen, buttons };
    });

    /** Combinations that agree with every picked row except `skip`. */
    const matching = skip => combinations.filter(combo => selected.every((value, g) => g === skip || value === null || combo.values[g] === value));

    function refresh() {
        rows.forEach(({ chosen, buttons }, group) => {
            const open = matching(group);
            buttons.forEach(chip => {
                const available = open.some(combo => combo.values[group] === chip.dataset.value && combo.stock > 0);
                const isPicked = selected[group] === chip.dataset.value;
                chip.disabled = !available && !isPicked;
                chip.title = chip.disabled ? 'Out of stock' : '';
                chip.classList.toggle('selected', isPicked);
                chip.setAttribute('aria-pressed', String(isPicked));
            });
            chosen.textContent = selected[group] ?? '—';
        });

        const complete = selected.every(value => value !== null);
        const combination = complete ? combinations.find(combo => combo.values.every((value, g) => value === selected[g])) ?? null : null;
        const image = combination?.image
            ?? (selected[0] !== null ? combinations.find(combo => combo.values[0] === selected[0] && combo.image)?.image : null)
            ?? null;
        onChange({ combination, image, complete });
    }

    function pick(group, value) {
        selected[group] = value;
        // A value picked earlier may not exist (or be out of stock) with the new pick: let it go.
        selected.forEach((other, g) => {
            if (g === group || other === null) return;
            const stillThere = combinations.some(combo => combo.values[g] === other && combo.values[group] === value
                && selected.every((v, k) => k === g || k === group || v === null || combo.values[k] === v) && combo.stock > 0);
            if (!stillThere) selected[g] = null;
        });
        refresh();
    }

    refresh();

    return {
        /** Pick every value of one combination (e.g. the cart line the buyer came from), if it has stock. */
        select(id) {
            const combo = combinations.find(c => String(c.id) === String(id));
            if (!combo || combo.stock <= 0) return false;
            combo.values.forEach((value, g) => { selected[g] = value; });
            refresh();
            return true;
        },
        focusFirst() {
            container.querySelector('.var-btn:not(:disabled)')?.focus();
        },
    };
}
