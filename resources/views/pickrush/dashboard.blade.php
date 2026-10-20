@extends('pickrush.layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Good day, {{ \Illuminate\Support\Str::before($staff->full_name, ' ') }}</h1>
        <p>{{ $roleLabel }} · {{ $hub?->name ?? 'Pickrush HQ' }}</p>
    </div>
</div>

@if($readinessGaps)
    <div class="pr-alert pr-alert-warn" role="status">
        <strong>{{ $hub->name }} is not fully set up yet.</strong>
        <ul>@foreach($readinessGaps as $gap)<li>{{ $gap }}</li>@endforeach</ul>
    </div>
@endif

<div class="pr-grid">
    <div class="pr-card"><div class="pr-stat-num">{{ number_format($parcelTotal) }}</div><div class="pr-stat-label">{{ $hub ? 'Parcels linked to this hub' : 'Parcels in the network' }}</div></div>
    <div class="pr-card"><div class="pr-stat-num">{{ number_format($inTransit) }}</div><div class="pr-stat-label">In transit between hubs</div></div>
    <div class="pr-card"><div class="pr-stat-num">{{ number_format($delivered) }}</div><div class="pr-stat-label">Delivered</div></div>
    <div class="pr-card"><div class="pr-stat-num">{{ number_format($failed) }}</div><div class="pr-stat-label">Failed delivery attempts</div></div>
    <div class="pr-card"><div class="pr-stat-num">{{ number_format($needsAddressFix) }}</div><div class="pr-stat-label">Need an address fix</div></div>
</div>

@if($network)
    <section class="pr-card">
        <h2>Network</h2>
        <dl class="pr-dl">
            <dt>Province hubs</dt><dd>{{ number_format($network['provinces']) }}</dd>
            <dt>Station hubs</dt><dd>{{ number_format($network['stations']) }}</dd>
            <dt>Hubs without a real manager</dt>
            <dd>{{ number_format($network['withoutManager']) }} · <a href="{{ route('pickrush.hubs.index', ['readiness' => 'no_manager']) }}">View list</a></dd>
        </dl>
    </section>
@endif
@endsection
