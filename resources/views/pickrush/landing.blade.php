<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('pickrush.portal_name') }} | Delivery partners of PickSell</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/psl-logo.svg') }}">
    <meta name="description" content="Logistics companies deliver PickSell orders through their province and station hubs, then a local rider brings each one home.">
    @vite(['resources/css/pickrush/public.css', 'resources/js/pickrush/landing.js'])
</head>
<body class="prp">
<header class="prp-header" data-header>
    <div class="prp-wrap prp-header-inner">
        <a class="prp-brand" href="{{ route('pickrush.dashboard') }}">
            @include('pickrush.partials.logo-mark', ['class' => 'prp-mark', 'size' => 40])
            <span>PickSell <span class="prp-accent">Logistics</span></span>
        </a>
        <nav class="prp-nav" aria-label="Main">
            <a href="#how">How it works</a>
            <a href="#track">Track a parcel</a>
            <a href="#partners">Partners</a>
            <a href="#apps">Get the app</a>
            <a href="{{ route('pickrush.register') }}">Register</a>
            <a class="prp-btn prp-btn-sm" href="{{ route('pickrush.login') }}">Staff sign-in <svg class="prp-btn-arrow" aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></a>
        </nav>
    </div>
</header>

<main>
    <section class="prp-wrap prp-hero">
        <div class="prp-hero-copy">
            <span class="prp-pill"><span aria-hidden="true"></span>PickSell's logistics partners</span>
            <h1>From the seller's shelf to <span class="prp-accent">your doorstep.</span></h1>
            <p class="prp-lead">Every PickSell order travels with a logistics partner, through its province and station hubs, until a local rider brings it home.</p>
            <form class="prp-track-form" method="GET" action="{{ route('pickrush.track') }}" role="search">
                <label for="heroTrack">Tracking number</label>
                <div class="prp-track-row">
                    <input class="prp-input" id="heroTrack" type="search" name="no" placeholder="e.g. PR2610200483921K" required>
                    <button class="prp-btn" type="submit">Track parcel <svg class="prp-btn-arrow" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></button>
                </div>
            </form>
            <p class="prp-hint">Logistics staff? <a href="{{ route('pickrush.login') }}">Sign in</a> · Run a logistics company? <a href="{{ route('pickrush.register') }}">Register it here</a></p>
        </div>

        <div class="prp-hero-art">
            <div class="prp-scene">
                @include('pickrush.partials.delivery-scene')
            </div>
        </div>
    </section>

    <section class="prp-band" id="how">
        <div class="prp-wrap prp-section">
            <div class="prp-section-head" data-reveal>
                <h2>How a parcel gets to you</h2>
                <p>Four hand-offs, each one scanned, so the buyer and seller always know where the order is.</p>
            </div>
            <div class="prp-steps">
                @foreach([
                    ['01', 'Pickup', 'A rider collects the parcel from the PickSell seller.', 'M3 9l1.5-5h15L21 9M3 9h18v2a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0V9zM5 13v7h14v-7'],
                    ['02', 'Province hub', 'Parcels are sorted and sent on toward the right area.', 'M3 21V9l9-6 9 6v12M9 21v-6h6v6'],
                    ['03', 'Station hub', 'The nearest station prepares the parcel for the last mile.', 'M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8'],
                    ['04', 'Delivered', 'A rider hands it over and the order status updates.', 'M20 6L9 17l-5-5'],
                ] as [$number, $title, $body, $icon])
                    <article class="prp-step" data-reveal style="--reveal-delay: {{ $loop->index * 0.1 }}s">
                        <div class="prp-step-top">
                            <span class="prp-step-icon"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg></span>
                            <span class="prp-step-num">{{ $number }}</span>
                        </div>
                        <h3>{{ $title }}</h3>
                        <p>{{ $body }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="prp-wrap prp-section" id="track">
        <div class="prp-track-card" data-reveal>
            <div>
                <h2>Track your parcel</h2>
                <p>Enter the tracking number from your PickSell order to see every scan, hub and rider update.</p>
            </div>
            <form class="prp-track-form" method="GET" action="{{ route('pickrush.track') }}" role="search">
                <label for="bandTrack">Tracking number</label>
                <div class="prp-track-row">
                    <input class="prp-input prp-input-light" id="bandTrack" type="search" name="no" placeholder="e.g. PR2610200483921K" required>
                    <button class="prp-btn prp-btn-dark" type="submit">Track</button>
                </div>
            </form>
        </div>
    </section>

    <section class="prp-wrap prp-riders" id="partners">
        <div class="prp-riders-copy" data-reveal>
            <h2>Deliver for PickSell</h2>
            <p>Register your logistics company. Once a PickSell administrator approves it, you run your own hubs, staff and riders, set your own rates, and buyers can choose you at checkout.</p>
            <div class="prp-actions">
                <a class="prp-btn" href="{{ route('pickrush.register') }}">Register your company</a>
                <a class="prp-btn-ghost" href="#apps">Get the rider app</a>
            </div>
        </div>
        <ul class="prp-perks" data-reveal style="--reveal-delay: .15s">
            <li><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/></svg>Every company reviewed by a PickSell administrator</li>
            <li><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V9l9-6 9 6v12M9 21v-6h6v6"/></svg>Your own hubs, staff and riders</li>
            <li><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="3" width="12" height="18" rx="2"/><path d="M10 17h4"/></svg>Riders apply to your company in the rider app</li>
        </ul>
    </section>

    {{-- Get the apps: Pickrush (riders) first here, then the PickSell marketplace; scan or tap to download the APK. --}}
    <section class="prp-wrap prp-section prp-apps" id="apps">
        <div class="prp-section-head" data-reveal>
            <h2>Get the apps</h2>
            <p>Scan a code with your phone camera to download the Android app, or tap Download APK on your phone.</p>
        </div>
        <div class="prp-apps-grid">
            @foreach(collect($apps)->sortBy(fn ($app) => $app['key'] === 'pickrush' ? 0 : 1) as $app)
            <article class="prp-app prp-app--{{ $app['key'] }}" data-reveal style="--reveal-delay: {{ $loop->index * .12 }}s">
                <div class="prp-app-qr">
                    {!! $app['qr'] !!}
                    @unless($app['available'])<span class="prp-app-soon">Coming soon</span>@endunless
                </div>
                <div class="prp-app-body">
                    <span class="prp-app-tag">{{ $app['tagline'] }}</span>
                    <h3>{{ $app['name'] }}</h3>
                    <p>{{ $app['description'] }}</p>
                    @if($app['available'])
                        <a class="prp-btn prp-btn-sm" href="{{ $app['url'] }}" download>
                            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11"/><path d="M7 10l5 5 5-5"/><path d="M5 20h14"/></svg>
                            Download APK
                        </a>
                        <small class="prp-app-meta">Android · {{ $app['size'] }}</small>
                    @else
                        <span class="prp-app-meta">Coming soon</span>
                    @endif
                </div>
            </article>
            @endforeach
        </div>
        <p class="prp-apps-hint">Open the downloaded file to install. If Android asks, allow installs from your browser. iPhone apps are not available yet.</p>
    </section>
</main>

@include('pickrush.partials.public-footer')
</body>
</html>
