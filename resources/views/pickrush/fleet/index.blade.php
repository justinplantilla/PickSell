@extends('pickrush.layouts.app')
@section('title', 'Trucks & drivers')

@section('content')
@php
    $activeCount = $vehicles->where('is_active', true)->count();
    $licenseBadge = function ($driver) {
        if ($driver->isLicenseExpired()) return ['pr-badge-bad', 'Expired'];
        if ($driver->license_expiry->lt(today()->addDays(30))) return ['pr-badge-warn', 'Expiring soon'];
        return ['pr-badge-ok', 'Valid'];
    };
@endphp
<div class="pr-page-head">
    <div>
        <h1>Trucks &amp; drivers</h1>
        <p>Company trucks registered under {{ $company }}, and the drivers hired to drive them. Trips leave with one of these. This hub keeps up to {{ $limit }} active {{ $limit === 1 ? 'truck' : 'trucks' }} ({{ $activeCount }} now).</p>
    </div>
</div>

<h2>Trucks</h2>
<div class="pr-table-wrap pr-section">
    <table class="pr-table">
        <thead><tr><th>Plate</th><th>Type</th><th class="pr-num">Capacity</th><th>Registered owner</th><th>Default driver</th><th>Status</th>@if($canManage)<th>Edit</th>@endif</tr></thead>
        <tbody>
        @forelse($vehicles as $vehicle)
            <tr>
                <td><strong>{{ $vehicle->plate_no }}</strong></td>
                <td>{{ ucfirst($vehicle->vehicle_type) }}</td>
                <td class="pr-num">{{ $vehicle->capacity_parcels ?? '—' }}</td>
                <td>{{ $vehicle->registered_owner ?? $company }}</td>
                <td>{{ $vehicle->defaultDriver?->full_name ?? '—' }}</td>
                <td>
                    @if(! $vehicle->is_active)<span class="pr-badge">Inactive</span>
                    @elseif($vehiclesOut->has($vehicle->id))<span class="pr-badge pr-badge-brand">On a trip</span>
                    @else<span class="pr-badge pr-badge-ok">Available</span>@endif
                </td>
                @if($canManage)
                    <td>
                        <form method="POST" action="{{ route('pickrush.fleet.update', $vehicle) }}" class="pr-inline">
                            @csrf @method('PUT')
                            <input type="hidden" name="plate_no" value="{{ $vehicle->plate_no }}">
                            <input type="hidden" name="vehicle_type" value="{{ $vehicle->vehicle_type }}">
                            <input type="hidden" name="capacity_parcels" value="{{ $vehicle->capacity_parcels }}">
                            <select name="default_driver_id" class="pr-select" aria-label="Default driver">
                                <option value="">No default driver</option>
                                @foreach($drivers->where('is_active', true) as $driver)<option value="{{ $driver->id }}" @selected($vehicle->default_driver_id === $driver->id)>{{ $driver->full_name }}</option>@endforeach
                            </select>
                            <label class="pr-check" style="margin:0"><input type="checkbox" name="is_active" value="1" @checked($vehicle->is_active)> Active</label>
                            <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm">Save</button>
                        </form>
                    </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="7" class="pr-muted">No trucks yet. Trips cannot be dispatched until this hub has one.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<h2>Drivers</h2>
<div class="pr-table-wrap pr-section">
    <table class="pr-table">
        <thead><tr><th>Driver</th><th>License</th><th>Codes</th><th>Expiry</th><th>Photos</th><th>Status</th>@if($canManage)<th>Edit</th>@endif</tr></thead>
        <tbody>
        @forelse($drivers as $driver)
            @php([$badgeClass, $badgeText] = $licenseBadge($driver))
            <tr>
                <td><strong>{{ $driver->full_name }}</strong><div class="pr-muted">{{ $driver->mobile_no }}{{ $driver->birthdate ? ' · '.$driver->birthdate->age.' yrs old' : ' · no birthdate yet' }}</div></td>
                <td>{{ $driver->license_no }}</td>
                <td>{{ implode(', ', $driver->licenseCodes()) }}</td>
                <td>{{ $driver->license_expiry->format('M d, Y') }} <span class="pr-badge {{ $badgeClass }}">{{ $badgeText }}</span></td>
                <td>
                    @if($driver->license_front_path)<a href="{{ route('pickrush.fleet.drivers.license', [$driver, 'front']) }}" target="_blank" rel="noopener">Front</a>@endif
                    @if($driver->license_back_path) · <a href="{{ route('pickrush.fleet.drivers.license', [$driver, 'back']) }}" target="_blank" rel="noopener">Back</a>@endif
                    @if(! $driver->license_front_path && ! $driver->license_back_path)<span class="pr-muted">None</span>@endif
                </td>
                <td>
                    @if(! $driver->is_active)<span class="pr-badge">Inactive</span>
                    @elseif($driversOut->has($driver->id))<span class="pr-badge pr-badge-brand">On a trip</span>
                    @else<span class="pr-badge pr-badge-ok">Available</span>@endif
                </td>
                @if($canManage)
                    <td>
                        <details>
                            <summary class="pr-btn pr-btn-outline pr-btn-sm">Edit</summary>
                            <form method="POST" action="{{ route('pickrush.fleet.drivers.update', $driver) }}" enctype="multipart/form-data" class="pr-form-grid" style="margin-top:.5rem;min-width:280px">
                                @csrf @method('PUT')
                                @include('pickrush.fleet.driver-fields', ['driver' => $driver])
                                <label class="pr-check"><input type="checkbox" name="is_active" value="1" @checked($driver->is_active)> Active</label>
                                <div class="pr-actions"><button type="submit" class="pr-btn pr-btn-primary pr-btn-sm">Save</button></div>
                            </form>
                        </details>
                    </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="7" class="pr-muted">No drivers yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($canManage)
    <div class="pr-grid">
        @if($activeCount < $limit)
            <form method="POST" action="{{ route('pickrush.fleet.store') }}" class="pr-card">
                @csrf
                <h2>Add a truck</h2>
                <p class="pr-muted">Registered under {{ $company }}.</p>
                <div class="pr-form-grid">
                    <div class="pr-field"><label for="plate_no">Plate number</label><input id="plate_no" name="plate_no" class="pr-input" value="{{ old('plate_no') }}" required></div>
                    <div class="pr-field"><label for="vehicle_type">Type</label>
                        <select id="vehicle_type" name="vehicle_type" class="pr-select">@foreach(['truck' => 'Truck', 'van' => 'Van'] as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="pr-field"><label for="capacity_parcels">Capacity (parcels)</label><input id="capacity_parcels" name="capacity_parcels" type="number" min="1" class="pr-input" value="{{ old('capacity_parcels') }}"></div>
                    <div class="pr-field"><label for="default_driver_id">Default driver</label>
                        <select id="default_driver_id" name="default_driver_id" class="pr-select">
                            <option value="">None yet</option>
                            @foreach($drivers->where('is_active', true) as $driver)<option value="{{ $driver->id }}">{{ $driver->full_name }}</option>@endforeach
                        </select></div>
                </div>
                <div class="pr-actions"><button type="submit" class="pr-btn pr-btn-primary">Add truck</button></div>
            </form>
        @else
            <section class="pr-card">
                <h2>Add a truck</h2>
                <p class="pr-muted">This hub already has its {{ $limit }} active {{ $limit === 1 ? 'truck' : 'trucks' }}. Deactivate one to add a replacement.</p>
            </section>
        @endif

        <form method="POST" action="{{ route('pickrush.fleet.drivers.store') }}" enctype="multipart/form-data" class="pr-card">
            @csrf
            <h2>Register a driver</h2>
            <div class="pr-form-grid">
                @include('pickrush.fleet.driver-fields', ['driver' => null])
            </div>
            <div class="pr-actions"><button type="submit" class="pr-btn pr-btn-primary">Register driver</button></div>
        </form>
    </div>
@endif
@endsection
