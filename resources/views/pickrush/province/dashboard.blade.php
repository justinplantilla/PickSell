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

<div class="pr-grid">
    <a class="pr-card" href="{{ route('pickrush.province.inbound') }}" style="text-decoration:none;color:inherit"><div class="pr-stat-num">{{ $inboundTrips }}</div><div class="pr-stat-label">Trips on the road to here ({{ $onTheWay }} parcels)</div></a>
    <a class="pr-card" href="{{ route('pickrush.province.inventory') }}" style="text-decoration:none;color:inherit"><div class="pr-stat-num">{{ number_format($onHand) }}</div><div class="pr-stat-label">On hand</div></a>
    <a class="pr-card" href="{{ route('pickrush.province.sorting') }}" style="text-decoration:none;color:inherit"><div class="pr-stat-num">{{ number_format($unsorted) }}</div><div class="pr-stat-label">Not sorted yet</div></a>
    <a class="pr-card" href="{{ route('pickrush.province.inventory', ['aging' => 1]) }}" style="text-decoration:none;color:inherit"><div class="pr-stat-num">{{ number_format($aging) }}</div><div class="pr-stat-label">Aging (over {{ \App\Pickrush\Services\Province\Inventory::AGING_HOURS }}h)</div></a>
    <a class="pr-card" href="{{ route('pickrush.exceptions.index') }}" style="text-decoration:none;color:inherit"><div class="pr-stat-num">{{ $openExceptions }}</div><div class="pr-stat-label">Open exceptions ({{ $onHold }} on hold)</div></a>
</div>

<section class="pr-card">
    <h2>Volume</h2>
    <dl class="pr-dl">
        <dt>Today</dt><dd>{{ number_format($inToday) }} in · {{ number_format($outToday) }} out · {{ $tripsToday }} trips dispatched</dd>
        <dt>Last 7 days</dt><dd>{{ number_format($in7d) }} in · {{ number_format($out7d) }} out</dd>
        <dt>Average dwell time</dt><dd>{{ $avgDwell !== null ? $avgDwell.' hours' : 'Not enough data yet' }}</dd>
    </dl>
</section>
@endsection
