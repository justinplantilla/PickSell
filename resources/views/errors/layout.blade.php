{{--
    Branded error page. Self-contained (inline CSS, no Vite) so it still renders when the build or the app is broken.
    Sections: code, title, message. The logistics host gets the logistics portal's wording and links.
--}}
@php
    $onPickrush = request()->getHost() === config('pickrush.domain');
    // rescue(): on a 500 the session or database may be the thing that broke.
    $home = $onPickrush ? '/' : (rescue(fn () => auth()->check(), false, false) ? '/dashboard' : '/');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('code') · @yield('title') | {{ $onPickrush ? config('pickrush.portal_name') : 'PickSell' }}</title>
    <style>
        :root { --ground: #1f2328; --bone: #f6f3ee; --coral: #E8472A; --soft: #c9c3b9; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; min-height: 100dvh; display: grid; place-items: center; padding: 24px 16px; overflow: hidden;
               background: var(--ground); color: var(--bone); font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        .orb { position: fixed; top: -240px; right: -220px; width: 520px; height: 520px; border-radius: 50%; background: var(--coral); }
        svg.route { position: fixed; inset: auto 0 0 0; width: 100%; height: 45vh; pointer-events: none; }
        svg.route path { fill: none; stroke: rgba(246, 243, 238, .35); stroke-width: 3; stroke-linecap: round; stroke-dasharray: 1; stroke-dashoffset: 1; animation: draw 1.6s .2s cubic-bezier(.76, 0, .24, 1) forwards; }
        main { position: relative; max-width: 520px; text-align: center; }
        .code { display: block; font-size: clamp(72px, 18vw, 132px); font-weight: 800; line-height: 1; letter-spacing: -0.05em; color: var(--coral); }
        h1 { margin: 12px 0 10px; font-size: clamp(22px, 4vw, 30px); font-weight: 800; letter-spacing: -0.02em; }
        p { margin: 0 auto; max-width: 420px; color: var(--soft); font-size: 16px; line-height: 1.6; }
        .actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 28px; }
        a, button { display: inline-flex; align-items: center; justify-content: center; height: 46px; padding: 0 20px; border-radius: 10px; font: inherit; font-size: 15px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .primary { border: 0; background: var(--coral); color: #fff; }
        .primary:hover { background: #c93a20; }
        .ghost { border: 1.5px solid rgba(246, 243, 238, .35); background: transparent; color: var(--bone); }
        .ghost:hover { border-color: var(--bone); }
        :focus-visible { outline: 3px solid #ff8a6e; outline-offset: 3px; }
        .brand { margin-top: 36px; color: rgba(246, 243, 238, .5); font-size: 13px; font-weight: 600; }
        .brand b { color: var(--bone); }
        @keyframes draw { to { stroke-dashoffset: 0; } }
        @media (prefers-reduced-motion: reduce) { svg.route path { animation: none; stroke-dashoffset: 0; } }
    </style>
</head>
<body>
    <span class="orb" aria-hidden="true"></span>
    <svg class="route" viewBox="0 0 1200 400" preserveAspectRatio="none" aria-hidden="true"><path d="M-20 300C220 160 420 380 640 260S1010 120 1220 220" pathLength="1"/></svg>
    <main>
        <span class="code">@yield('code')</span>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            <a class="primary" href="{{ $home }}">{{ $onPickrush ? 'Go to PickSell Logistics' : 'Go to PickSell' }}</a>
            <button type="button" class="ghost" data-go-back data-home="{{ $home }}">Go back</button>
        </div>
        <div class="brand">{!! $onPickrush ? 'Pick<b>Sell</b> Logistics' : 'Pick<b>Sell</b>' !!}</div>
    </main>
    <script>
        document.querySelector('[data-go-back]').addEventListener('click', event => {
            history.length > 1 ? history.back() : location.assign(event.currentTarget.dataset.home);
        });
    </script>
</body>
</html>
