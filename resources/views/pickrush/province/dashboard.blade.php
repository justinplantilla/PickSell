@extends('pickrush.layouts.app')
@section('title', 'Sorting center')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>{{ $hub->name }}</h1>
        <p>Sorting center for {{ $hub->province_name }} · {{ $hub->childStations()->count() }} stations</p>
    </div>
    <div class="pr-actions" style="margin:0">
        <a href="{{ route('pickrush.province.inbound') }}" class="pr-btn pr-btn-primary">Receive</a>
        <a href="{{ route('pickrush.province.sorting') }}" class="pr-btn pr-btn-outline">Sort</a>
        <a href="{{ route('pickrush.province.trips.index') }}" class="pr-btn pr-btn-outline">Outbound</a>
    </div>
</div>

@if($gaps)
    <div class="pr-alert pr-alert-warn" role="status"><strong>Not fully set up:</strong> {{ implode(', ', $gaps) }}</div>
@endif

{{-- Parcels in the order they move through the center, then what needs a person, then volume. --}}
<section class="pr-card pr-flow-card">
    <div class="pr-card-head"><h2>Parcel flow</h2></div>
    <ol class="pr-flow pr-flow-3">
        <li><a class="pr-flow-step" href="{{ route('pickrush.province.inbound') }}"><span class="pr-stat-num">{{ $inboundTrips }}</span><span class="pr-stat-label">Trips on the road to here ({{ $onTheWay }} parcels)</span></a></li>
        <li><a class="pr-flow-step" href="{{ route('pickrush.province.inventory') }}"><span class="pr-stat-num">{{ number_format($onHand) }}</span><span class="pr-stat-label">On hand</span></a></li>
        <li><a class="pr-flow-step" href="{{ route('pickrush.province.sorting') }}"><span class="pr-stat-num">{{ number_format($unsorted) }}</span><span class="pr-stat-label">Not sorted yet</span></a></li>
    </ol>
    <div class="pr-flow-side">
        <a @class(['pr-flow-step', 'is-warn' => $aging > 0]) href="{{ route('pickrush.province.inventory', ['aging' => 1]) }}"><span class="pr-stat-num">{{ number_format($aging) }}</span><span class="pr-stat-label">Aging (over {{ \App\Pickrush\Services\Province\Inventory::agingHours() }}h)</span></a>
        <a @class(['pr-flow-step', 'is-warn' => $openExceptions > 0]) href="{{ route('pickrush.exceptions.index') }}"><span class="pr-stat-num">{{ $openExceptions }}</span><span class="pr-stat-label">Open exceptions ({{ $onHold }} on hold)</span></a>
    </div>
</section>

<section class="pr-section">
    <h2>Volume</h2>
    <div class="pr-grid pr-kpis pr-money">
        <div class="pr-card">
            <span class="pr-kpi-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
            <div class="pr-stat-label">Today</div>
            <div class="pr-volume"><span><b>{{ number_format($inToday) }}</b> in</span><span><b>{{ number_format($outToday) }}</b> out</span><span><b>{{ $tripsToday }}</b> trips dispatched</span></div>
        </div>
        <div class="pr-card">
            <span class="pr-kpi-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18M7 15l4-4 3 3 5-6"/></svg></span>
            <div class="pr-stat-label">Last 7 days</div>
            <div class="pr-volume"><span><b>{{ number_format($in7d) }}</b> in</span><span><b>{{ number_format($out7d) }}</b> out</span></div>
        </div>
        <div class="pr-card">
            <span class="pr-kpi-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h12M6 22h12M7 2v4l5 6-5 6v4M17 2v4l-5 6 5 6v4"/></svg></span>
            <div class="pr-stat-label">Average dwell time</div>
            <div class="pr-stat-num">{{ $avgDwell !== null ? $avgDwell.' hours' : 'Not enough data yet' }}</div>
        </div>
    </div>
</section>
@endsection
