{{--
    Map pin picker: free OpenStreetMap tiles through Leaflet, no API key. Click the map or drag
    the pin; "Use my current location" asks the browser. Writes into the two hidden inputs.
    Params: $id (unique), $lat, $lng (current pin or null), optional $center [lat, lng] to open at.
--}}
@php
    $hasPin = $lat !== null && $lng !== null;
    $start = $hasPin ? [(float) $lat, (float) $lng] : ($center ?? [12.8797, 121.7740]);   // the Philippines
@endphp
<div class="map-pin" data-map-pin="{{ $id }}" data-lat="{{ $hasPin ? $lat : '' }}" data-lng="{{ $hasPin ? $lng : '' }}"
     data-start="{{ json_encode($start) }}" data-zoom="{{ $hasPin ? 17 : ($center ?? null ? 14 : 6) }}">
    <div id="{{ $id }}-map" style="height:320px;border-radius:10px;border:1px solid #e3e6ec;z-index:0"></div>
    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-top:.6rem">
        <button type="button" data-map-pin-locate style="padding:.45rem .8rem;border-radius:8px;border:1px solid #d0d5dd;background:#fff;cursor:pointer">📍 Use my current location</button>
        <span data-map-pin-label style="color:#6b7280;font-size:.9em">{{ $hasPin ? 'Pinned at '.round((float) $lat, 6).', '.round((float) $lng, 6) : 'No pin yet: click the map to drop one.' }}</span>
    </div>
    <input type="hidden" name="lat" value="{{ $hasPin ? $lat : '' }}" data-map-pin-lat>
    <input type="hidden" name="lng" value="{{ $hasPin ? $lng : '' }}" data-map-pin-lng>
</div>

@once
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-map-pin]').forEach((box) => {
                const latInput = box.querySelector('[data-map-pin-lat]');
                const lngInput = box.querySelector('[data-map-pin-lng]');
                const label = box.querySelector('[data-map-pin-label]');
                const map = L.map(box.dataset.mapPin + '-map').setView(JSON.parse(box.dataset.start), Number(box.dataset.zoom));
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                }).addTo(map);

                let marker = null;
                const place = (latlng, zoom) => {
                    if (!marker) {
                        marker = L.marker(latlng, { draggable: true }).addTo(map);
                        marker.on('dragend', () => place(marker.getLatLng()));
                    }
                    marker.setLatLng(latlng);
                    latInput.value = latlng.lat.toFixed(7);
                    lngInput.value = latlng.lng.toFixed(7);
                    label.textContent = 'Pinned at ' + latlng.lat.toFixed(6) + ', ' + latlng.lng.toFixed(6) + ' — save to keep it.';
                    if (zoom) map.setView(latlng, zoom);
                };
                if (box.dataset.lat && box.dataset.lng) place(L.latLng(Number(box.dataset.lat), Number(box.dataset.lng)));
                map.on('click', (e) => place(e.latlng));

                // A page can move the view (not the pin), e.g. to the barangay just picked:
                // box.dispatchEvent(new CustomEvent('map-pin:center', { detail: { lat, lng, zoom } }))
                box.addEventListener('map-pin:center', (e) => map.setView([e.detail.lat, e.detail.lng], e.detail.zoom || 16));
                box.mapPinHasPin = () => marker !== null;

                box.querySelector('[data-map-pin-locate]').addEventListener('click', () => {
                    if (!navigator.geolocation) return alert('This browser cannot share its location.');
                    label.textContent = 'Finding you…';
                    navigator.geolocation.getCurrentPosition(
                        (pos) => place(L.latLng(pos.coords.latitude, pos.coords.longitude), 18),
                        () => { label.textContent = 'Location blocked. Click the map to drop the pin instead.'; },
                        { enableHighAccuracy: true, timeout: 15000 }
                    );
                });
            });
        });
    </script>
@endonce
