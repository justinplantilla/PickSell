@extends('pickrush.layouts.app')
@section('title', 'Outbound trips')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Outbound trips</h1>
        <p>One trip per destination: down to a station in this province, or across to a neighboring province hub. Parcels for farther provinces relay hub by hub along the route.</p>
    </div>
    <form method="POST" action="{{ route('pickrush.province.trips.store') }}" class="pr-inline">
        @csrf
        <select name="to_hub_id" class="pr-select" required aria-label="Destination">
            <option value="">Destination…</option>
            <optgroup label="Stations">@foreach($allDestinations->where('type', \App\Pickrush\Enums\HubType::Station) as $to)<option value="{{ $to->id }}">{{ $to->name }}</option>@endforeach</optgroup>
            <optgroup label="Neighboring province hubs">@foreach($allDestinations->where('type', \App\Pickrush\Enums\HubType::Province) as $to)<option value="{{ $to->id }}">{{ $to->name }}</option>@endforeach</optgroup>
        </select>
        <button type="submit" class="pr-btn pr-btn-primary">Start trip</button>
    </form>
</div>

@include('pickrush.trips.partials.loading', ['prefix' => 'pickrush.province.trips'])

<section class="pr-section">
    <h2>Multi-stop routes</h2>
    <p class="pr-muted">One truck drops at up to {{ $maxStops }} stations in one run. Stops are ordered nearest-next from this hub; you can change the order before dispatch.</p>
    @if($stations->count() > 1)
        <form method="POST" action="{{ route('pickrush.province.routes.store') }}" class="pr-card" style="margin-bottom:1rem">
            @csrf
            <div>
                @foreach($stations as $station)
                    <label class="pr-check" style="display:inline-flex;margin-right:1rem">
                        <input type="checkbox" name="stations[]" value="{{ $station->id }}" @checked(in_array($station->id, old('stations', [])))>
                        {{ $station->name }}@if($ready = $destinations->firstWhere('hub.id', $station->id)) <span class="pr-muted">· {{ $ready->ready }} ready</span>@endif
                    </label>
                @endforeach
            </div>
            <div class="pr-actions"><button type="submit" class="pr-btn pr-btn-primary">Start route</button> <span class="pr-muted" data-stop-count></span></div>
        </form>
        <script>
            (function () {
                const max = {{ (int) $maxStops }};
                const form = document.currentScript.previousElementSibling;
                const boxes = form.querySelectorAll('input[name="stations[]"]');
                const counter = form.querySelector('[data-stop-count]');
                function sync() {
                    const picked = [...boxes].filter(b => b.checked).length;
                    boxes.forEach(b => { b.disabled = !b.checked && picked >= max; });
                    counter.textContent = picked + ' / ' + max + ' selected';
                }
                boxes.forEach(b => b.addEventListener('change', sync));
                sync();
            })();
        </script>
    @endif
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Route</th><th>Stops</th><th class="pr-num">Parcels</th><th>Status</th><th>Truck</th></tr></thead>
            <tbody>
            @forelse($routes as $run)
                <tr>
                    <td><a href="{{ route('pickrush.province.routes.show', $run) }}">{{ $run->route_no }}</a></td>
                    <td>{{ $run->stops->map(fn ($s) => $s->toHub->name)->join(' → ') }}</td>
                    <td class="pr-num">{{ $run->stops->sum('parcel_count') }}</td>
                    <td><span class="pr-badge">{{ $run->status === 'draft' ? 'Being loaded' : 'On the road' }}</span></td>
                    <td>{{ $run->vehicle_plate ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="pr-muted">No route is being loaded or on the road.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="pr-section">
    <h2>Ready to leave, by destination</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Bin</th><th>Destination</th><th class="pr-num">Ready</th><th class="pr-num">Sorted</th><th></th></tr></thead>
            <tbody>
            @forelse($destinations as $row)
                <tr>
                    <td><strong>{{ $row->bin }}</strong></td>
                    <td>{{ $row->hub->name }}@if($row->hub->isProvince()) <span class="pr-badge pr-badge-brand">Inter-province</span>@endif</td>
                    @php($sortedPct = $row->ready > 0 ? min(100, round($row->sorted / $row->ready * 100)) : 0)
                    <td class="pr-num">{{ $row->ready }}</td>
                    <td class="pr-num">{{ $row->sorted }}<span class="pr-meter" aria-hidden="true"><span style="width: {{ $sortedPct }}%"></span></span></td>
                    <td>
                        <form method="POST" action="{{ route('pickrush.province.trips.store') }}">@csrf
                            <input type="hidden" name="to_hub_id" value="{{ $row->hub->id }}">
                            <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm">Start trip</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="pr-muted">Nothing waiting to leave.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="pr-section">
    <h2>Recent trips</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Trip</th><th>Route</th><th class="pr-num">Parcels</th><th>Status</th><th>Updated</th></tr></thead>
            <tbody>
            @forelse($recent as $trip)
                <tr>
                    <td><a href="{{ route('pickrush.province.trips.show', $trip) }}">{{ $trip->manifest_no }}</a></td>
                    <td>{{ $trip->fromHub->name }} → {{ $trip->toHub->name }}@if($trip->route) <span class="pr-badge">Stop {{ $trip->stop_order }} · {{ $trip->route->route_no }}</span>@endif</td>
                    <td class="pr-num">{{ $trip->parcel_count }}</td>
                    <td><span class="pr-badge">{{ ucfirst($trip->status) }}</span></td>
                    <td>{{ $trip->updated_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="pr-muted">No trips yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
