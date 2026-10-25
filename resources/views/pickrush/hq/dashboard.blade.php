@extends('pickrush.layouts.app')
@section('title', 'HQ dashboard')

@section('content')
@php
    $peso = fn ($n) => '₱'.number_format((float) $n, 2);
    $cashRows = ['With riders' => $cash['with_riders'], 'Counted at stations' => $cash['at_stations'], 'Sent, awaiting confirmation' => $cash['submitted_unconfirmed']];
    $cashMax = max(1, ...array_map('floatval', array_values($cashRows)));
    $firstName = explode(' ', trim(\App\Pickrush\Support\Actor::staff()->full_name))[0];
@endphp
<div class="pr-page-head">
    <div>
        <span class="pr-eyebrow">Welcome back, {{ $firstName }}</span>
        <h1>Pickrush network</h1>
        <p>{{ $network['provinces'] }} province hubs · {{ number_format($network['stations']) }} station hubs</p>
    </div>
    <div class="pr-head-tools">
        <form method="GET" action="{{ route('pickrush.parcels.index') }}" class="pr-search" role="search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="search" name="q" class="pr-input" placeholder="Find a parcel by tracking no." aria-label="Find a parcel">
        </form>
        @include('pickrush.hq.partials.period-tabs')
    </div>
</div>

@if($alertCount)
    <a href="{{ route('pickrush.hq.alerts') }}" class="pr-attention">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
        <strong>{{ $alertCount }} alerts need attention</strong>
        <span class="pr-attention-cta">Review alerts →</span>
    </a>
@endif

<div class="pr-grid pr-kpis">
    <div class="pr-card"><div class="pr-stat-label">Parcels booked</div><div class="pr-stat-num">{{ number_format($summary['booked']) }}</div></div>
    <div class="pr-card"><div class="pr-stat-label">Delivered</div><div class="pr-stat-num">{{ number_format($summary['delivered']) }}</div></div>
    <div class="pr-card"><div class="pr-stat-label">In transit now</div><div class="pr-stat-num">{{ number_format($summary['in_transit']) }}</div><div class="pr-stat-note">{{ number_format($summary['in_network']) }} in the network</div></div>
    <div class="pr-card"><div class="pr-stat-label">Failed attempts</div><div class="pr-stat-num">{{ number_format($summary['failed_attempts']) }}</div></div>
    <div class="pr-card"><div class="pr-stat-label">Returned to sellers</div><div class="pr-stat-num">{{ number_format($summary['returned']) }}</div></div>
</div>

<div class="pr-two pr-section">
    <section class="pr-card pr-perf">
        <div class="pr-card-head"><h2>Delivery performance</h2><span class="pr-muted">Target {{ $sla }}%</span></div>
        <div class="pr-perf-hero">
            <span class="pr-perf-num">{{ $summary['success_rate'] !== null ? $summary['success_rate'].'%' : '—' }}</span>
            <span class="pr-muted">success rate</span>
        </div>
        @if($summary['success_rate'] !== null)
            <div class="pr-bar pr-bar-lg" role="img" aria-label="{{ $summary['success_rate'] }}% against a {{ $sla }}% target">
                <span style="width: {{ min(100, $summary['success_rate']) }}%"></span><i class="pr-bar-target" style="left: {{ $sla }}%"></i>
            </div>
        @endif
        <div class="pr-mini-stats">
            <div><b>{{ $summary['first_attempt_rate'] !== null ? $summary['first_attempt_rate'].'%' : '—' }}</b><span>Delivered on the first attempt</span></div>
            <div><b>{{ $summary['avg_delivery_hours'] !== null ? $summary['avg_delivery_hours'].' h' : '—' }}</b><span>Average booking-to-delivery time</span></div>
            <div><b>{{ $peso($summary['cod_collected']) }}</b><span>COD collected · {{ $peso($summary['cod_remitted']) }} confirmed at HQ</span></div>
        </div>
    </section>

    <section class="pr-card pr-cash">
        <div class="pr-card-head"><h2>Cash not yet at HQ</h2><a href="{{ route('pickrush.hq.finance') }}">Finance</a></div>
        <div class="pr-perf-num">{{ $peso(array_sum(array_map('floatval', $cashRows))) }}</div>
        @foreach($cashRows as $label => $amount)
            <div class="pr-cash-row">
                <div><span class="pr-muted">{{ $label }}</span><strong>{{ $peso($amount) }}</strong></div>
                <div class="pr-bar"><span style="width: {{ round((float) $amount / $cashMax * 100) }}%"></span></div>
            </div>
        @endforeach
    </section>
</div>

<section class="pr-section">
    <div class="pr-card-head"><h2>Hub ranking</h2>@if($ranking->count() > 25)<a href="{{ route('pickrush.hq.reports', ['period' => $period]) }}">See all {{ $ranking->count() }} stations</a>@endif</div>
    @include('pickrush.hq.partials.ranking', ['ranking' => $ranking->take(25)])
</section>
@endsection
