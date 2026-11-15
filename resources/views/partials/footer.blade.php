@vite('resources/css/views/partials-footer.css')
{{--
    Site footer for the public pages, sign-in and the buyer portal, the same dark footer on each.
    Links follow who is looking: buyers get their shop and account pages, everyone else the public
    ones and sign-up. The link groups are divs with role="navigation", not <nav>: the public layout
    styles every <nav> as its sticky top bar.
--}}
@php
    $isBuyer = auth()->check() && auth()->user()->role === 'buyer';
    $trackUrl = \Illuminate\Support\Facades\Route::has('pickrush.track') ? route('pickrush.track') : null;
    $partnerUrl = \Illuminate\Support\Facades\Route::has('pickrush.register') ? route('pickrush.register') : null;
@endphp
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-main">
            <div class="footer-brand">
                <a href="{{ $isBuyer ? route('buyer.home') : '/' }}" class="footer-logo"><img src="{{ asset('images/transparent logo.png') }}" alt="" width="29" height="29"><span>Pick<span>Sell</span></span></a>
                <p>The Philippines' marketplace for buyers and sellers. Pick it, sell it, and let PickSell handle the rest.</p>
                <ul class="footer-badges" aria-label="Payment and delivery">
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h18v10H3zM3 10h18M7 14h3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg> Cash on Delivery</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11v10H3zM14 10h4l3 3v3h-7zM7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg> Delivered by Pickrush</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 3v5c0 5-3.4 8.8-8 10-4.6-1.2-8-5-8-10V6Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg> Verified sellers</li>
                </ul>
            </div>

            <div class="footer-col" role="navigation" aria-labelledby="footer-shop">
                <h4 id="footer-shop">Shop</h4>
                @if($isBuyer)
                    <a href="{{ route('buyer.home') }}">All products</a>
                    <a href="{{ route('buyer.browse', ['deals' => 1]) }}">Deals &amp; promos</a>
                    <a href="{{ route('buyer.recommended') }}">Recommended for you</a>
                    <a href="{{ route('buyer.categories', ['category' => 'Fashion']) }}">Fashion</a>
                    <a href="{{ route('buyer.categories', ['category' => 'Electronics']) }}">Electronics</a>
                    <a href="{{ route('buyer.categories', ['category' => 'Home & Living']) }}">Home &amp; Living</a>
                @else
                    <a href="/shop">Browse products</a>
                    <a href="/shop?cat=fashion">Fashion</a>
                    <a href="/shop?cat=electronics">Electronics</a>
                    <a href="/shop?cat=home">Home &amp; Living</a>
                @endif
            </div>

            <div class="footer-col" role="navigation" aria-labelledby="footer-account">
                <h4 id="footer-account">{{ $isBuyer ? 'My Account' : 'Get started' }}</h4>
                @if($isBuyer)
                    <a href="{{ route('buyer.orders') }}">My orders</a>
                    <a href="{{ route('buyer.cart') }}">My cart</a>
                    <a href="{{ route('buyer.wishlist') }}">Wishlist</a>
                    <a href="{{ route('buyer.chat') }}">Messages</a>
                    <a href="{{ route('buyer.account') }}">Profile &amp; settings</a>
                @elseif(auth()->check())
                    <a href="/">Go to my dashboard</a>
                @else
                    <a href="/login">Log in</a>
                    <a href="/register">Create a buyer account</a>
                @endif
            </div>

            <div class="footer-col" role="navigation" aria-labelledby="footer-quick">
                <h4 id="footer-quick">Quick Links</h4>
                <a href="{{ route('help') }}#shipping">Shipping &amp; delivery</a>
                <a href="{{ route('help') }}#returns">Returns &amp; refunds</a>
                <a href="{{ route('help') }}#cancellations">Cancellations</a>
                <a href="{{ route('help') }}#faq">FAQ</a>
                @if($trackUrl)<a href="{{ $trackUrl }}">Track a parcel</a>@endif
                <a href="{{ route('contact') }}">Contact us</a>
            </div>

            <div class="footer-col" role="navigation" aria-labelledby="footer-partners">
                <h4 id="footer-partners">Partner with us</h4>
                <a href="{{ auth()->check() ? route('help').'#selling' : '/register?role=seller' }}">Sell on PickSell</a>
                @if($partnerUrl)<a href="{{ $partnerUrl }}">Logistics partners</a>@endif
                @if(config('pickrush.rider_app_url'))<a href="{{ config('pickrush.rider_app_url') }}" rel="noopener">Become a rider</a>@endif
                <a href="{{ route('about') }}">About PickSell</a>
            </div>
        </div>

        <div class="footer-bottom">
            <span>&copy; {{ date('Y') }} PickSell · Made in the Philippines</span>
            <div class="footer-bottom-links">
                <a href="{{ route('privacy') }}">Privacy Policy</a>
                <a href="{{ route('terms') }}">Terms of Service</a>
                <a href="#" class="footer-top-link">Back to top ↑</a>
            </div>
        </div>
    </div>
</footer>
