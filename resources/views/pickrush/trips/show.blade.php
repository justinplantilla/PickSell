@extends('pickrush.layouts.app')
@section('title', 'Trip '.$manifest->manifest_no)

@section('content')
@php
    $counts = $manifest->items->countBy('scan_status');
    $badge = fn ($s) => match ($s) { 'scanned' => 'pr-badge-ok', 'missing' => 'pr-badge-bad', 'extra' => 'pr-badge-warn', default => '' };
    $arriving = ! $outbound && in_array($manifest->status, ['dispatched', 'received'], true);
@endphp
<div class="pr-page-head">
    <div>
        <h1>Trip {{ $manifest->manifest_no }}</h1>
        <p>{{ $manifest->fromHub->name }} → {{ $manifest->toHub->name }} · <a href="{{ route($prefix.'.index') }}">All trips</a></p>
        @if($manifest->route && $outbound)
            <p>Stop {{ $manifest->stop_order }} on route <a href="{{ route('pickrush.province.routes.show', $manifest->route) }}">{{ $manifest->route->route_no }}</a>@if($manifest->status === 'draft'): load its parcels here, then dispatch the whole route from the route page.@endif</p>
        @elseif($manifest->route)
            <p>Stop {{ $manifest->stop_order }} of {{ $manifest->route->stops->count() }} on route {{ $manifest->route->route_no }} · {{ $manifest->route->position() }}</p>
        @endif
    </div>
    <div>
        <a href="{{ route($prefix.'.print', $manifest) }}" target="_blank" rel="noopener" class="pr-btn pr-btn-outline pr-btn-sm">Print manifest</a>
        <span class="pr-badge pr-badge-brand">{{ ucfirst($manifest->status) }}</span>
        @if($manifest->isOverdue())<span class="pr-badge pr-badge-bad">Overdue</span>@endif
        @if($outbound && $manifest->status === 'draft' && ! $manifest->route_id)
            <form method="POST" action="{{ route($prefix.'.cancel', $manifest) }}" style="display:inline">
                @csrf @method('DELETE')
                <button type="submit" class="pr-btn pr-btn-danger pr-btn-sm" data-confirm="Cancel this trip? Its {{ $manifest->items->count() }} parcels go back to ready to leave.">Cancel trip</button>
            </form>
        @endif
    </div>
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
@elseif($arriving)
    @unless($manifest->arrived_at)
        {{-- Stops the overdue clock when unloading starts later; the first scan records it too. --}}
        <form method="POST" action="{{ route($prefix.'.arrived', $manifest) }}" class="pr-actions" style="margin-bottom:.5rem">
            @csrf
            <button type="submit" class="pr-btn pr-btn-outline">Mark truck arrived</button>
        </form>
    @endunless
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
        <thead><tr><th>Tracking no.</th><th>Deliver to</th><th>Check</th>@unless($manifest->status === 'draft')<th>Scanned by</th>@endunless @if($outbound && $manifest->status === 'draft')<th></th>@endif</tr></thead>
        <tbody>
        @forelse($manifest->items as $item)
            <tr>
                <td>@include('pickrush.partials.tracking-no', ['parcel' => $item->parcel])@if($item->parcel->is_return) <span class="pr-badge pr-badge-warn">Return</span>@endif</td>
                <td>{{ $item->parcel->is_return ? $item->parcel->shipper_name : $item->parcel->consignee_name }}</td>
                <td><span class="pr-badge {{ $badge($item->scan_status) }}">{{ ucfirst($item->scan_status) }}</span></td>
                @unless($manifest->status === 'draft')
                    <td>@if($item->scanned_at){{ $item->scanner?->full_name ?? '—' }}<div class="pr-muted">{{ $item->scanned_at->format('M d, h:i A') }}</div>@else<span class="pr-muted">—</span>@endif</td>
                @endunless
                @if($outbound && $manifest->status === 'draft')
                    <td><form method="POST" action="{{ route($prefix.'.remove', [$manifest, $item]) }}">@csrf @method('DELETE')
                        <button class="pr-btn pr-btn-danger pr-btn-sm" type="submit">Remove</button></form></td>
                @endif
            </tr>
        @empty
            <tr><td colspan="5" class="pr-muted">No parcels yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($outbound && $manifest->status === 'draft' && $manifest->route)
    <div class="pr-sticky-bar">
        <strong>Dispatch</strong>
        <span class="pr-muted">This stop leaves with its route.</span>
        <a href="{{ route('pickrush.province.routes.show', $manifest->route) }}" class="pr-btn pr-btn-primary">Back to route {{ $manifest->route->route_no }}</a>
    </div>
@elseif($outbound && $manifest->status === 'draft')
    <form method="POST" action="{{ route($prefix.'.dispatch', $manifest) }}" class="pr-sticky-bar">
        @csrf
        <strong>Dispatch</strong>
        @if($vehicles->isEmpty() || $drivers->isEmpty())
            <span class="pr-muted">This hub needs a registered truck and driver first. <a href="{{ route('pickrush.fleet.index') }}">Trucks &amp; drivers</a></span>
        @else
            @include('pickrush.trips.partials.fleet-picker')
            <button type="submit" class="pr-btn pr-btn-primary" data-confirm="Dispatch this trip with {{ $manifest->items->count() }} parcels?">Dispatch trip</button>
        @endif
    </form>
@elseif($manifest->status !== 'draft')
    <section class="pr-card" style="margin-top:1rem">
        <dl class="pr-dl">
            <dt>Vehicle</dt><dd>{{ $manifest->vehicle_plate }} · {{ $manifest->driver_name }}@if($manifest->driver?->mobile_no) · {{ $manifest->driver->mobile_no }}@endif</dd>
            <dt>Dispatched</dt><dd>{{ $manifest->dispatched_at?->format('M d, Y h:i A') }}</dd>
            @if($manifest->route)<dt>Route</dt><dd>{{ $manifest->route->stops->map(fn ($s) => $s->stop_order.'. '.$s->toHub->name)->join(' → ') }} · {{ $manifest->route->position() }}</dd>@endif
            @if($manifest->due_at)<dt>Due by</dt><dd>{{ $manifest->due_at->format('M d, Y h:i A') }}@if($manifest->isOverdue()) <span class="pr-badge pr-badge-bad">Overdue {{ $manifest->due_at->diffForHumans(null, true) }}</span>@endif</dd>@endif
            <dt>Arrived</dt><dd>@if($manifest->arrived_at){{ $manifest->arrived_at->format('M d, Y h:i A') }}{{ $manifest->arriver ? ' · '.$manifest->arriver->full_name : '' }}@else — @endif</dd>
            <dt>Received</dt><dd>{{ $manifest->received_at?->format('M d, Y h:i A') ?? '—' }}</dd>
            <dt>Closed</dt><dd>{{ $manifest->closed_at?->format('M d, Y h:i A') ?? '—' }}</dd>
        </dl>
        @if($arriving)
            <form method="POST" action="{{ route($prefix.'.close', $manifest) }}" class="pr-actions">
                @csrf
                <button type="submit" class="pr-btn pr-btn-outline" data-confirm="Close the check? Unscanned parcels will be marked missing.">Close check</button>
            </form>
        @endif
    </section>
@endif
@endsection
