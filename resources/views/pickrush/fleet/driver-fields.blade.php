{{-- Driver's personal details, in the same formats as the rider application. $driver is null when registering. --}}
@php
    $p = $driver ? "d{$driver->id}-" : 'new-';
    $value = fn (string $field, $current = null) => $driver ? $current : old($field);
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
