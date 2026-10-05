import { initProductGalleries } from '../components/product-gallery.js';

initProductGalleries();

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
    f.querySelector('[name=discount]').value = p.discount || 0;
    f.querySelector('[name=voucher_code]').value = p.voucher_code || '';
    f.querySelector('[name=voucher_discount]').value = p.voucher_discount || 0;
    f.querySelector('[name=stock]').value = p.stock;
    f.querySelector('[data-product-gallery]').productGallery?.load(p.images || [], p.name);
    openModal('editModal');
}

window.openModal = openModal;
window.closeModal = closeModal;
window.openEditModal = openEditModal;

if (new URLSearchParams(window.location.search).get('action') === 'create') {
    openModal('addModal');
}
