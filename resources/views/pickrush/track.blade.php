<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Track a Parcel | Pickrush Logistics</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/pickrush-logo.svg') }}">
    @vite('resources/css/pickrush/app.css')
</head>
<body>
<div class="pr-auth" style="align-items:start">
    <div class="pr-auth-card" style="max-width:620px;margin-top:2rem">
        <div class="pr-brand">@include('pickrush.partials.logo-mark', ['class' => 'pr-brand-logo', 'tone' => 'light', 'size' => 32]) Pickrush Logistics</div>
        <h1>Track a parcel</h1>
        <form method="GET" action="{{ route('pickrush.track') }}" class="pr-filters" role="search">
            <input type="search" name="no" value="{{ $number }}" class="pr-input" style="flex:1" placeholder="Tracking number, e.g. PR2610200483921K" aria-label="Tracking number" required>
            <button type="submit" class="pr-btn pr-btn-primary">Track</button>
        </form>

        @if($error)
            <div class="pr-alert pr-alert-bad" role="alert">{{ $error }}</div>
        @endif

        @if($parcel)
            <div class="pr-card" style="margin-bottom:1rem">
                <dl class="pr-dl">
                    <dt>Tracking no.</dt><dd><strong>{{ $parcel->tracking_no }}</strong></dd>
                    <dt>Status</dt><dd><span class="pr-badge pr-badge-brand">{{ $parcel->current_status->label() }}</span></dd>
                    @if($parcel->destHub)<dt>Delivering to</dt><dd>{{ $parcel->destHub->municipality_name }}, {{ $parcel->destHub->province_name }}</dd>@endif
                </dl>
            </div>
            <ol class="pr-timeline">
                @foreach($events as $event)
                    <li @class(['is-current' => $loop->first])>
                        <strong>{{ $event->headline() }}</strong>
                        <time datetime="{{ $event->occurred_at->toIso8601String() }}">{{ $event->occurred_at->format('M d, Y h:i A') }}</time>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</div>
</body>
</html>
