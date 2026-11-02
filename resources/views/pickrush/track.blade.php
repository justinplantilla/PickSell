<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Track a Parcel | Pickrush Logistics</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/pickrush-logo.svg') }}">
    @vite('resources/css/pickrush/public.css')
</head>
<body class="prp">
<header class="prp-header">
    <div class="prp-wrap prp-header-inner">
        <a class="prp-brand" href="{{ route('pickrush.dashboard') }}">
            @include('pickrush.partials.logo-mark', ['class' => 'prp-mark', 'size' => 40])
            <span>Pickrush <span class="prp-accent">Logistics</span></span>
        </a>
        <nav class="prp-nav" aria-label="Main">
            <a href="{{ route('pickrush.dashboard') }}#how">How it works</a>
            <a href="{{ route('pickrush.dashboard') }}#riders">Riders</a>
            <a class="prp-btn prp-btn-sm" href="{{ route('pickrush.login') }}">Staff sign-in <svg class="prp-btn-arrow" aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></a>
        </nav>
    </div>
</header>

<main class="prp-wrap prt">
    <section class="prt-head">
        <span class="prp-pill"><span aria-hidden="true"></span>Parcel tracking</span>
        <h1>Where's my <span class="prp-accent">parcel?</span></h1>
        <p class="prp-lead">Enter the tracking number on your waybill to see every scan, from pickup to your door.</p>
        <form class="prp-track-form" method="GET" action="{{ route('pickrush.track') }}" role="search">
            <label for="trackNo">Tracking number</label>
            <div class="prp-track-row">
                <input class="prp-input" id="trackNo" type="search" name="no" value="{{ $number }}" placeholder="e.g. PR2610200483921K" required>
                <button class="prp-btn" type="submit">Track parcel <svg class="prp-btn-arrow" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg></button>
            </div>
        </form>
    </section>

    @if($error)
        <div class="prt-alert" role="alert">{{ $error }}</div>
    @endif

    @if($parcel)
        <section class="prt-result" aria-label="Tracking result">
            <div class="prt-summary">
                <div>
                    <span class="prt-label">Tracking no.</span>
                    <strong class="prt-number">{{ $parcel->tracking_no }}</strong>
                </div>
                <div>
                    <span class="prt-label">Status</span>
                    <span class="prt-status">{{ $parcel->current_status->label() }}</span>
                </div>
                @if($parcel->destHub)
                    <div>
                        <span class="prt-label">Delivering to</span>
                        <strong>{{ $parcel->destHub->municipality_name }}, {{ $parcel->destHub->province_name }}</strong>
                    </div>
                @endif
            </div>

            <ol class="prt-timeline">
                @foreach($events as $event)
                    <li @class(['is-current' => $loop->first])>
                        <strong>{{ $event->headline() }}</strong>
                        <time datetime="{{ $event->occurred_at->toIso8601String() }}">{{ $event->occurred_at->format('M d, Y h:i A') }}</time>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif
</main>

<footer class="prp-footer">
    <div class="prp-wrap prp-footer-inner">
        <span class="prp-footer-brand">@include('pickrush.partials.logo-mark', ['class' => 'prp-mark', 'size' => 28])Pickrush Logistics · Delivery partner of PickSell</span>
        <nav aria-label="Footer">
            <a href="{{ route('pickrush.login') }}">Staff sign-in</a>
            <a href="{{ config('app.url') }}">PickSell shop</a>
        </nav>
        <span>&copy; {{ now()->year }} Pickrush Logistics</span>
    </div>
</footer>
</body>
</html>
