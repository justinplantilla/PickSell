{{-- Rider details form fields. Expects optional $rider and $zones. --}}
@php $v = fn ($field) => old($field, $rider?->{$field} ?? null); @endphp
<div class="pr-form-grid">
    <div class="pr-field"><label for="full_name">Full name</label><input id="full_name" name="full_name" class="pr-input" value="{{ $v('full_name') }}" required></div>
    <div class="pr-field"><label for="mobile_no">Mobile number (app login)</label><input id="mobile_no" name="mobile_no" class="pr-input" value="{{ $v('mobile_no') }}" placeholder="09XXXXXXXXX" required></div>
    <div class="pr-field"><label for="birthdate">Birthdate</label><input id="birthdate" type="date" name="birthdate" class="pr-input" value="{{ old('birthdate', $rider?->birthdate?->toDateString()) }}" required></div>
    <div class="pr-field" style="grid-column:1/-1"><label for="address">Home address</label><input id="address" name="address" class="pr-input" value="{{ $v('address') }}" required></div>
    <div class="pr-field"><label for="emergency_contact_name">Emergency contact</label><input id="emergency_contact_name" name="emergency_contact_name" class="pr-input" value="{{ $v('emergency_contact_name') }}" required></div>
    <div class="pr-field"><label for="emergency_contact_no">Emergency contact number</label><input id="emergency_contact_no" name="emergency_contact_no" class="pr-input" value="{{ $v('emergency_contact_no') }}" placeholder="09XXXXXXXXX" required></div>
    <div class="pr-field"><label for="vehicle_type">Vehicle</label>
        <select id="vehicle_type" name="vehicle_type" class="pr-select" required>
            @foreach(['motorcycle' => 'Motorcycle', 'bicycle' => 'Bicycle', 'tricycle' => 'Tricycle', 'car' => 'Car', 'van' => 'Van'] as $value => $label)
                <option value="{{ $value }}" @selected($v('vehicle_type') === $value)>{{ $label }}</option>
            @endforeach
        </select></div>
    <div class="pr-field"><label for="vehicle_model">Vehicle model</label><input id="vehicle_model" name="vehicle_model" class="pr-input" value="{{ $v('vehicle_model') }}"></div>
    <div class="pr-field"><label for="plate_no">Plate number</label><input id="plate_no" name="plate_no" class="pr-input" value="{{ $v('plate_no') }}"></div>
    <div class="pr-field"><label for="zone_id">Home zone</label>
        <select id="zone_id" name="zone_id" class="pr-select">
            <option value="">None yet</option>
            @foreach($zones as $zone)<option value="{{ $zone->id }}" @selected((int) $v('zone_id') === $zone->id)>{{ $zone->name }}</option>@endforeach
        </select></div>
    <div class="pr-field"><label for="payout_method">Payout</label>
        <select id="payout_method" name="payout_method" class="pr-select">
            <option value="">—</option>
            @foreach(['gcash' => 'GCash', 'maya' => 'Maya', 'bank' => 'Bank'] as $value => $label)<option value="{{ $value }}" @selected($v('payout_method') === $value)>{{ $label }}</option>@endforeach
        </select></div>
    <div class="pr-field"><label for="payout_account">Payout account</label><input id="payout_account" name="payout_account" class="pr-input" value="{{ $v('payout_account') }}"></div>
</div>
