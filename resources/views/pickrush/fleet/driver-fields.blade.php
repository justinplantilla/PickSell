{{-- Driver fields, in the same formats as the rider application. $driver is null when registering. --}}
@php
    $p = $driver ? "d{$driver->id}-" : 'new-';
    $value = fn (string $field, $current = null) => $driver ? $current : old($field);
    $codes = $driver ? $driver->licenseCodes() : old('license_codes', []);
@endphp
<div class="pr-field"><label for="{{ $p }}first_name">First name</label>
    <input id="{{ $p }}first_name" name="first_name" class="pr-input" value="{{ $value('first_name', $driver?->first_name) }}" minlength="2" maxlength="60" required></div>
<div class="pr-field"><label for="{{ $p }}last_name">Last name</label>
    <input id="{{ $p }}last_name" name="last_name" class="pr-input" value="{{ $value('last_name', $driver?->last_name) }}" minlength="2" maxlength="60" required></div>
<div class="pr-field"><label for="{{ $p }}birthdate">Date of birth</label>
    <input id="{{ $p }}birthdate" name="birthdate" type="date" class="pr-input" max="{{ today()->subYears(18)->toDateString() }}" title="The driver must be at least 18 years old"
        value="{{ $value('birthdate', $driver?->birthdate?->toDateString()) }}" required></div>
<div class="pr-field"><label for="{{ $p }}mobile">Mobile number</label>
    <div style="display:flex;gap:.4rem;align-items:center"><span class="pr-badge">+63</span>
        <input id="{{ $p }}mobile" name="mobile" class="pr-input" inputmode="numeric" placeholder="917 123 4567" pattern="9[0-9]{9}" maxlength="10" title="10 digits starting with 9"
            value="{{ $value('mobile', $driver ? substr($driver->mobile_no, 1) : null) }}" required data-digits-only></div></div>
<div class="pr-field"><label for="{{ $p }}license_no">Driver's license no.</label>
    <input id="{{ $p }}license_no" name="license_no" class="pr-input" placeholder="A00-00-000000" pattern="[A-Z][0-9]{2}-[0-9]{2}-[0-9]{6}" maxlength="13" title="Format must be A00-00-000000"
        value="{{ $value('license_no', $driver?->license_no) }}" required data-license-no></div>
<div class="pr-field"><label for="{{ $p }}license_expiry">License expiry</label>
    <input id="{{ $p }}license_expiry" name="license_expiry" type="date" class="pr-input" min="{{ today()->addDay()->toDateString() }}"
        value="{{ $value('license_expiry', $driver?->license_expiry->toDateString()) }}" required></div>
<div class="pr-field"><label>Restriction codes</label>
    <div data-require-one="Select at least one restriction code.">@foreach($licenseCodes as $code)<label class="pr-check" style="display:inline-flex;margin-right:.5rem"><input type="checkbox" name="license_codes[]" value="{{ $code }}" @checked(in_array($code, $codes, true))> {{ $code }}</label>@endforeach</div></div>
<div class="pr-field"><label for="{{ $p }}license_front">{{ $driver ? 'Replace license (front)' : 'License (front)' }}</label>
    <input id="{{ $p }}license_front" name="license_front" type="file" accept=".jpg,.jpeg,.png,.heic,.webp" class="pr-input" @required(! $driver)></div>
<div class="pr-field"><label for="{{ $p }}license_back">{{ $driver ? 'Replace license (back)' : 'License (back)' }}</label>
    <input id="{{ $p }}license_back" name="license_back" type="file" accept=".jpg,.jpeg,.png,.heic,.webp" class="pr-input" @required(! $driver)></div>
