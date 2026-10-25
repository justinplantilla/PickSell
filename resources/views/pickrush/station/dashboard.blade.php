@extends('pickrush.layouts.app')
@section('title', 'Station dashboard')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>{{ $station->name }}</h1>
        <p>{{ $station->municipality_name }}, {{ $station->province_name }} · sorts through {{ $station->parent?->name }}</p>
    </div>
    <div class="pr-actions" style="margin:0">
        <a href="{{ route('pickrush.station.receiving') }}" class="pr-btn pr-btn-primary">Receive parcels</a>
        <a href="{{ route('pickrush.station.dispatch') }}" class="pr-btn pr-btn-outline">Dispatch</a>
    </div>
</div>

@if($gaps)
    <div class="pr-alert pr-alert-warn" role="status">
        <strong>This station is not fully set up yet.</strong>
        <ul>@foreach($gaps as $gap)<li>{{ $gap }}</li>@endforeach</ul>
    </div>
@endif

@foreach($riderReports as $report)
    <div class="pr-alert pr-alert-bad pr-report" role="alert">
        <span class="pr-report-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg></span>
        <div class="pr-report-body">
            <strong>{{ $report->rider->full_name }} reported: {{ $report->label() }}</strong>
            <div class="pr-report-meta">
                @if($report->batch)<span>batch #{{ $report->batch->batch_no }}</span>@endif
                <span>{{ $report->created_at->diffForHumans() }}</span>
                @if($report->rider->mobile_no)<a href="tel:{{ $report->rider->mobile_no }}">{{ $report->rider->mobile_no }}</a>@endif
                @if($report->lat !== null)<a href="https://www.google.com/maps/search/?api=1&amp;query={{ $report->lat }},{{ $report->lng }}" target="_blank" rel="noopener">Rider's location</a>@endif
            </div>
            @if($report->note)<div class="pr-report-note">{{ $report->note }}</div>@endif
        </div>
        <form method="POST" action="{{ route('pickrush.station.rider-reports.resolve', $report) }}" class="pr-inline">
            @csrf
            <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm">Mark handled</button>
        </form>
    </div>
@endforeach

@if($riderApplications)
    <div class="pr-alert pr-alert-warn" role="status">
        <strong>{{ $riderApplications }} rider {{ \Illuminate\Support\Str::plural('application', $riderApplications) }} to review.</strong>
        <a href="{{ route('pickrush.station.riders.index', ['status' => 'pending']) }}">Review applications</a>
    </div>
@endif

{{-- Parcels in the order they move through the station, then the two side queues that need a person. --}}
<section class="pr-card pr-flow-card">
    <div class="pr-card-head"><h2>Parcel flow</h2></div>
    <ol class="pr-flow">
        <li><a class="pr-flow-step" href="{{ route('pickrush.station.receiving') }}"><span class="pr-stat-num">{{ number_format($inbound) }}</span><span class="pr-stat-label">On the way here</span></a></li>
        <li><div class="pr-flow-step"><span class="pr-stat-num">{{ number_format($arrivedToday) }}</span><span class="pr-stat-label">Arrived today</span></div></li>
        <li><a class="pr-flow-step" href="{{ route('pickrush.station.dispatch') }}"><span class="pr-stat-num">{{ number_format($waitingDispatch) }}</span><span class="pr-stat-label">Waiting for a rider</span></a></li>
        <li><div class="pr-flow-step"><span class="pr-stat-num">{{ number_format($outForDelivery) }}</span><span class="pr-stat-label">Out for delivery</span></div></li>
        <li><div class="pr-flow-step is-done"><span class="pr-stat-num">{{ number_format($deliveredToday) }}</span><span class="pr-stat-label">Delivered today</span></div></li>
    </ol>
    <div class="pr-flow-side">
        <a @class(['pr-flow-step', 'is-warn' => $failedToday > 0]) href="{{ route('pickrush.station.failed') }}"><span class="pr-stat-num">{{ number_format($failedToday) }}</span><span class="pr-stat-label">Failed today</span></a>
        <a @class(['pr-flow-step', 'is-warn' => $waitingPickup > 0]) href="{{ route('pickrush.station.dispatch', ['view' => 'pickups']) }}"><span class="pr-stat-num">{{ number_format($waitingPickup) }}</span><span class="pr-stat-label">Seller pickups waiting</span></a>
    </div>
</section>

<div class="pr-grid pr-kpis pr-money">
    <div class="pr-card">
        <span class="pr-kpi-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h11v10H3zM14 10h4l3 3v3h-7M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/></svg></span>
        <div class="pr-stat-num">₱{{ number_format($codOnRoad, 2) }}</div><div class="pr-stat-label">COD on the road</div>
    </div>
    <a class="pr-card" href="{{ route('pickrush.station.cash') }}">
        <span class="pr-kpi-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 6h20v13H2zM12 10a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z"/></svg></span>
        <div class="pr-stat-num">₱{{ number_format($cashOnHand, 2) }}</div><div class="pr-stat-label">Cash at the station, not yet remitted</div>
    </a>
    <a class="pr-card" href="{{ route('pickrush.station.riders.index') }}">
        <span class="pr-kpi-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM2 21a7 7 0 0 1 14 0M17 4a4 4 0 0 1 0 8M19 14a6 6 0 0 1 3 7"/></svg></span>
        <div class="pr-stat-num">{{ $ridersOnShift }} / {{ $ridersActive }}</div><div class="pr-stat-label">Riders on shift / active</div>
    </a>
    <div class="pr-card">
        <span class="pr-kpi-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11zM12 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg></span>
        <div class="pr-stat-num">{{ number_format($needsAddressFix) }}</div><div class="pr-stat-label">Parcels needing an address fix</div>
    </div>
</div>
@endsection
