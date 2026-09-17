<style>
    @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap');

    .nav-top {
        background: #1b1b1b;
        padding: 0 max(1.5rem, calc((100vw - 1280px) / 2));
        display: flex;
        align-items: center;
        gap: 1.75rem;
        height: 72px;
        position: sticky;
        top: 0;
        z-index: 100;
        font-family: 'Manrope', sans-serif;
        border-bottom: 1px solid #333230;
        transition: background .35s ease, box-shadow .35s ease, backdrop-filter .35s ease;
        animation: nav-bar-in .65s cubic-bezier(.22, 1, .36, 1) both;
    }
    .nav-top.is-scrolled { background: rgba(27, 27, 27, .78); box-shadow: 0 8px 24px rgba(0, 0, 0, .16); backdrop-filter: blur(14px); }
    .nav-logo { display: inline-flex; align-items: center; gap: 0.55rem; color: #f9f8f6; font-size: 1.12rem; font-weight: 800; letter-spacing: -0.04em; line-height: 1; white-space: nowrap; }
    .nav-logo img { width: 32px; height: 32px; object-fit: contain; display: block; mix-blend-mode: normal; }
    .nav-logo span { color: #f9f8f6; }
    .nav-logo > span > span { color: #ff6f61; }
    .nav-logo:hover { transform: translateY(-1px); }

    .nav-search {
        flex: 1;
        display: flex;
        max-width: 560px;
        animation: nav-item-in .55s .08s cubic-bezier(.22, 1, .36, 1) both;
    }
    .nav-search input {
        flex: 1;
        padding: 0.72rem 1rem;
        border: 1px solid transparent;
        border-radius: 10px 0 0 10px;
        font-family: 'Manrope', sans-serif;
        font-size: 0.76rem;
        background: #f9f8f6;
        color: #1b1b1b;
        outline: none;
        transition: border-color .25s, background-color .25s;
    }
    .nav-search input:focus { border-color: #ff6f61; background: #fff; }
    .nav-search button {
        position: relative;
        overflow: hidden;
        background: #ff6f61;
        border: none;
        padding: 0 1.15rem;
        border-radius: 0 10px 10px 0;
        cursor: pointer;
        color: #fff;
        font-family: 'Manrope', sans-serif;
        font-size: 0.76rem;
        font-weight: 700;
        transition: background .25s, transform .25s;
    }
    .nav-search button::before { content: ''; position: absolute; inset: 0 auto 0 -80%; width: 45%; background: rgba(255,255,255,.3); transform: skewX(-20deg); transition: left .55s ease; }
    .nav-search button:hover::before { left: 135%; }
    .nav-search button:hover { background: #e85a4d; }

    .nav-actions { display: flex; align-items: center; gap: 1rem; margin-left: auto; animation: nav-item-in .55s .14s cubic-bezier(.22, 1, .36, 1) both; }
    .nav-icon-btn {
        background: transparent;
        border: none;
        color: #d7d4cf;
        font-size: 1.3rem;
        cursor: pointer;
        position: relative;
        display: flex;
        align-items: center;
        gap: 0.3rem;
        transition: color .25s, transform .25s;
        text-decoration: none;
    }
    .nav-icon-btn:hover { color: #fff; transform: translateY(-2px); }
    .nav-icon-btn .badge {
        position: absolute;
        top: -6px;
        right: -8px;
        background: var(--coral);
        color: #fff;
        font-size: 0.65rem;
        font-weight: 700;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .nav-divider { width: 1px; height: 24px; background: #444; }

    .nav-auth { display: flex; align-items: center; gap: 0.65rem; }
    .btn { display: inline-block; padding: 0.62rem 1.05rem; border-radius: 9px; font-family: 'Manrope', sans-serif; font-size: 0.72rem; font-weight: 700; cursor: pointer; transition: all .25s; border: none; text-decoration: none; }
    @property --nav-cta-angle { syntax: '<angle>'; initial-value: 0deg; inherits: false; }
    .btn-coral { position: relative; isolation: isolate; overflow: visible; background: #ff6f61; color: #fff; box-shadow: 0 6px 16px rgba(255,111,97,.35); }
    .btn-coral.btn-lightning::before { content: ''; position: absolute; z-index: -2; inset: -1px; border-radius: 10px; background: conic-gradient(from var(--nav-cta-angle), #ff6f61 0deg, #ff6f61 72deg, #fff4f1 88deg, #ff6f61 104deg, #ff6f61 360deg); animation: nav-cta-border-spin 1.8s linear infinite; filter: drop-shadow(0 0 4px rgba(255,111,97,.32)); }
    .btn-coral.btn-lightning::after { content: ''; position: absolute; z-index: -1; inset: 1px; border-radius: 8px; background: #ff6f61; }
    .btn-coral:hover { background: #e85a4d; transform: translateY(-2px); box-shadow: 0 10px 26px rgba(255,111,97,.5); }
    .btn-coral-active { background: var(--coral-dark) !important; outline: 2px solid rgba(255,255,255,0.3); outline-offset: 2px; }
    .btn-outline { position: relative; isolation: isolate; background: transparent; color: #f9f8f6; border: 1px solid rgba(255,255,255,.25); overflow: visible; }
    .btn-outline.btn-lightning::before { content: ''; position: absolute; z-index: -2; inset: -1px; border-radius: 10px; background: conic-gradient(from var(--nav-cta-angle), transparent 0deg, transparent 48deg, #ff6f61 72deg, #fff4f1 88deg, #ff6f61 104deg, transparent 132deg, transparent 360deg); animation: nav-cta-border-spin 1.8s linear infinite; filter: drop-shadow(0 0 4px rgba(255,111,97,.32)); }
    .btn-outline.btn-lightning::after { content: ''; position: absolute; z-index: -1; inset: 1px; border-radius: 8px; background: #1b1b1b; }
    .btn-outline:hover { border-color: #fff; color: #fff; transform: translateY(-2px); }
    .btn-outline-active { border-color: #fff !important; }

    /* Categories bar */
    .nav-cats {
        background: #222;
        padding: 0 2rem;
        display: flex;
        align-items: center;
        gap: 0;
        overflow-x: auto;
        scrollbar-width: none;
        border-bottom: 1px solid #333;
    }
    .nav-cats::-webkit-scrollbar { display: none; }
    .nav-cat {
        color: #bbb;
        font-size: 0.82rem;
        font-weight: 500;
        padding: 0.6rem 1rem;
        white-space: nowrap;
        text-decoration: none;
        transition: color 0.25s, background 0.25s, transform 0.25s, box-shadow 0.25s;
        position: relative;
        animation: nav-category-in .45s ease both;
    }
    .nav-cat:nth-child(2) { animation-delay: .04s; }
    .nav-cat:nth-child(3) { animation-delay: .08s; }
    .nav-cat:nth-child(4) { animation-delay: .12s; }
    .nav-cat:nth-child(5) { animation-delay: .16s; }
    .nav-cat:nth-child(6) { animation-delay: .20s; }
    .nav-cat:nth-child(7) { animation-delay: .24s; }
    .nav-cat:nth-child(8) { animation-delay: .28s; }
    .nav-cat:nth-child(9) { animation-delay: .32s; }
    .nav-cat:hover { color: #fff; background: #2a2a2a; transform: translateY(-2px); box-shadow: inset 0 -2px 0 var(--coral); }
    .nav-cat.nav-active { color: var(--coral); }
    .nav-cat.nav-active::after {
        content: '';
        position: absolute;
        bottom: 0; left: 0; right: 0;
        height: 2px;
        background: var(--coral);
        transform-origin: left;
        animation: nav-underline-in .35s ease both;
    }
    @keyframes nav-bar-in { from { opacity: 0; transform: translateY(-14px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes nav-item-in { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes nav-cta-border-spin { to { --nav-cta-angle: 360deg; } }
    @keyframes nav-category-in { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes nav-underline-in { from { transform: scaleX(0); } to { transform: scaleX(1); } }
    @media (prefers-reduced-motion: reduce) {
        .nav-top, .nav-search, .nav-actions, .nav-cat { animation: none; }
        .btn-coral.btn-lightning::before, .btn-outline.btn-lightning::before { animation: none; }
    }


    @media (max-width: 768px) {
        .nav-top { height: auto; min-height: 64px; padding: 0.75rem 1rem; flex-wrap: wrap; gap: 0.7rem; }
        .nav-logo { font-size: 1.35rem; }
        .nav-logo img { width: 27px; height: 27px; }
        .nav-search { order: 3; flex-basis: 100%; max-width: none; }
        .nav-actions { gap: 0.65rem; }
        .nav-auth { gap: 0.4rem; }
        .nav-auth .btn { padding: 0.45rem 0.7rem; font-size: 0.78rem; }
        .nav-divider { display: none; }
        .nav-cats { padding: 0 0.75rem; }
        .nav-cat { padding: 0.6rem 0.75rem; font-size: 0.78rem; }
    }

    @media (max-width: 420px) {
        .nav-actions .nav-icon-btn span { display: none; }
        .nav-auth .btn-outline { display: none; }
    }
</style>

@if(request()->is('/'))
<header class="landing-header landing-nav">
    <div class="landing-nav-inner">
        <a href="/" class="landing-logo"><img src="{{ asset('images/transparent logo.png') }}" alt="PickSell logo"><span>Pick<span>Sell</span></span></a>
        <nav class="landing-links">
            <a href="#how-it-works">How It Works</a>
            <a href="/register?role=seller">For Sellers</a>
            <a href="/register?role=courier">For Couriers</a>
            <a href="#what-we-stand-for">About</a>
        </nav>
        <a href="/register" class="landing-nav-cta">Join PickSell</a>
    </div>
</header>
@else
<div class="nav-top">
    <a href="/" class="nav-logo"><img src="{{ asset('images/transparent logo.png') }}" alt="PickSell logo"><span>Pick<span>Sell</span></span></a>

    <form class="nav-search" action="/shop" method="GET">
        <input type="text" name="q" placeholder="Search products, brands, categories..." value="{{ request('q') }}">
        <button type="submit">Search</button>
    </form>

    <div class="nav-actions">
        @auth
            <a href="/dashboard" class="nav-icon-btn" title="Account">
                <span style="font-size:0.82rem;color:#ccc;">{{ Auth::user()->first_name }}</span>
            </a>
        @else
            <div class="nav-auth">
                <a href="/login" class="btn btn-outline {{ request()->is('login') ? 'btn-outline-active' : '' }} {{ request()->is('register') ? 'btn-lightning' : '' }}">Log In</a>
                <a href="/register" class="btn btn-coral {{ request()->is('register') ? 'btn-coral-active' : '' }} {{ request()->is('login') ? 'btn-lightning' : '' }}">Sign Up</a>
            </div>
        @endauth
        <div class="nav-divider"></div>
        @auth
        <a href="/{{ Auth::user()->role === 'buyer' ? 'buyer/cart' : 'dashboard' }}" class="nav-icon-btn" title="Cart" style="position:relative;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM5.2 5H2V3H0v2h2l3.6 7.59L4.25 15A2 2 0 0 0 6 18h14v-2H6.42a.25.25 0 0 1-.25-.25l.03-.12L7.1 14h9.45c.75 0 1.41-.41 1.75-1.03L21.7 6.5A1 1 0 0 0 20.83 5H5.2z"/></svg>
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
    <a href="/about" class="nav-cat {{ request()->is('about*') ? 'nav-active' : '' }}">About</a>
    <a href="/contact" class="nav-cat {{ request()->is('contact*') ? 'nav-active' : '' }}">Contact Us</a>
</div>

@if(!request()->is('/'))
<script>
    (() => {
        const nav = document.querySelector('.nav-top');
        if (!nav) return;
        const syncNav = () => nav.classList.toggle('is-scrolled', window.scrollY > 18);
        syncNav();
        window.addEventListener('scroll', syncNav, { passive: true });
    })();
</script>
@endif
