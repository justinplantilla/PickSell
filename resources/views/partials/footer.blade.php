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
                @auth
                    @if(auth()->user()->role === 'buyer')
                        <a href="{{ route('buyer.home') }}">All Products</a>
                        <a href="{{ route('buyer.browse', ['deals' => 1]) }}">Deals & Promos</a>
                        <a href="{{ route('buyer.recommended') }}">Recommended</a>
                        <a href="{{ route('buyer.categories', ['category' => 'Electronics']) }}">Electronics</a>
                        <a href="{{ route('buyer.categories', ['category' => 'Fashion']) }}">Fashion</a>
                        <a href="{{ route('buyer.categories', ['category' => 'Home & Living']) }}">Home & Living</a>
                    @else
                        <a href="/">Home</a>
                        <a href="/about">About</a>
                        <a href="/contact">Contact</a>
                    @endif
                @else
                    <a href="/">Home</a>
                    <a href="/about">About</a>
                    <a href="/contact">Contact</a>
                @endauth
            </div>
            <div class="footer-col">
                <h4>My Account</h4>
                @auth
                    @if(auth()->user()->role === 'buyer')
                        <a href="{{ route('buyer.account') }}">Profile & Settings</a>
                        <a href="{{ route('buyer.orders') }}">My Orders</a>
                        <a href="{{ route('buyer.cart') }}">My Cart</a>
                        <a href="{{ route('buyer.chat') }}">Messages</a>
                    @else
                        <a href="/login">Log In</a>
                        <a href="/register">Sign Up</a>
                    @endif
                @else
                    <a href="/login">Log In</a>
                    <a href="/register">Sign Up</a>
                    <a href="/register">Become a Seller</a>
                @endauth
            </div>
            <div class="footer-col">
                <h4>Support</h4>
                <a href="{{ route('contact') }}">Help Center</a>
                <a href="{{ route('contact') }}">Contact Us</a>
                <a href="{{ route('about') }}">About PickSell</a>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; {{ date('Y') }} PickSell. All rights reserved.</span>
            <div class="footer-bottom-links">
                <a href="{{ route('about') }}">Privacy Policy</a>
                <a href="{{ route('about') }}">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>
