import { initProductGalleries } from '../components/product-gallery.js';
import { initProductVariations } from '../components/product-variations.js';

initProductGalleries();
initProductVariations();

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});
function openEditModal(id, p) {
    const f = document.getElementById('editForm');
    f.action = '/seller/inventory/' + id;
    f.querySelector('[name=name]').value = p.name;
    f.querySelector('[name=description]').value = p.description || '';
    f.querySelector('[name=category]').value = p.category || '';
    f.querySelector('[name=price]').value = p.price;
    f.querySelector('[name=discount]').value = p.regular_discount || 0;
    // datetime-local wants "YYYY-MM-DDTHH:MM" in local time.
    const local = value => value ? new Date(new Date(value).getTime() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16) : '';
    f.querySelector('[name=sale_discount]').value = p.sale_discount ?? '';
    f.querySelector('[name=sale_starts_at]').value = local(p.sale_starts_at);
    f.querySelector('[name=sale_ends_at]').value = local(p.sale_ends_at);
    f.querySelector('[data-product-sale]').open = Boolean(p.sale_discount);
    f.querySelector('[name=voucher_code]').value = p.voucher_code || '';
    f.querySelector('[name=voucher_discount]').value = p.voucher_discount || 0;
    f.querySelector('[name=stock]').value = p.stock;
    f.querySelector('[name=weight_kg]').value = p.weight_kg ?? '';
    f.querySelector('[data-product-variations]').productVariations?.load(p.variations || []);
    f.querySelector('[data-product-gallery]').productGallery?.load(p.images || [], p.name);
    openModal('editModal');
}

document.addEventListener('click', event => {
    const open = event.target.closest('[data-open-modal]');
    if (open) return openModal(open.dataset.openModal);
    const close = event.target.closest('[data-close-modal]');
    if (close) return closeModal(close.dataset.closeModal);
    const edit = event.target.closest('[data-edit-product]');
    if (edit) {
        const product = JSON.parse(edit.dataset.editProduct);
        openEditModal(product.id, product);
    }
});

window.openModal = openModal;
window.closeModal = closeModal;
window.openEditModal = openEditModal;

if (new URLSearchParams(window.location.search).get('action') === 'create') {
    openModal('addModal');
}
