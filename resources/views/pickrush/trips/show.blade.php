@extends('pickrush.layouts.app')
@section('title', 'Trip '.$manifest->manifest_no)

@section('content')
@php
    $counts = $manifest->items->countBy('scan_status');
    $badge = fn ($s) => match ($s) { 'scanned' => 'pr-badge-ok', 'missing' => 'pr-badge-bad', 'extra' => 'pr-badge-warn', default => '' };
@endphp
<div class="pr-page-head">
    <div>
        <h1>Trip {{ $manifest->manifest_no }}</h1>
        <p>{{ $manifest->fromHub->name }} → {{ $manifest->toHub->name }} · <a href="{{ route($prefix.'.index') }}">All trips</a></p>
    </div>
    <span class="pr-badge pr-badge-brand">{{ ucfirst($manifest->status) }}</span>
</div>

@if($outbound && $manifest->status === 'draft')
    @include('pickrush.partials.scan-box', ['action' => route($prefix.'.add', $manifest), 'label' => 'Scan each parcel as it goes on the vehicle', 'button' => 'Add'])
    <div class="pr-actions" style="margin-top:-.5rem;margin-bottom:1rem">
        @if($readyCount)
            <form method="POST" action="{{ route($prefix.'.add-all', $manifest) }}">@csrf
                <button class="pr-btn pr-btn-outline" type="submit">Add all {{ $readyCount }} ready parcels</button>
            </form>
        @endif
    </div>
@elseif(! $outbound && in_array($manifest->status, ['dispatched', 'received'], true))
    @include('pickrush.partials.scan-box', ['action' => route($prefix.'.receive', $manifest), 'label' => 'Scan each parcel off the vehicle', 'button' => 'Receive'])
@endif

<div class="pr-grid">
    <div class="pr-card"><div class="pr-stat-num">{{ $manifest->items->count() }}</div><div class="pr-stat-label">On the list</div></div>
    @unless($manifest->status === 'draft')
        <div class="pr-card"><div class="pr-stat-num">{{ $counts['scanned'] ?? 0 }}</div><div class="pr-stat-label">Received</div></div>
        <div class="pr-card"><div class="pr-stat-num">{{ ($counts['expected'] ?? 0) + ($counts['missing'] ?? 0) }}</div><div class="pr-stat-label">{{ $manifest->status === 'closed' ? 'Missing' : 'Not scanned yet' }}</div></div>
        <div class="pr-card"><div class="pr-stat-num">{{ $counts['extra'] ?? 0 }}</div><div class="pr-stat-label">Extra (not on the list)</div></div>
    @endunless
</div>

<div class="pr-table-wrap">
    <table class="pr-table">
        <thead><tr><th>Tracking no.</th><th>Deliver to</th><th>Check</th>@if($outbound && $manifest->status === 'draft')<th></th>@endif</tr></thead>
        <tbody>
        @forelse($manifest->items as $item)
            <tr>
                <td><a href="{{ route('pickrush.parcels.show', $item->parcel) }}">{{ $item->parcel->tracking_no }}</a>@if($item->parcel->is_return) <span class="pr-badge pr-badge-warn">Return</span>@endif</td>
                <td>{{ $item->parcel->is_return ? $item->parcel->shipper_name : $item->parcel->consignee_name }}</td>
                <td><span class="pr-badge {{ $badge($item->scan_status) }}">{{ ucfirst($item->scan_status) }}</span></td>
                @if($outbound && $manifest->status === 'draft')
                    <td><form method="POST" action="{{ route($prefix.'.remove', [$manifest, $item]) }}">@csrf @method('DELETE')
                        <button class="pr-btn pr-btn-danger pr-btn-sm" type="submit">Remove</button></form></td>
                @endif
            </tr>
        @empty
            <tr><td colspan="4" class="pr-muted">No parcels yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($outbound && $manifest->status === 'draft')
    <form method="POST" action="{{ route($prefix.'.dispatch', $manifest) }}" class="pr-sticky-bar">
        @csrf
        <strong>Dispatch</strong>
        @if(! empty($vehicles) && $vehicles->isNotEmpty())
            <select class="pr-select" aria-label="Fleet vehicle" onchange="const o=this.selectedOptions[0];this.form.vehicle_plate.value=o.dataset.plate||'';this.form.driver_name.value=o.dataset.driver||''">
                <option value="">Pick from fleet…</option>
                @foreach($vehicles as $vehicle)<option data-plate="{{ $vehicle->plate_no }}" data-driver="{{ $vehicle->driver_name }}">{{ $vehicle->plate_no }} · {{ $vehicle->vehicle_type }}{{ $vehicle->driver_name ? ' · '.$vehicle->driver_name : '' }}</option>@endforeach
            </select>
        @endif
        <input name="vehicle_plate" class="pr-input" placeholder="Vehicle plate" value="{{ old('vehicle_plate') }}" required aria-label="Vehicle plate">
        <input name="driver_name" class="pr-input" placeholder="Driver name" value="{{ old('driver_name') }}" required aria-label="Driver name">
        <button type="submit" class="pr-btn pr-btn-primary" onclick="return confirm('Dispatch this trip with {{ $manifest->items->count() }} parcels?')">Dispatch trip</button>
    </form>
@elseif($manifest->status !== 'draft')
    <section class="pr-card" style="margin-top:1rem">
        <dl class="pr-dl">
            <dt>Vehicle</dt><dd>{{ $manifest->vehicle_plate }} · {{ $manifest->driver_name }}</dd>
            <dt>Dispatched</dt><dd>{{ $manifest->dispatched_at?->format('M d, Y h:i A') }}</dd>
            <dt>Received</dt><dd>{{ $manifest->received_at?->format('M d, Y h:i A') ?? '—' }}</dd>
            <dt>Closed</dt><dd>{{ $manifest->closed_at?->format('M d, Y h:i A') ?? '—' }}</dd>
        </dl>
        @if(! $outbound && in_array($manifest->status, ['dispatched', 'received'], true))
            <form method="POST" action="{{ route($prefix.'.close', $manifest) }}" class="pr-actions">
                @csrf
                <button type="submit" class="pr-btn pr-btn-outline" onclick="return confirm('Close the check? Unscanned parcels will be marked missing.')">Close check</button>
            </form>
        @endif
    </section>
@endif
@endsection
