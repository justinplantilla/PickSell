@extends('pickrush.layouts.app')
@section('title', 'HQ dashboard')

@section('content')
@php $peso = fn ($n) => '₱'.number_format((float) $n, 2); @endphp
<div class="pr-page-head">
    <div>
        <h1>Pickrush network</h1>
        <p>{{ $network['provinces'] }} province hubs · {{ number_format($network['stations']) }} station hubs</p>
    </div>
    @if($alertCount)<a href="{{ route('pickrush.hq.alerts') }}" class="pr-btn pr-btn-primary">{{ $alertCount }} alerts need attention</a>@endif
</div>

@include('pickrush.hq.partials.period-tabs')

<div class="pr-grid">
    <div class="pr-card"><div class="pr-stat-num">{{ number_format($summary['booked']) }}</div><div class="pr-stat-label">Parcels booked</div></div>
    <div class="pr-card"><div class="pr-stat-num">{{ number_format($summary['delivered']) }}</div><div class="pr-stat-label">Delivered</div></div>
    <div class="pr-card"><div class="pr-stat-num">{{ number_format($summary['in_transit']) }}</div><div class="pr-stat-label">In transit now ({{ number_format($summary['in_network']) }} in the network)</div></div>
    <div class="pr-card"><div class="pr-stat-num">{{ number_format($summary['failed_attempts']) }}</div><div class="pr-stat-label">Failed attempts</div></div>
    <div class="pr-card"><div class="pr-stat-num">{{ number_format($summary['returned']) }}</div><div class="pr-stat-label">Returned to sellers</div></div>
</div>

<div class="pr-grid">
    <div class="pr-card"><div class="pr-stat-num">{{ $summary['success_rate'] !== null ? $summary['success_rate'].'%' : '—' }}</div><div class="pr-stat-label">Success rate (target {{ $sla }}%)</div></div>
    <div class="pr-card"><div class="pr-stat-num">{{ $summary['first_attempt_rate'] !== null ? $summary['first_attempt_rate'].'%' : '—' }}</div><div class="pr-stat-label">Delivered on the first attempt</div></div>
    <div class="pr-card"><div class="pr-stat-num">{{ $summary['avg_delivery_hours'] !== null ? $summary['avg_delivery_hours'].' h' : '—' }}</div><div class="pr-stat-label">Average booking-to-delivery time</div></div>
    <div class="pr-card"><div class="pr-stat-num">{{ $peso($summary['cod_collected']) }}</div><div class="pr-stat-label">COD collected · {{ $peso($summary['cod_remitted']) }} confirmed at HQ</div></div>
</div>

<section class="pr-card pr-section">
    <h2>Cash not yet at HQ</h2>
    <dl class="pr-dl">
        <dt>With riders</dt><dd>{{ $peso($cash['with_riders']) }}</dd>
        <dt>Counted at stations</dt><dd>{{ $peso($cash['at_stations']) }}</dd>
        <dt>Sent, awaiting confirmation</dt><dd>{{ $peso($cash['submitted_unconfirmed']) }} · <a href="{{ route('pickrush.hq.finance') }}">Finance</a></dd>
    </dl>
</section>

<section class="pr-section">
    <h2>Hub ranking</h2>
    @include('pickrush.hq.partials.ranking', ['ranking' => $ranking->take(25)])
    @if($ranking->count() > 25)<p class="pr-muted"><a href="{{ route('pickrush.hq.reports', ['period' => $period]) }}">See all {{ $ranking->count() }} stations</a></p>@endif
</section>
@endsection
