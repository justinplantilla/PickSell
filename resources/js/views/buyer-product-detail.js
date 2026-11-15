function selectVariation(btn, typeSlug) {
    document.querySelectorAll(`.var-btn[data-type="${btn.dataset.type}"]`).forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    document.getElementById('selected_' + typeSlug).textContent = btn.dataset.value;
    document.getElementById('variation_id').value = btn.dataset.id;
    // Each option has its own stock: cap the quantity and show what this option has left.
    const qty = document.getElementById('qtyInput');
    qty.max = btn.dataset.stock;
    if (parseInt(qty.value) > parseInt(qty.max)) qty.value = qty.max;
    document.getElementById('stockAvailable').textContent = `${btn.dataset.stock} available`;
    if (btn.dataset.price) document.getElementById('detailPrice').textContent = btn.dataset.price;
    if (btn.dataset.image) showGalleryImage(btn.dataset.image, btn.dataset.value);
}
function changeQty(delta) {
    const input = document.getElementById('qtyInput');
    const max   = parseInt(input.max);
    let val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > max) val = max;
    input.value = val;
}

const showToast = (...args) => window.notify(...args);

function updateCartBadge(delta = 1) {
    const badge = document.querySelector('.cart-badge');
    if (badge) {
        badge.textContent = String(parseInt(badge.textContent || '0') + delta);
        return;
    }

    const cartWrap = document.querySelector('.nav-icon-btn[href="/buyer/cart"] .cart-badge-wrap, a[href="/buyer/cart"] > div, a[href="/buyer/cart"]');
    if (cartWrap) {
        const currentWrap = cartWrap.tagName === 'DIV' ? cartWrap : cartWrap.querySelector('div');
        const badgeNode = document.createElement('span');
        badgeNode.className = 'cart-badge';
        badgeNode.textContent = String(delta > 0 ? delta : 1);
        currentWrap?.appendChild(badgeNode);
    }
}

async function addProductToCart({ redirectToCheckout = false } = {}) {
    const form = document.getElementById('addCartForm');
    if (!optionChosen()) return;
    const data = new FormData(form);
    const button = redirectToCheckout ? document.getElementById('buyNowBtn') : document.getElementById('addToCartBtn');
    if (button) button.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' },
            body: data,
        });

        if (!response.ok) {
            throw new Error('Failed to add to cart');
        }

        const payload = await response.json();
        const badge = document.querySelector('.cart-badge');
        if (badge) {
            badge.textContent = String(parseInt(badge.textContent || '0') + 1);
        } else {
            updateCartBadge(1);
        }

        showToast(redirectToCheckout ? 'Preparing checkout...' : 'Added to cart successfully.');

        if (redirectToCheckout && payload.item_id) {
            window.location.href = `/buyer/checkout?item_ids[]=${encodeURIComponent(payload.item_id)}`;
            return;
        }

        if (redirectToCheckout) {
            window.location.href = '/buyer/cart';
        }
    } catch (error) {
        showToast('Failed to add to cart. Please try again.', 'error');
    } finally {
        if (button) button.disabled = false;
    }
}

/** Products sold in options need one picked before they go in the cart. */
function optionChosen() {
    const variation = document.getElementById('variation_id');
    if (variation.dataset.required === '1' && !variation.value) {
        showToast('Choose an option first.', 'error');
        return false;
    }
    return true;
}

function confirmCartAction(button, message, options) {
    if (button.disabled || !optionChosen()) return;
    button.disabled = true;
    window.showConfirm(message).then(confirmed => {
        if (confirmed) addProductToCart(options);
        else button.disabled = false;
    });
}

// Adding to the cart is undone from the cart, so it needs no confirmation; Buy Now still asks.
document.getElementById('addToCartBtn')?.addEventListener('click', () => addProductToCart());
document.getElementById('buyNowBtn')?.addEventListener('click', event => {
    confirmCartAction(event.currentTarget, 'Add this product and continue to checkout?', { redirectToCheckout: true });
});

window.selectVariation = selectVariation;
window.changeQty = changeQty;
window.addProductToCart = addProductToCart;

const galleryMain = document.querySelector('[data-gallery-main]');
/** Show one gallery photo as the main image and mark its thumbnail. */
function showGalleryImage(src, alt) {
    if (!galleryMain) return;
    const thumbs = [...document.querySelectorAll('[data-gallery-thumb]')];
    const match = thumbs.find(t => t.dataset.src === src);
    galleryMain.src = src;
    galleryMain.alt = match?.dataset.alt || alt || galleryMain.alt;
    thumbs.forEach(t => t.classList.toggle('is-active', t === match));
}
document.querySelectorAll('[data-gallery-thumb]').forEach(thumb => {
    thumb.addEventListener('click', () => showGalleryImage(thumb.dataset.src, thumb.dataset.alt));
});

document.querySelectorAll('.var-btn[data-type-slug]').forEach(button => button.addEventListener('click', () => selectVariation(button, button.dataset.typeSlug)));
// Coming from the cart or checkout (?option=ID): that option starts picked, if it is still in stock.
const preselected = new URLSearchParams(window.location.search).get('option');
const preselectedButton = preselected && document.querySelector(`.var-btn[data-type-slug][data-id="${CSS.escape(preselected)}"]:not(:disabled)`);
if (preselectedButton) selectVariation(preselectedButton, preselectedButton.dataset.typeSlug);
document.querySelectorAll('[data-qty-step]').forEach(button => button.addEventListener('click', () => changeQty(Number(button.dataset.qtyStep))));

// Zoom: clicking opens the photo full size.
const zoom = document.querySelector('[data-zoom]');
if (zoom) {
    const img = zoom.querySelector('img');
    zoom.addEventListener('click', () => {
        const overlay = document.createElement('div');
        overlay.className = 'zoom-lightbox';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', img.alt);
        overlay.innerHTML = '<button type="button" class="zoom-lightbox-close" aria-label="Close">×</button><img alt="">';
        overlay.querySelector('img').src = img.src;
        overlay.querySelector('img').alt = img.alt;
        const close = () => { overlay.remove(); document.removeEventListener('keydown', onKey); zoom.focus(); };
        const onKey = event => { if (event.key === 'Escape') close(); };
        overlay.addEventListener('click', event => { if (event.target !== overlay.querySelector('img')) close(); });
        document.addEventListener('keydown', onKey);
        document.body.appendChild(overlay);
        overlay.querySelector('.zoom-lightbox-close').focus();
    });
}
