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
}
function changeQty(delta) {
    const input = document.getElementById('qtyInput');
    const max   = parseInt(input.max);
    let val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > max) val = max;
    input.value = val;
}

const showToast = (...args) => window.showToast(...args);

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

document.getElementById('addToCartBtn')?.addEventListener('click', event => {
    confirmCartAction(event.currentTarget, 'Add this product to your cart?');
});
document.getElementById('buyNowBtn')?.addEventListener('click', event => {
    confirmCartAction(event.currentTarget, 'Add this product and continue to checkout?', { redirectToCheckout: true });
});

window.selectVariation = selectVariation;
window.changeQty = changeQty;
window.addProductToCart = addProductToCart;

const galleryMain = document.querySelector('[data-gallery-main]');
document.querySelectorAll('[data-gallery-thumb]').forEach(thumb => {
    thumb.addEventListener('click', () => {
        if (!galleryMain) return;
        galleryMain.src = thumb.dataset.src;
        galleryMain.alt = thumb.dataset.alt;
        document.querySelectorAll('[data-gallery-thumb]').forEach(t => t.classList.toggle('is-active', t === thumb));
    });
});

document.querySelectorAll('.var-btn[data-type-slug]').forEach(button => button.addEventListener('click', () => selectVariation(button, button.dataset.typeSlug)));
document.querySelectorAll('[data-qty-step]').forEach(button => button.addEventListener('click', () => changeQty(Number(button.dataset.qtyStep))));

// Zoom: hovering magnifies under the pointer (mouse only); clicking opens the photo full size.
const zoom = document.querySelector('[data-zoom]');
if (zoom) {
    const img = zoom.querySelector('img');
    const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (finePointer && !still) {
        zoom.addEventListener('mousemove', event => {
            const box = zoom.getBoundingClientRect();
            img.style.transformOrigin = `${((event.clientX - box.left) / box.width) * 100}% ${((event.clientY - box.top) / box.height) * 100}%`;
            img.style.transform = 'scale(2)';
        });
        zoom.addEventListener('mouseleave', () => { img.style.transform = ''; });
    }
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
