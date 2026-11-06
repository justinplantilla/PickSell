{{-- Add or edit an address-book entry. $address is the entry being edited (null to add); $itemIds returns to checkout. --}}
@php $key = $address?->id ?? 'new'; @endphp
<form method="POST" action="{{ $address ? route('buyer.addresses.update', $address) : route('buyer.addresses.store') }}" class="address-form" data-no-confirm>
    @csrf
    @if($address) @method('PUT') @endif
    @foreach($itemIds ?? [] as $id)
        <input type="hidden" name="item_ids[]" value="{{ $id }}">
    @endforeach
    <div class="address-form-grid">
        <div class="form-group">
            <label class="form-label" for="address-{{ $key }}-recipient">Recipient name *</label>
            <input type="text" id="address-{{ $key }}-recipient" name="recipient_name" class="form-control" maxlength="120" required value="{{ $address?->recipient_name ?? auth()->user()->full_name }}">
        </div>
        <div class="form-group">
            <label class="form-label" for="address-{{ $key }}-phone">Mobile number *</label>
            <input type="tel" id="address-{{ $key }}-phone" name="contact_no" class="form-control" required inputmode="numeric" placeholder="09171234567" value="{{ $address?->contact_no ?? auth()->user()->contact_no }}">
        </div>
        <div class="address-picker address-form-wide" data-address-picker data-province="{{ $address?->province }}" data-municipality="{{ $address?->municipality }}" data-barangay="{{ $address?->barangay }}">
            <div class="form-group">
                <label class="form-label" for="address-{{ $key }}-province">Province *</label>
                <select id="address-{{ $key }}-province" name="province" class="form-control" required data-address-level="province"><option value="">Loading provinces...</option></select>
            </div>
            <div class="form-group">
                <label class="form-label" for="address-{{ $key }}-municipality">City / Municipality *</label>
                <select id="address-{{ $key }}-municipality" name="municipality" class="form-control" required data-address-level="municipality" disabled><option value="">Select a province first</option></select>
            </div>
            <div class="form-group">
                <label class="form-label" for="address-{{ $key }}-barangay">Barangay *</label>
                <select id="address-{{ $key }}-barangay" name="barangay" class="form-control" required data-address-level="barangay" disabled><option value="">Select a city first</option></select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label" for="address-{{ $key }}-house">House no.</label>
            <input type="text" id="address-{{ $key }}-house" name="house_no" class="form-control" maxlength="60" value="{{ $address?->house_no }}">
        </div>
        <div class="form-group">
            <label class="form-label" for="address-{{ $key }}-street">Street</label>
            <input type="text" id="address-{{ $key }}-street" name="street" class="form-control" maxlength="255" value="{{ $address?->street }}">
        </div>
        <div class="form-group address-form-wide">
            <span class="form-label">Door pin <span class="cart-muted-text">(optional, helps the rider find you)</span></span>
            @include('partials.map-pin', ['id' => 'address-'.$key.'-pin', 'lat' => $address?->delivery_lat, 'lng' => $address?->delivery_lng])
        </div>
        <div class="form-group address-form-wide">
            <label class="form-label" for="address-{{ $key }}-label">Label</label>
            <input type="text" id="address-{{ $key }}-label" name="label" class="form-control" maxlength="40" placeholder="e.g. Work, Mom's house" value="{{ $address?->label }}">
        </div>
    </div>
    <button type="submit" class="btn btn-coral btn-sm">{{ $address ? 'Save address' : 'Add address' }}</button>
</form>
