<style>
    footer {
        background: var(--charcoal);
        color: #aaa;
        font-size: 0.85rem;
        margin-top: 4rem;
    }
    .footer-main {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 2rem;
        max-width: 1100px;
        margin: 0 auto;
        padding: 3rem 1.5rem 2rem;
    }
    @media(max-width: 700px) {
        .footer-main { grid-template-columns: 1fr 1fr; }
        .footer-brand { grid-column: 1 / -1; }
    }
    .footer-logo { display: inline-flex; align-items: center; gap: 0.45rem; color: var(--coral); font-size: 1.5rem; font-weight: 800; letter-spacing: -1px; line-height: 1; margin-bottom: 0.6rem; text-decoration: none; }
    .footer-logo img { width: 29px; height: 29px; object-fit: contain; display: block; mix-blend-mode: lighten; }
    .footer-logo span { color: #fff; }
    .footer-brand p { line-height: 1.7; font-size: 0.83rem; }

    .footer-col h4 { color: #fff; font-size: 0.88rem; font-weight: 700; margin-bottom: 0.9rem; }
    .footer-col a { display: block; color: #aaa; margin-bottom: 0.5rem; font-size: 0.83rem; text-decoration: none; transition: color 0.2s; }
    .footer-col a:hover { color: var(--coral); }

    .footer-bottom {
        border-top: 1px solid #3a3a3a;
        padding: 1.2rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.5rem;
        max-width: 1100px;
        margin: 0 auto;
        font-size: 0.8rem;
    }
    .footer-bottom-links { display: flex; gap: 1.2rem; }
    .footer-bottom-links a { color: #aaa; text-decoration: none; }
    .footer-bottom-links a:hover { color: var(--coral); }
</style>

<footer class="{{ !empty($buyerFooter) ? 'buyer-layout-footer' : '' }}">
    <div class="footer-main">
        <div class="footer-brand">
            <a href="/" class="footer-logo"><img src="{{ asset('images/transparent logo.png') }}" alt="PickSell logo"><span>Pick<span>Sell</span></span></a>
            <p>The Philippines' trusted marketplace for buyers and sellers. Pick it, sell it, and let PickSell handle the rest.</p>
        </div>
        <div class="footer-col">
            <h4>Shop</h4>
            <a href="/shop">All Products</a>
            <a href="/shop?cat=electronics">Electronics</a>
            <a href="/shop?cat=fashion">Fashion</a>
            <a href="/shop?cat=home">Home & Living</a>
            <a href="/shop?cat=beauty">Beauty</a>
        </div>
        <div class="footer-col">
            <h4>Account</h4>
            <a href="/login">Log In</a>
            <a href="/register">Sign Up</a>
            <a href="/register">Become a Seller</a>
        </div>
        <div class="footer-col">
            <h4>Support</h4>
            <a href="/contact">Help Center</a>
            <a href="/contact">Contact Us</a>
            <a href="/about">About Us</a>
        </div>
    </div>
    <div class="footer-bottom">
        <span>&copy; {{ date('Y') }} PickSell. All rights reserved.</span>
        <div class="footer-bottom-links">
            <a href="/about">Privacy Policy</a>
            <a href="/about">Terms of Service</a>
        </div>
    </div>
</footer>
