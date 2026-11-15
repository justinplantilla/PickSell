{{-- A truck's LTO papers: the CR (ownership) and the OR of its registration. $vehicle is null when adding a truck; otherwise this is a renewal that starts from what is on file. --}}
@php
    $p = $vehicle ? "v{$vehicle->id}-" : 'new-';
    $value = fn (string $field, $current = null) => $vehicle ? $current : old($field);
    $paper = '.jpg,.jpeg,.png,.heic,.webp,.pdf';
@endphp
<div class="pr-field"><label for="{{ $p }}cr_no">CR no.</label>
    <input id="{{ $p }}cr_no" name="cr_no" class="pr-input" inputmode="numeric" pattern="[0-9]{7,12}" maxlength="12" title="7 to 12 digits"
        value="{{ $value('cr_no', $vehicle?->cr_no) }}" required data-digits-only></div>
<div class="pr-field"><label for="{{ $p }}mv_file_no">MV file no.</label>
    <input id="{{ $p }}mv_file_no" name="mv_file_no" class="pr-input" placeholder="0000-00000000000" pattern="[0-9]{4}-?[0-9]{7,11}" maxlength="16" title="Format must be 0000-00000000000"
        value="{{ $value('mv_file_no', $vehicle?->mv_file_no) }}" required></div>
<div class="pr-field"><label for="{{ $p }}engine_no">Engine no.</label>
    <input id="{{ $p }}engine_no" name="engine_no" class="pr-input" maxlength="20" style="text-transform:uppercase"
        value="{{ $value('engine_no', $vehicle?->engine_no) }}" required></div>
<div class="pr-field"><label for="{{ $p }}chassis_no">Chassis no.</label>
    <input id="{{ $p }}chassis_no" name="chassis_no" class="pr-input" maxlength="20" style="text-transform:uppercase"
        value="{{ $value('chassis_no', $vehicle?->chassis_no) }}" required></div>
<div class="pr-field"><label for="{{ $p }}or_no">{{ $vehicle ? 'New OR no.' : 'OR no.' }}</label>
    <input id="{{ $p }}or_no" name="or_no" class="pr-input" inputmode="numeric" pattern="[0-9]{7,12}" maxlength="12" title="7 to 12 digits"
        value="{{ $value('or_no', $vehicle?->or_no) }}" required data-digits-only></div>
<div class="pr-field"><label for="{{ $p }}registration_expiry">Registration valid until</label>
    <input id="{{ $p }}registration_expiry" name="registration_expiry" type="date" class="pr-input" min="{{ today()->addDay()->toDateString() }}" max="{{ today()->addYears(3)->toDateString() }}"
        value="{{ $value('registration_expiry', $vehicle?->registration_expiry?->isFuture() ? $vehicle->registration_expiry->toDateString() : null) }}" required></div>
<div class="pr-field"><label for="{{ $p }}or_photo">{{ $vehicle?->or_photo_path ? 'New OR photo' : 'OR photo' }}</label>
    <input id="{{ $p }}or_photo" name="or_photo" type="file" accept="{{ $paper }}" class="pr-input" @required(! $vehicle?->or_photo_path)></div>
<div class="pr-field"><label for="{{ $p }}cr_photo">{{ $vehicle?->cr_photo_path ? 'New CR photo' : 'CR photo' }}</label>
    <input id="{{ $p }}cr_photo" name="cr_photo" type="file" accept="{{ $paper }}" class="pr-input" @required(! $vehicle?->cr_photo_path)></div>
