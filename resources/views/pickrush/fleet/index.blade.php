@extends('pickrush.layouts.app')
@section('title', 'Vehicles & drivers')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Vehicles &amp; drivers</h1>
        <p>The hub's trucks and vans. Picking one when dispatching a trip fills in the plate and driver.</p>
    </div>
</div>

<div class="pr-table-wrap pr-section">
    <table class="pr-table">
        <thead><tr><th>Plate</th><th>Type</th><th class="pr-num">Capacity</th><th>Driver</th><th>Status</th>@if($canManage)<th>Edit</th>@endif</tr></thead>
        <tbody>
        @forelse($vehicles as $vehicle)
            <tr>
                <td><strong>{{ $vehicle->plate_no }}</strong></td>
                <td>{{ ucfirst($vehicle->vehicle_type) }}</td>
                <td class="pr-num">{{ $vehicle->capacity_parcels ?? '—' }}</td>
                <td>{{ $vehicle->driver_name ?? '—' }}<div class="pr-muted">{{ $vehicle->driver_mobile }}</div></td>
                <td>
                    @if(! $vehicle->is_active)<span class="pr-badge">Inactive</span>
                    @elseif($onTrip->has($vehicle->plate_no))<span class="pr-badge pr-badge-brand">On a trip</span>
                    @else<span class="pr-badge pr-badge-ok">Available</span>@endif
                </td>
                @if($canManage)
                    <td>
                        <form method="POST" action="{{ route('pickrush.fleet.update', $vehicle) }}" class="pr-inline">
                            @csrf @method('PUT')
                            <input type="hidden" name="plate_no" value="{{ $vehicle->plate_no }}">
                            <input type="hidden" name="vehicle_type" value="{{ $vehicle->vehicle_type }}">
                            <input type="hidden" name="capacity_parcels" value="{{ $vehicle->capacity_parcels }}">
                            <input name="driver_name" class="pr-input" value="{{ $vehicle->driver_name }}" placeholder="Driver" aria-label="Driver" style="width:140px">
                            <input name="driver_mobile" class="pr-input" value="{{ $vehicle->driver_mobile }}" placeholder="09…" aria-label="Driver mobile" style="width:120px">
                            <label class="pr-check" style="margin:0"><input type="checkbox" name="is_active" value="1" @checked($vehicle->is_active)> Active</label>
                            <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm">Save</button>
                        </form>
                    </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="6" class="pr-muted">No vehicles yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($canManage)
    <form method="POST" action="{{ route('pickrush.fleet.store') }}" class="pr-card">
        @csrf
        <h2>Add a vehicle</h2>
        <div class="pr-form-grid">
            <div class="pr-field"><label for="plate_no">Plate number</label><input id="plate_no" name="plate_no" class="pr-input" value="{{ old('plate_no') }}" required></div>
            <div class="pr-field"><label for="vehicle_type">Type</label>
                <select id="vehicle_type" name="vehicle_type" class="pr-select">@foreach(['truck' => 'Truck', 'van' => 'Van', 'car' => 'Car', 'motorcycle' => 'Motorcycle'] as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
            <div class="pr-field"><label for="capacity_parcels">Capacity (parcels)</label><input id="capacity_parcels" name="capacity_parcels" type="number" min="1" class="pr-input"></div>
            <div class="pr-field"><label for="driver_name">Usual driver</label><input id="driver_name" name="driver_name" class="pr-input"></div>
            <div class="pr-field"><label for="driver_mobile">Driver mobile</label><input id="driver_mobile" name="driver_mobile" class="pr-input" placeholder="09XXXXXXXXX"></div>
        </div>
        <div class="pr-actions"><button type="submit" class="pr-btn pr-btn-primary">Add vehicle</button></div>
    </form>
@endif
@endsection
