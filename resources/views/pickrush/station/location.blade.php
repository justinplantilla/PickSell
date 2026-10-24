@extends('pickrush.layouts.app')
@section('title', 'Station location')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Station location</h1>
        <p>Riders navigate to this pin, and their distance from it is recorded when they confirm "Arrived at hub"@if(config('pickrush.enforce_arrival_radius')) (only within {{ config('pickrush.arrival_radius_m') }} m)@endif. Pin the rider drop-off entrance.</p>
    </div>
</div>

<section class="pr-card" style="max-width:760px">
    @if($canEdit)
        <form method="POST" action="{{ route('pickrush.station.location.update') }}" data-station-address
              data-barangays-url="{{ route('pickrush.station.location.barangays') }}" data-province="{{ $province?->name ?? $station->province_name }}">
            @csrf @method('PUT')
            <div class="pr-form-grid">
                <div class="pr-field">
                    <label>Province</label>
                    <input class="pr-input" value="{{ $province?->name ?? $station->province_name }}" disabled>
                </div>
                <div class="pr-field">
                    <label for="address_city_code">City / municipality</label>
                    <select id="address_city_code" name="address_city_code" class="pr-select" required data-city>
                        @foreach($cities as $city)
                            <option value="{{ $city->code }}" @selected(old('address_city_code', $cityCode) === $city->code)>{{ $city->name }}{{ $city->code === $station->psgc_code ? ' (this station)' : '' }}</option>
                        @endforeach
                    </select>
                    @error('address_city_code')<div class="pr-field-error">{{ $message }}</div>@enderror
                </div>
                <div class="pr-field">
                    <label for="address_barangay_code">Barangay</label>
                    <select id="address_barangay_code" name="address_barangay_code" class="pr-select" required data-barangay>
                        <option value="">Choose a barangay…</option>
                        @foreach($barangays as $barangay)
                            <option value="{{ $barangay->code }}" @selected(old('address_barangay_code', $station->address_barangay_code) === $barangay->code)>{{ $barangay->name }}</option>
                        @endforeach
                    </select>
                    @error('address_barangay_code')<div class="pr-field-error">{{ $message }}</div>@enderror
                </div>
                <div class="pr-field">
                    <label for="address_line">Street, building, gate</label>
                    <input id="address_line" name="address_line" class="pr-input" required maxlength="150" data-line
                           value="{{ old('address_line', $station->address_line) }}" placeholder="e.g. Rider Zone, Gate 2, 123 Osmeña Blvd.">
                    @error('address_line')<div class="pr-field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <p style="margin:.25rem 0 1rem"><span class="pr-muted">Riders will see:</span> <strong data-preview>{{ $station->address ?? '—' }}</strong></p>

            @include('partials.map-pin', ['id' => 'station-pin', 'lat' => old('lat', $station->lat), 'lng' => old('lng', $station->lng)])
            <p class="pr-muted" style="margin:.4rem 0 0">The map moves to the city and barangay you pick. Click the exact entrance to drop the pin.</p>
            @error('lat')<div class="pr-field-error">{{ $message }}</div>@enderror
            <div class="pr-actions"><button type="submit" class="pr-btn pr-btn-primary">Save location</button></div>
        </form>
    @else
        <dl class="pr-dl">
            <dt>Address</dt><dd>{{ $station->address ?? 'Not set' }}</dd>
            <dt>Map pin</dt><dd>{{ $station->lat !== null ? round((float) $station->lat, 6).', '.round((float) $station->lng, 6) : 'Not set' }}</dd>
        </dl>
        <p class="pr-muted">Only the hub manager can change the station's location.</p>
    @endif
</section>

@if($canEdit)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-station-address]');
    if (!form) return;
    const city = form.querySelector('[data-city]');
    const barangay = form.querySelector('[data-barangay]');
    const line = form.querySelector('[data-line]');
    const preview = form.querySelector('[data-preview]');
    const pin = form.querySelector('[data-map-pin]');
    const province = form.dataset.province;

    const label = (select) => select.selectedIndex > 0 || select === city ? select.options[select.selectedIndex]?.text.replace(' (this station)', '') : '';
    // OpenStreetMap knows "Cebu City" better than PSGC's "City of Cebu".
    const searchable = (name) => name.replace(/^City of (.+)$/i, '$1 City');

    const refreshPreview = () => {
        const parts = [line.value.trim(), barangay.value ? 'Brgy. ' + label(barangay) : '', label(city), province];
        preview.textContent = parts.filter(Boolean).join(', ') || '—';
    };

    // Free geocoding (OpenStreetMap Nominatim) only to move the map; the pin is always placed by hand.
    const geocode = async (q) => {
        const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=ph&q=' + encodeURIComponent(q);
        try {
            const hits = await (await fetch(url, { headers: { 'Accept-Language': 'en' } })).json();
            return hits[0] ? { lat: Number(hits[0].lat), lng: Number(hits[0].lon) } : null;
        } catch { return null; }
    };
    const centerOn = async (withBarangay) => {
        const cityName = searchable(label(city));
        const spot = (withBarangay && barangay.value && await geocode(`${label(barangay)}, ${cityName}, ${province}, Philippines`))
            || await geocode(`${cityName}, ${province}, Philippines`);
        if (spot) pin.dispatchEvent(new CustomEvent('map-pin:center', { detail: { ...spot, zoom: withBarangay && barangay.value ? 16 : 14 } }));
    };

    city.addEventListener('change', async () => {
        barangay.innerHTML = '<option value="">Loading barangays…</option>';
        refreshPreview();
        const res = await fetch(form.dataset.barangaysUrl + '?city=' + encodeURIComponent(city.value), { headers: { Accept: 'application/json' } });
        const data = res.ok ? await res.json() : { barangays: [] };
        barangay.innerHTML = '<option value="">Choose a barangay…</option>'
            + data.barangays.map((b) => `<option value="${b.code}">${b.name.replace(/</g, '&lt;')}</option>`).join('');
        refreshPreview();
        centerOn(false);
    });
    barangay.addEventListener('change', () => { refreshPreview(); centerOn(true); });
    line.addEventListener('input', refreshPreview);

    refreshPreview();
    // No pin yet: start the map at the station's city (or the saved barangay).
    setTimeout(() => { if (!pin.mapPinHasPin?.()) centerOn(true); }, 0);
});
</script>
@endif
@endsection
