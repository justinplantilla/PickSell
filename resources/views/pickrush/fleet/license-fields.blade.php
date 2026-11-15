{{-- The driver's license. $driver is null when registering; otherwise this is a renewal and starts from what is on file. --}}
@php
    $p = $driver ? "l{$driver->id}-" : 'new-';
    $value = fn (string $field, $current = null) => $driver ? $current : old($field);
    $codes = $driver ? $driver->licenseCodes() : old('license_codes', []);
@endphp
<div class="pr-field"><label for="{{ $p }}license_no">Driver's license no.</label>
    <input id="{{ $p }}license_no" name="license_no" class="pr-input" placeholder="A00-00-000000" pattern="[A-Z][0-9]{2}-[0-9]{2}-[0-9]{6}" maxlength="13" title="Format must be A00-00-000000"
        value="{{ $value('license_no', $driver?->license_no) }}" required data-license-no></div>
<div class="pr-field"><label for="{{ $p }}license_expiry">{{ $driver ? 'New expiry' : 'License expiry' }}</label>
    <input id="{{ $p }}license_expiry" name="license_expiry" type="date" class="pr-input" min="{{ today()->addDay()->toDateString() }}"
        value="{{ $value('license_expiry', $driver?->license_expiry->isFuture() ? $driver->license_expiry->toDateString() : null) }}" required></div>
<div class="pr-field"><label>Restriction codes</label>
    <div data-require-one="Select at least one restriction code.">@foreach($licenseCodes as $code)<label class="pr-check" style="display:inline-flex;margin-right:.5rem"><input type="checkbox" name="license_codes[]" value="{{ $code }}" @checked(in_array($code, $codes, true))> {{ $code }}</label>@endforeach</div></div>
<div class="pr-field"><label for="{{ $p }}license_front">{{ $driver?->license_front_path ? 'New license photo (front)' : 'License (front)' }}</label>
    <input id="{{ $p }}license_front" name="license_front" type="file" accept=".jpg,.jpeg,.png,.heic,.webp" class="pr-input" @required(! $driver?->license_front_path)></div>
<div class="pr-field"><label for="{{ $p }}license_back">{{ $driver?->license_back_path ? 'New license photo (back)' : 'License (back)' }}</label>
    <input id="{{ $p }}license_back" name="license_back" type="file" accept=".jpg,.jpeg,.png,.heic,.webp" class="pr-input" @required(! $driver?->license_back_path)></div>
