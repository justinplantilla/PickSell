@extends('pickrush.layouts.app')
@section('title', 'Trucks & drivers')

@section('content')
@use('App\Pickrush\Support\Expiry')
@php
    $activeCount = $vehicles->where('is_active', true)->count();
    $warningDays = Expiry::warningDays();
    // Papers that need the manager: expired, expiring within the warning days, or never filed.
    $toRenew = $vehicles->where('is_active', true)->filter(fn ($v) => Expiry::state($v->registration_expiry) !== 'valid')
        ->map(fn ($v) => ['what' => "{$v->plate_no} · OR/CR", 'expiry' => $v->registration_expiry, 'anchor' => "truck-{$v->id}"])
        ->concat($drivers->where('is_active', true)->filter(fn ($d) => Expiry::state($d->license_expiry) !== 'valid')
            ->map(fn ($d) => ['what' => "{$d->full_name} · license", 'expiry' => $d->license_expiry, 'anchor' => "driver-{$d->id}"]))
        ->sortBy(fn ($r) => $r['expiry']?->timestamp ?? 0)->values();
@endphp
<div class="pr-page-head">
    <div>
        <h1>Trucks &amp; drivers</h1>
        <p>Company trucks registered under {{ $company }}, and the drivers hired to drive them. Trips leave with one of these. This hub keeps up to {{ $limit }} active {{ $limit === 1 ? 'truck' : 'trucks' }} ({{ $activeCount }} now).</p>
    </div>
</div>

@if($toRenew->isNotEmpty())
    @php($urgent = $toRenew->contains(fn ($r) => in_array(Expiry::state($r['expiry']), ['expired', 'missing'], true)))
    <div @class(['pr-alert', 'pr-section', 'pr-alert-bad' => $urgent, 'pr-alert-warn' => ! $urgent]) role="status">
        <strong>Papers to renew.</strong> A truck with an expired OR or a driver with an expired license cannot take a trip; renew within {{ $warningDays }} days of the expiry.
        <ul class="pr-alert-list">
            @foreach($toRenew as $row)
                @php([$cls, $text] = Expiry::badge($row['expiry']))
                <li><span><a href="#{{ $row['anchor'] }}">{{ $row['what'] }}</a></span><span><span class="pr-badge {{ $cls }}">{{ $text }}</span>@if($row['expiry']) <span class="pr-muted">{{ $row['expiry']->format('M d, Y') }}</span>@endif</span></li>
            @endforeach
        </ul>
    </div>
@endif

<h2>Trucks</h2>
<div class="pr-table-wrap pr-section">
    <table class="pr-table">
        <thead><tr><th>Plate</th><th>Type</th><th class="pr-num">Capacity</th><th>OR/CR</th><th>Default driver</th><th>Status</th>@if($canManage)<th>Edit</th>@endif</tr></thead>
        <tbody>
        @forelse($vehicles as $vehicle)
            @php([$paperClass, $paperText] = Expiry::badge($vehicle->hasPapers() ? $vehicle->registration_expiry : null))
            <tr id="truck-{{ $vehicle->id }}">
                <td><strong>{{ $vehicle->plate_no }}</strong><div class="pr-muted">{{ $vehicle->registered_owner ?? $company }}</div></td>
                <td>{{ ucfirst($vehicle->vehicle_type) }}</td>
                <td class="pr-num">{{ $vehicle->capacity_parcels ?? '—' }}</td>
                <td>
                    @if($vehicle->hasPapers())
                        OR {{ $vehicle->or_no }} · until {{ $vehicle->registration_expiry->format('M d, Y') }}
                        <div class="pr-muted">CR {{ $vehicle->cr_no }} · MV {{ $vehicle->mv_file_no }}</div>
                        <div class="pr-muted">Engine {{ $vehicle->engine_no }} · Chassis {{ $vehicle->chassis_no }}</div>
                    @endif
                    <span class="pr-badge {{ $paperClass }}">{{ $paperText }}</span>
                    <div>
                        @if($vehicle->or_photo_path)<a href="{{ route('pickrush.fleet.papers.show', [$vehicle, 'or']) }}" target="_blank" rel="noopener">OR</a>@endif
                        @if($vehicle->cr_photo_path) · <a href="{{ route('pickrush.fleet.papers.show', [$vehicle, 'cr']) }}" target="_blank" rel="noopener">CR</a>@endif
                        @if(! $vehicle->or_photo_path && ! $vehicle->cr_photo_path)<span class="pr-muted">No photos yet</span>@endif
                    </div>
                    @include('pickrush.fleet.renewals', ['renewals' => $vehicle->renewals])
                </td>
                <td>{{ $vehicle->defaultDriver?->full_name ?? '—' }}</td>
                <td>
                    @if(! $vehicle->is_active)<span class="pr-badge">Inactive</span>
                    @elseif($vehiclesOut->has($vehicle->id))<span class="pr-badge pr-badge-brand">On a trip</span>
                    @elseif(! $vehicle->canTravel())<span class="pr-badge pr-badge-bad">Cannot travel</span>
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
                        <details style="margin-top:.4rem">
                            <summary class="pr-btn pr-btn-outline pr-btn-sm">{{ $vehicle->hasPapers() ? 'Renew OR/CR' : 'Add OR/CR' }}</summary>
                            <form method="POST" action="{{ route('pickrush.fleet.papers.renew', $vehicle) }}" enctype="multipart/form-data" class="pr-form-grid" style="margin-top:.5rem;min-width:280px">
                                @csrf @method('PUT')
                                @include('pickrush.fleet.paper-fields', ['vehicle' => $vehicle])
                                <p class="pr-muted" style="grid-column:1/-1;margin:0">The old OR/CR stays in the history.</p>
                                <div class="pr-actions"><button type="submit" class="pr-btn pr-btn-primary pr-btn-sm">Save papers</button></div>
                            </form>
                        </details>
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
            @php([$badgeClass, $badgeText] = Expiry::badge($driver->license_expiry))
            <tr id="driver-{{ $driver->id }}">
                <td><strong>{{ $driver->full_name }}</strong><div class="pr-muted">{{ $driver->mobile_no }}{{ $driver->birthdate ? ' · '.$driver->birthdate->age.' yrs old' : ' · no birthdate yet' }}</div></td>
                <td>{{ $driver->license_no }}@include('pickrush.fleet.renewals', ['renewals' => $driver->renewals])</td>
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
                    @elseif($driver->isLicenseExpired())<span class="pr-badge pr-badge-bad">Cannot drive</span>
                    @else<span class="pr-badge pr-badge-ok">Available</span>@endif
                </td>
                @if($canManage)
                    <td>
                        <details>
                            <summary class="pr-btn pr-btn-outline pr-btn-sm">Edit</summary>
                            <form method="POST" action="{{ route('pickrush.fleet.drivers.update', $driver) }}" class="pr-form-grid" style="margin-top:.5rem;min-width:280px">
                                @csrf @method('PUT')
                                @include('pickrush.fleet.driver-fields', ['driver' => $driver])
                                <label class="pr-check"><input type="checkbox" name="is_active" value="1" @checked($driver->is_active)> Active</label>
                                <div class="pr-actions"><button type="submit" class="pr-btn pr-btn-primary pr-btn-sm">Save</button></div>
                            </form>
                        </details>
                        <details style="margin-top:.4rem">
                            <summary class="pr-btn pr-btn-outline pr-btn-sm">Renew license</summary>
                            <form method="POST" action="{{ route('pickrush.fleet.drivers.license.renew', $driver) }}" enctype="multipart/form-data" class="pr-form-grid" style="margin-top:.5rem;min-width:280px">
                                @csrf @method('PUT')
                                @include('pickrush.fleet.license-fields', ['driver' => $driver])
                                <p class="pr-muted" style="grid-column:1/-1;margin:0">The old license stays in the history.</p>
                                <div class="pr-actions"><button type="submit" class="pr-btn pr-btn-primary pr-btn-sm">Save license</button></div>
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
            <form method="POST" action="{{ route('pickrush.fleet.store') }}" enctype="multipart/form-data" class="pr-card">
                @csrf
                <h2>Add a truck</h2>
                <p class="pr-muted">Registered under {{ $company }}. Enter the details from its LTO OR and CR.</p>
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
                    @include('pickrush.fleet.paper-fields', ['vehicle' => null])
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
                @include('pickrush.fleet.license-fields', ['driver' => null])
            </div>
            <div class="pr-actions"><button type="submit" class="pr-btn pr-btn-primary">Register driver</button></div>
        </form>
    </div>
@endif
@endsection
