<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('images/transparent logo.png') }}">
    <title>PickSell — @yield('title', 'Pick it. Sell it. We handle the rest.')</title>
    <style>
        :root {
            --coral: #E8472A;
            --coral-dark: #c93a20;
            --charcoal: #2D2D2D;
            --charcoal-light: #3d3d3d;
            --bone: #F5F0E8;
            --bone-dark: #ede7d9;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body, body *, body *::before, body *::after { transition: background-color 0.35s ease, color 0.35s ease, border-color 0.35s ease, box-shadow 0.35s ease; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bone); color: var(--charcoal); }
        body.dark { background: #111; color: #f3f4f6; }
        body.dark .nav-top,
        body.dark .nav-cats,
        body.dark footer,
        body.dark .nav-search input,
        body.dark .help-card,
        body.dark .faq-item,
        body.dark .support-form,
        body.dark .auth-card,
        body.dark .stat-card,
        body.dark .card,
        body.dark .product-card,
        body.dark .step,
        body.dark .search-box,
        body.dark .filter-card,
        body.dark .modal,
        body.dark .form-control { background: #1b1b1b; color: #f3f4f6; border-color: #333; }
        body.dark .nav-links a,
        body.dark .nav-cat,
        body.dark .nav-logo span,
        body.dark .nav-icon-btn,
        body.dark .nav-search input::placeholder,
        body.dark .section-body,
        body.dark .faq-a,
        body.dark .help-card p,
        body.dark .stat-label,
        body.dark .auth-sub,
        body.dark .auth-footer,
        body.dark .nav-search input,
        body.dark .trust-item,
        body.dark .product-name,
        body.dark .product-seller,
        body.dark .section-title,
        body.dark .section-label,
        body.dark .contact-hero p,
        body.dark .about-hero p,
        body.dark .auth-sub,
        body.dark .auth-footer,
        body.dark .text-muted,
        body.dark .nav-auth .btn-outline,
        body.dark .nav-cat { color: #d1d5db; }
        body.dark .nav-search input { background: #2a2a2a; border-color: #444; }
        body.dark .nav-logo { color: var(--coral); }
        body.dark .nav-cat:hover { background: #2a2a2a; }
        body.dark .nav-cat.nav-active { color: var(--coral); }
        a { text-decoration: none; color: inherit; }

        .dm-toggle { position: fixed; bottom: 1.2rem; right: 1.2rem; width: 42px; height: 42px; border-radius: 50%; background: #2D2D2D; color: #fff; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 12px rgba(0,0,0,0.25); z-index: 9999; }
        .dm-toggle:hover { background: var(--coral); }

        nav {
            background: var(--charcoal);
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 64px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .nav-logo { color: var(--coral); font-size: 1.6rem; font-weight: 800; letter-spacing: -1px; }
        .nav-logo span { color: #fff; }
        .nav-links { display: flex; align-items: center; gap: 1.5rem; }
        .nav-links a { color: #ccc; font-size: 0.9rem; transition: color 0.2s; }
        .nav-links a:hover { color: #fff; }

        .btn { display: inline-block; padding: 0.55rem 1.3rem; border-radius: 6px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; }
        .btn-coral { background: var(--coral); color: #fff; }
        .btn-coral:hover { background: var(--coral-dark); }
        .btn-coral-active { background: var(--coral-dark) !important; outline: 2px solid rgba(255,255,255,0.3); outline-offset: 2px; }
        .btn-outline { background: transparent; color: #fff; border: 1.5px solid #555; }
        .btn-outline:hover { border-color: #fff; }
        .btn-outline-active { border-color: #fff !important; color: #fff !important; }

        footer { background: var(--charcoal); color: #aaa; text-align: center; padding: 1.5rem; font-size: 0.85rem; margin-top: 4rem; }
        /* partials: navbar -> partials/navbar.blade.php | footer -> partials/footer.blade.php */

        @media (min-width: 1440px) {
            nav { padding-inline: clamp(2rem, 4vw, 5rem); }
            .nav-top { padding-inline: max(2rem, calc((100vw - 1680px) / 2)); }
            .nav-search { max-width: 760px; }
            footer { padding-inline: clamp(2rem, 5vw, 8rem); }
        }

        @media (max-width: 640px) {
            .dm-toggle { right: 0.75rem; bottom: 0.75rem; }
            footer { padding-inline: 1rem; }
        }
    </style>
    @yield('styles')
</head>
<body class="{{ request()->is('/') ? 'landing-page' : '' }}">
    @include('partials.navbar')

    @yield('content')

    @include('partials.footer')

    <button class="dm-toggle" onclick="toggleDark()" title="Toggle dark mode" aria-label="Toggle dark mode">
        <svg id="dmIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z"/></svg>
    </button>

    <script>
        function toggleDark() {
            document.body.classList.toggle('dark');
            localStorage.setItem('darkMode', document.body.classList.contains('dark'));
        }
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark');
        }
    </script>
    @yield('scripts')
</body>
</html>
