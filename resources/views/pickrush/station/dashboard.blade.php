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

<div class="pr-grid">
    <a class="pr-card" href="{{ route('pickrush.station.receiving') }}" style="text-decoration:none;color:inherit"><div class="pr-stat-num">{{ number_format($inbound) }}</div><div class="pr-stat-label">On the way here</div></a>
    <div class="pr-card"><div class="pr-stat-num">{{ number_format($arrivedToday) }}</div><div class="pr-stat-label">Arrived today</div></div>
    <a class="pr-card" href="{{ route('pickrush.station.dispatch') }}" style="text-decoration:none;color:inherit"><div class="pr-stat-num">{{ number_format($waitingDispatch) }}</div><div class="pr-stat-label">Waiting for a rider</div></a>
    <div class="pr-card"><div class="pr-stat-num">{{ number_format($outForDelivery) }}</div><div class="pr-stat-label">Out for delivery</div></div>
    <div class="pr-card"><div class="pr-stat-num">{{ number_format($deliveredToday) }}</div><div class="pr-stat-label">Delivered today</div></div>
    <a class="pr-card" href="{{ route('pickrush.station.failed') }}" style="text-decoration:none;color:inherit"><div class="pr-stat-num">{{ number_format($failedToday) }}</div><div class="pr-stat-label">Failed today</div></a>
    <a class="pr-card" href="{{ route('pickrush.station.dispatch', ['view' => 'pickups']) }}" style="text-decoration:none;color:inherit"><div class="pr-stat-num">{{ number_format($waitingPickup) }}</div><div class="pr-stat-label">Seller pickups waiting</div></a>
</div>

<div class="pr-grid">
    <div class="pr-card"><div class="pr-stat-num">₱{{ number_format($codOnRoad, 2) }}</div><div class="pr-stat-label">COD on the road</div></div>
    <a class="pr-card" href="{{ route('pickrush.station.cash') }}" style="text-decoration:none;color:inherit"><div class="pr-stat-num">₱{{ number_format($cashOnHand, 2) }}</div><div class="pr-stat-label">Cash at the station, not yet remitted</div></a>
    <a class="pr-card" href="{{ route('pickrush.station.riders.index') }}" style="text-decoration:none;color:inherit"><div class="pr-stat-num">{{ $ridersOnShift }} / {{ $ridersActive }}</div><div class="pr-stat-label">Riders on shift / active</div></a>
    <div class="pr-card"><div class="pr-stat-num">{{ number_format($needsAddressFix) }}</div><div class="pr-stat-label">Parcels needing an address fix</div></div>
</div>
@endsection
