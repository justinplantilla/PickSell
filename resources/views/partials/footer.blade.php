@vite('resources/css/views/partials-footer.css')

<footer>
    <div class="footer-inner">
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
    </div>
</footer>
