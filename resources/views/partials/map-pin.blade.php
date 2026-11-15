{{--
    Map pin picker: free OpenStreetMap tiles through Leaflet, no API key. Click the map or drag
    the pin; "Use my current location" asks the browser. Writes into the two hidden inputs.
    Params: $id (unique), $lat, $lng (current pin or null), optional $center [lat, lng] to open at,
    optional $place ("City, Province") and $placeZoom for pickrush.partials.map-centering to open at.
--}}
@php
    $hasPin = $lat !== null && $lng !== null;
    $start = $hasPin ? [(float) $lat, (float) $lng] : ($center ?? [12.8797, 121.7740]);   // the Philippines
@endphp
<div class="map-pin" data-map-pin="{{ $id }}" data-lat="{{ $hasPin ? $lat : '' }}" data-lng="{{ $hasPin ? $lng : '' }}"
     data-start="{{ json_encode($start) }}" data-zoom="{{ $hasPin ? 17 : ($center ?? null ? 14 : 6) }}"
     @isset($place) data-place="{{ $place }}" data-place-zoom="{{ $placeZoom ?? 13 }}" @endisset>
    <div id="{{ $id }}-map" class="map-pin-canvas"></div>
    <div class="map-pin-bar">
        <button type="button" data-map-pin-locate class="map-pin-locate"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true" style="vertical-align:-2px;color:#E8472A"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg> Use my current location</button>
        <span data-map-pin-label class="map-pin-label">{{ $hasPin ? 'Pinned at '.round((float) $lat, 6).', '.round((float) $lng, 6) : 'No pin yet: click the map to drop one.' }}</span>
    </div>
    <input type="hidden" name="lat" value="{{ $hasPin ? $lat : '' }}" data-map-pin-lat>
    <input type="hidden" name="lng" value="{{ $hasPin ? $lng : '' }}" data-map-pin-lng>
</div>

@once
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    {{-- Base size here, so the map shows on pages that do not load ui-feedback.css (the logistics portal). --}}
    <style>
        .map-pin-canvas { height: 320px; border-radius: 10px; border: 1px solid #e3e6ec; z-index: 0; }
        .map-pin-bar { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-top: .6rem; }
        .map-pin-locate { padding: .45rem .8rem; border: 1px solid #d0d5dd; border-radius: 8px; background: #fff; color: inherit; font: inherit; cursor: pointer; }
        .map-pin-label { color: #6b7280; font-size: .9em; }
    </style>
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
                // A map drawn inside a closed <details> has no size yet: redraw it when opened.
                box.closest('details')?.addEventListener('toggle', () => map.invalidateSize());

                box.querySelector('[data-map-pin-locate]').addEventListener('click', () => {
                    if (!navigator.geolocation) return window.notify ? window.notify.error('This browser cannot share its location.') : alert('This browser cannot share its location.');
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
