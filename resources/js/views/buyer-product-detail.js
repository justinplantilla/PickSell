function selectVariation(btn, typeSlug) {
    document.querySelectorAll(`.var-btn[data-type="${btn.dataset.type}"]`).forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    document.getElementById('selected_' + typeSlug).textContent = btn.dataset.value;
    document.getElementById('variation_id').value = btn.dataset.id;
}
function changeQty(delta) {
    const input = document.getElementById('qtyInput');
    const max   = parseInt(input.max);
    let val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > max) val = max;
    input.value = val;
}

function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position:fixed;left:1rem;bottom:1rem;z-index:99999;display:flex;flex-direction:column;gap:0.6rem;align-items:flex-start;pointer-events:none;';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.style.cssText = `padding:0.75rem 1.1rem;border-radius:10px;font-size:0.82rem;font-weight:600;color:#fff;background:${type === 'success' ? '#16a34a' : '#dc2626'};box-shadow:0 10px 24px rgba(0,0,0,0.18);animation:toast-in 0.25s ease;border:1px solid rgba(255,255,255,0.12);max-width:min(320px, calc(100vw - 2rem));pointer-events:auto;`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 3000);
}

const style = document.createElement('style');
style.textContent = '@keyframes toast-in{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}';
document.head.appendChild(style);

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

document.getElementById('addToCartBtn')?.addEventListener('click', () => addProductToCart());
document.getElementById('buyNowBtn')?.addEventListener('click', () => addProductToCart({ redirectToCheckout: true }));

window.selectVariation = selectVariation;
window.changeQty = changeQty;
window.addProductToCart = addProductToCart;
