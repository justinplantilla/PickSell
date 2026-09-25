@vite('resources/css/views/partials-navbar.css')

@if(request()->is('/'))
<header class="landing-header landing-nav">
    <div class="landing-nav-inner">
        <a href="/" class="landing-logo"><img src="{{ asset('images/transparent logo.png') }}" alt="PickSell logo"><span>Pick<span>Sell</span></span></a>
        <nav class="landing-links">
            <a href="#how-it-works">How It Works</a>
            <a href="/register?role=seller">For Sellers</a>
            <a href="/register">For Couriers</a>
            <a href="#what-we-stand-for">About</a>
        </nav>
        <a href="/register" class="landing-nav-cta">Join PickSell</a>
    </div>
</header>
@else
<div class="nav-top">
    <a href="/" class="nav-logo"><img src="{{ asset('images/transparent logo.png') }}" alt="PickSell logo"><span>Pick<span>Sell</span></span></a>

    <form class="nav-search" action="/shop" method="GET">
        <input type="text" name="q" data-product-search placeholder="Search products, brands, categories..." value="{{ request('q') }}">
        <button type="submit">Search</button>
    </form>

    <div class="nav-actions">
        @auth
            <a href="/dashboard" class="nav-icon-btn" title="Account">
                <span class="blade-inline-1">{{ Auth::user()->first_name }}</span>
            </a>
        @else
            <div class="nav-auth">
                <a href="/login" class="btn btn-outline {{ request()->is('login') ? 'btn-outline-active' : '' }} {{ request()->is('register') ? 'btn-lightning' : '' }}">Log In</a>
                <a href="/register" class="btn btn-coral {{ request()->is('register') ? 'btn-coral-active' : '' }} {{ request()->is('login') ? 'btn-lightning' : '' }}">Sign Up</a>
            </div>
        @endauth
        <div class="nav-divider"></div>
        @auth
        <a href="/{{ Auth::user()->role === 'buyer' ? 'buyer/cart' : 'dashboard' }}" class="nav-icon-btn cart-link" title="Cart">
            <span class="cart-badge-wrap">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM5.2 5H2V3H0v2h2l3.6 7.59L4.25 15A2 2 0 0 0 6 18h14v-2H6.42a.25.25 0 0 1-.25-.25l.03-.12L7.1 14h9.45c.75 0 1.41-.41 1.75-1.03L21.7 6.5A1 1 0 0 0 20.83 5H5.2z"/></svg>
                @if(Auth::user()->role === 'buyer' && isset($cartCount) && $cartCount > 0)
                    <span class="cart-badge">{{ $cartCount }}</span>
                @endif
            </span>
        </a>
        @else
        <a href="/login" class="nav-icon-btn" title="Cart">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM5.2 5H2V3H0v2h2l3.6 7.59L4.25 15A2 2 0 0 0 6 18h14v-2H6.42a.25.25 0 0 1-.25-.25l.03-.12L7.1 14h9.45c.75 0 1.41-.41 1.75-1.03L21.7 6.5A1 1 0 0 0 20.83 5H5.2z"/></svg>
        </a>
        @endauth
    </div>
</div>
@endif

<div class="nav-cats {{ request()->is('/') ? 'landing-nav-cats' : '' }}">
    <a href="/" class="nav-cat {{ request()->is('/') ? 'nav-active' : '' }}">Home</a>
    <a href="/shop" class="nav-cat {{ request()->is('shop*') && !request('cat') ? 'nav-active' : '' }}">All Products</a>
    <a href="/shop?cat=electronics" class="nav-cat {{ request('cat') === 'electronics' ? 'nav-active' : '' }}">Electronics</a>
    <a href="/shop?cat=fashion" class="nav-cat {{ request('cat') === 'fashion' ? 'nav-active' : '' }}">Fashion</a>
    <a href="/shop?cat=home" class="nav-cat {{ request('cat') === 'home' ? 'nav-active' : '' }}">Home & Living</a>
    <a href="/shop?cat=sports" class="nav-cat {{ request('cat') === 'sports' ? 'nav-active' : '' }}">Sports</a>
    <a href="/shop?cat=beauty" class="nav-cat {{ request('cat') === 'beauty' ? 'nav-active' : '' }}">Beauty</a>
    <a href="/shop?cat=food%20%26%20grocery" class="nav-cat {{ request('cat') === 'food & grocery' ? 'nav-active' : '' }}">Food & Grocery</a>
    <a href="/shop?cat=books" class="nav-cat {{ request('cat') === 'books' ? 'nav-active' : '' }}">Books</a>
    <a href="/shop?cat=toys" class="nav-cat {{ request('cat') === 'toys' ? 'nav-active' : '' }}">Toys</a>
    <a href="/about" class="nav-cat {{ request()->is('about*') ? 'nav-active' : '' }}">About</a>
    <a href="/contact" class="nav-cat {{ request()->is('contact*') ? 'nav-active' : '' }}">Contact Us</a>
</div>

@if(!request()->is('/'))
@vite('resources/js/components/navbar.js')
@endif

@include('partials.search-suggestions')
