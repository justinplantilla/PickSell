<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pickrush Logistics | PickSell's delivery network</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/pickrush-logo.svg') }}">
    <meta name="description" content="Pickrush moves every PickSell order through province and station hubs, then a local rider brings it home.">
    @vite(['resources/css/pickrush/public.css', 'resources/js/pickrush/landing.js'])
</head>
<body class="prp">
<header class="prp-header" data-header>
    <div class="prp-wrap prp-header-inner">
        <a class="prp-brand" href="{{ route('pickrush.dashboard') }}">
            @include('pickrush.partials.logo-mark', ['class' => 'prp-mark', 'size' => 40])
            <span>Pickrush <span class="prp-accent">Logistics</span></span>
        </a>
        <nav class="prp-nav" aria-label="Main">
            <a href="#how">How it works</a>
            <a href="#track">Track a parcel</a>
            <a href="#riders">Riders</a>
            <a class="prp-btn prp-btn-sm" href="{{ route('pickrush.login') }}">Staff sign-in <svg class="prp-btn-arrow" aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></a>
        </nav>
    </div>
</header>

<main>
    <section class="prp-wrap prp-hero">
        <div class="prp-hero-copy">
            <span class="prp-pill"><span aria-hidden="true"></span>PickSell's delivery network</span>
            <h1>From the seller's shelf to <span class="prp-accent">your doorstep.</span></h1>
            <p class="prp-lead">Pickrush moves every PickSell order through our province and station hubs, then a local rider brings it home.</p>
            <form class="prp-track-form" method="GET" action="{{ route('pickrush.track') }}" role="search">
                <label for="heroTrack">Tracking number</label>
                <div class="prp-track-row">
                    <input class="prp-input" id="heroTrack" type="search" name="no" placeholder="e.g. PR2610200483921K" required>
                    <button class="prp-btn" type="submit">Track parcel <svg class="prp-btn-arrow" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></button>
                </div>
            </form>
            <p class="prp-hint">Hub staff? <a href="{{ route('pickrush.login') }}">Sign in to the staff portal</a></p>
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

    <section class="prp-wrap prp-riders" id="riders">
        <div class="prp-riders-copy" data-reveal>
            <h2>Ride with Pickrush</h2>
            <p>Apply in the Pickrush rider app. Your station manager reviews your application before your first trip.</p>
            <div class="prp-actions">
                @if(config('pickrush.rider_app_url'))
                    <a class="prp-btn" href="{{ config('pickrush.rider_app_url') }}" rel="noopener">Get the rider app</a>
                @endif
                <a class="prp-btn-ghost" href="#how">See how it works</a>
            </div>
        </div>
        <ul class="prp-perks" data-reveal style="--reveal-delay: .15s">
            <li><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h10"/></svg>Trips assigned by your station hub</li>
            <li><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="3" width="12" height="18" rx="2"/><path d="M10 17h4"/></svg>Scan and confirm deliveries in the app</li>
            <li><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/></svg>Every rider reviewed by a station manager</li>
        </ul>
    </section>
</main>

<footer class="prp-footer">
    <div class="prp-wrap prp-footer-inner">
        <span class="prp-footer-brand">@include('pickrush.partials.logo-mark', ['class' => 'prp-mark', 'size' => 28])Pickrush Logistics · Delivery partner of PickSell</span>
        <nav aria-label="Footer">
            <a href="#track">Track a parcel</a>
            <a href="{{ route('pickrush.login') }}">Staff sign-in</a>
            <a href="{{ config('app.url') }}">PickSell shop</a>
        </nav>
        <span>&copy; {{ now()->year }} Pickrush Logistics</span>
    </div>
</footer>
</body>
</html>
