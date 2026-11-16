import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

// Exercise the actual event handlers without a browser or network mutations.
function cart() {
    const element = () => ({
        checked: false, indeterminate: false, children: [], listeners: {},
        addEventListener(name, fn) { this.listeners[name] = fn; },
        appendChild(child) { this.children.push(child); },
        set innerHTML(_) { this.children = []; },
        change(checked) { this.checked = checked; this.listeners.change?.({ target: this }); },
    });
    const stores = [[100, 200], [300], [400, 500]].map((prices, index) => {
        const store = element();
        store.box = element();
        store.box.closest = () => store;
        store.items = prices.map((price, i) => {
            const item = element();
            item.value = `${index}-${i}`;
            item.closest = () => ({ querySelector: () => ({ dataset: { subtotal: `${price}` } }) });
            return item;
        });
        store.querySelector = () => store.box;
        store.querySelectorAll = () => store.items;
        return store;
    });
    const items = stores.flatMap(s => s.items);
    const all = element();
    const ids = Object.fromEntries(['selectedCount', 'totalAmount', 'placeOrderBtn', 'selectedItemsInputs', 'checkoutForm'].map(id => [id, element()]));
    const document = {
        getElementById: id => ids[id],
        createElement: element,
        querySelector: selector => selector === '[data-cart-select-all]' ? all : null,
        querySelectorAll: selector => ({
            '.item-check': items,
            '.item-check:checked': items.filter(item => item.checked),
            '[data-cart-store]': stores,
            '[data-cart-select-store]': stores.map(store => store.box),
        })[selector] ?? [],
    };
    vm.runInNewContext(readFileSync(new URL('../../resources/js/views/buyer-cart.js', import.meta.url), 'utf8'), { document, window: {} });
    return { stores, items, all, ids };
}

test('Select All selects every store and submits only selected IDs', () => {
    const c = cart();
    c.all.change(true);
    assert.ok(c.items.every(i => i.checked));
    assert.ok(c.stores.every(s => s.box.checked));
    assert.equal(c.ids.selectedCount.textContent, 5);
    assert.equal(c.ids.selectedItemsInputs.children.length, 5);
    assert.equal(c.ids.placeOrderBtn.disabled, false);
    c.all.change(false);
    assert.ok(c.items.every(i => !i.checked));
    assert.equal(c.ids.selectedItemsInputs.children.length, 0);
    assert.equal(c.ids.placeOrderBtn.disabled, true);
});

test('store selection is isolated and partial item selection sets indeterminate states', () => {
    const c = cart();
    c.stores[0].box.change(true);
    assert.deepEqual(c.items.map(i => i.checked), [true, true, false, false, false]);
    assert.equal(c.all.indeterminate, true);
    c.items[0].change(false);
    assert.equal(c.stores[0].box.checked, false);
    assert.equal(c.stores[0].box.indeterminate, true);
    assert.deepEqual(c.ids.selectedItemsInputs.children.map(i => i.value), ['0-1']);
    c.stores[0].box.change(false);
    assert.equal(c.stores[0].box.indeterminate, false);
    assert.equal(c.all.indeterminate, false);
});
