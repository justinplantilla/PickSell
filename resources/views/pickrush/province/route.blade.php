@extends('pickrush.layouts.app')
@section('title', 'Route '.$route->route_no)

@section('content')
@php
    $draft = $route->status === 'draft';
    $statusLabel = ['draft' => 'Being loaded', 'dispatched' => 'On the road', 'completed' => 'Completed'][$route->status] ?? ucfirst($route->status);
    $totalKm = collect($legs)->filter()->sum();
@endphp
<div class="pr-page-head">
    <div>
        <h1>Route {{ $route->route_no }}</h1>
        <p>{{ $route->hub->name }} → {{ $route->stops->map(fn ($s) => $s->toHub->name)->join(' → ') }} · <a href="{{ route('pickrush.province.trips.index') }}">All trips</a></p>
    </div>
    <div>
        <a href="{{ route('pickrush.province.routes.print', $route) }}" target="_blank" rel="noopener" class="pr-btn pr-btn-outline pr-btn-sm">Print route sheet</a>
        <span class="pr-badge pr-badge-brand">{{ $statusLabel }}</span>
        @if($draft)
            <form method="POST" action="{{ route('pickrush.province.routes.cancel', $route) }}" style="display:inline">
                @csrf @method('DELETE')
                <button type="submit" class="pr-btn pr-btn-danger pr-btn-sm" data-confirm="Cancel this route? Every stop's parcels go back to ready to leave.">Cancel route</button>
            </form>
        @endif
    </div>
</div>

<div class="pr-grid">
    <div class="pr-card"><div class="pr-stat-num">{{ $route->stops->count() }}<span class="pr-muted" style="font-size:1rem"> / {{ $maxStops }}</span></div><div class="pr-stat-label">Stops</div></div>
    <div class="pr-card"><div class="pr-stat-num">{{ $route->stops->sum('parcel_count') }}</div><div class="pr-stat-label">Parcels on the truck</div></div>
    @if($totalKm)<div class="pr-card"><div class="pr-stat-num">{{ number_format($totalKm, 1) }} km</div><div class="pr-stat-label">Straight-line distance</div></div>@endif
    @unless($draft)
        <div class="pr-card"><div class="pr-stat-num">{{ $route->stops->whereNotNull('arrived_at')->count() }}</div><div class="pr-stat-label">Stops arrived</div></div>
    @endunless
</div>

@if($draft)
    <div class="pr-actions" style="margin-bottom:1rem">
        @if($ready->sum())
            <form method="POST" action="{{ route('pickrush.province.routes.load-all', $route) }}">@csrf
                <button type="submit" class="pr-btn pr-btn-outline">Load all {{ $ready->sum() }} ready parcels</button>
            </form>
        @endif
        @if($route->stops->count() > 1)
            <form method="POST" action="{{ route('pickrush.province.routes.auto-order', $route) }}">@csrf
                <button type="submit" class="pr-btn pr-btn-outline">Auto-order stops</button>
            </form>
        @endif
        @if($addable->isNotEmpty() && $route->stops->count() < $maxStops)
            <form method="POST" action="{{ route('pickrush.province.routes.stops.add', $route) }}" class="pr-inline">@csrf
                <select name="to_hub_id" class="pr-select" required aria-label="Station to add">
                    <option value="">Add a station…</option>
                    @foreach($addable as $station)<option value="{{ $station->id }}">{{ $station->name }}</option>@endforeach
                </select>
                <button type="submit" class="pr-btn pr-btn-outline">Add stop</button>
            </form>
        @endif
    </div>
@endif

<div class="pr-table-wrap">
    <table class="pr-table">
        <thead><tr>
            <th class="pr-num">#</th><th>Station</th><th>Trip</th><th class="pr-num">Leg</th><th class="pr-num">Parcels</th>
            @if($draft)<th class="pr-num">Ready</th><th></th>@else<th>Due by</th><th>Arrived</th>@endif
        </tr></thead>
        <tbody>
        @forelse($route->stops as $stop)
            <tr>
                <td class="pr-num"><strong>{{ $stop->stop_order }}</strong></td>
                <td>{{ $stop->toHub->name }}</td>
                <td><a href="{{ route('pickrush.province.trips.show', $stop) }}">{{ $stop->manifest_no }}</a></td>
                <td class="pr-num">{{ isset($legs[$stop->id]) ? number_format($legs[$stop->id], 1).' km' : '—' }}</td>
                <td class="pr-num">{{ $stop->parcel_count }}@if($draft && ! $stop->parcel_count) <span class="pr-badge pr-badge-warn">Empty</span>@endif</td>
                @if($draft)
                    <td class="pr-num">{{ $ready[$stop->id] ?? 0 }}</td>
                    <td>
                        <div class="pr-inline">
                            <a href="{{ route('pickrush.province.trips.show', $stop) }}" class="pr-btn pr-btn-primary pr-btn-sm">Load</a>
                            @unless($loop->first)
                                <form method="POST" action="{{ route('pickrush.province.routes.stops.move', [$route, $stop]) }}">@csrf
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm" aria-label="Move {{ $stop->toHub->name }} earlier">↑</button>
                                </form>
                            @endunless
                            @unless($loop->last)
                                <form method="POST" action="{{ route('pickrush.province.routes.stops.move', [$route, $stop]) }}">@csrf
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm" aria-label="Move {{ $stop->toHub->name }} later">↓</button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('pickrush.province.routes.stops.remove', [$route, $stop]) }}">@csrf @method('DELETE')
                                <button type="submit" class="pr-btn pr-btn-danger pr-btn-sm" data-confirm="Take {{ $stop->toHub->name }} off the route? Its {{ $stop->parcel_count }} parcels go back to ready to leave.">Remove</button>
                            </form>
                        </div>
                    </td>
                @else
                    <td>{{ $stop->due_at?->format('M d, h:i A') ?? '—' }}@if($stop->isOverdue()) <span class="pr-badge pr-badge-bad">Overdue</span>@endif</td>
                    <td>@if($stop->arrived_at)<span class="pr-badge pr-badge-ok">{{ $stop->arrived_at->format('M d, h:i A') }}</span>@else<span class="pr-muted">On the way</span>@endif</td>
                @endif
            </tr>
        @empty
            <tr><td colspan="7" class="pr-muted">No stops yet. Add a station.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($draft)
    <form method="POST" action="{{ route('pickrush.province.routes.dispatch', $route) }}" class="pr-sticky-bar">
        @csrf
        <strong>Dispatch</strong>
        @if($vehicles->isEmpty() || $drivers->isEmpty())
            <span class="pr-muted">This hub needs a registered truck and driver first. <a href="{{ route('pickrush.fleet.index') }}">Trucks &amp; drivers</a></span>
        @else
            @include('pickrush.trips.partials.fleet-picker')
            <button type="submit" class="pr-btn pr-btn-primary" data-confirm="Dispatch this route: {{ $route->stops->count() }} stops, {{ $route->stops->sum('parcel_count') }} parcels, one truck?">Dispatch route</button>
        @endif
    </form>
@else
    <section class="pr-card" style="margin-top:1rem">
        <dl class="pr-dl">
            <dt>Vehicle</dt><dd>{{ $route->vehicle_plate }} · {{ $route->driver_name }}@if($route->driver?->mobile_no) · {{ $route->driver->mobile_no }}@endif</dd>
            <dt>Where</dt><dd>{{ $route->position() }}</dd>
            <dt>Dispatched</dt><dd>{{ $route->dispatched_at?->format('M d, Y h:i A') }}</dd>
            <dt>Completed</dt><dd>{{ $route->completed_at?->format('M d, Y h:i A') ?? '— (truck free once the last stop arrives)' }}</dd>
        </dl>
    </section>
@endif
@endsection
