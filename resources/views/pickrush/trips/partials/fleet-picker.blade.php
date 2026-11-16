{{-- Truck and driver pickers inside a dispatch form. Trucks and drivers out on the road, or with lapsed papers, are disabled. Expects $vehicles, $drivers, $onTrip. --}}
@php
    $vehiclesOut = $onTrip->pluck('vehicle_id')->filter()->flip();
    $driversOut = $onTrip->pluck('driver_id')->filter()->flip();
@endphp
<select name="vehicle_id" class="pr-select" aria-label="Truck" required data-fleet-picker>
    <option value="">Pick a truck…</option>
    @foreach($vehicles as $vehicle)
        @php
            $why = match (true) {
                $vehiclesOut->has($vehicle->id) => ' · on a trip',
                ! $vehicle->hasPapers() => ' · no OR/CR',
                $vehicle->isRegistrationExpired() => ' · OR expired',
                default => null,
            };
            $warn = \App\Pickrush\Support\Expiry::state($vehicle->registration_expiry) === 'expiring' ? ' · OR '.strtolower(\App\Pickrush\Support\Expiry::badge($vehicle->registration_expiry)[1]) : '';
        @endphp
        <option value="{{ $vehicle->id }}" data-driver="{{ $vehicle->default_driver_id }}" @selected((int) old('vehicle_id') === $vehicle->id) @disabled($why)>{{ $vehicle->plate_no }} · {{ $vehicle->vehicle_type }}{{ $why ?? $warn }}</option>
    @endforeach
</select>
<select name="driver_id" class="pr-select" aria-label="Driver" required>
    <option value="">Pick a driver…</option>
    @foreach($drivers as $driver)
        @php
            $why = $driversOut->has($driver->id) ? ' · on a trip' : ($driver->isLicenseExpired() ? ' · license expired' : null);
            $warn = \App\Pickrush\Support\Expiry::state($driver->license_expiry) === 'expiring' ? ' · license '.strtolower(\App\Pickrush\Support\Expiry::badge($driver->license_expiry)[1]) : '';
        @endphp
        <option value="{{ $driver->id }}" @selected((int) old('driver_id') === $driver->id) @disabled($why)>{{ $driver->full_name }}{{ $why ?? $warn }}</option>
    @endforeach
</select>
