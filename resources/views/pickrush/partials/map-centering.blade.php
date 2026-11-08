{{--
    Moves a map pin's view (never the pin) to a place by name, using free OpenStreetMap search.
    - A <select data-center-pin="ID"> whose options carry data-place="City, Province" recenters
      the [data-map-pin="ID"] map when the choice changes.
    - A [data-map-pin] box with data-place opens there when it has no pin yet.
--}}
@once
<script>
document.addEventListener('DOMContentLoaded', () => {
    // OpenStreetMap knows "Tanauan City" better than PSGC's "City of Tanauan".
    const searchable = (name) => name.replace(/City of ([^,]+)/i, '$1 City');
    const geocode = async (place) => {
        const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=ph&q='
            + encodeURIComponent(searchable(place) + ', Philippines');
        try {
            const hits = await (await fetch(url, { headers: { 'Accept-Language': 'en' } })).json();
            return hits[0] ? { lat: Number(hits[0].lat), lng: Number(hits[0].lon) } : null;
        } catch { return null; }
    };
    const center = async (box, place, zoom) => {
        const spot = place && await geocode(place);
        if (spot) box.dispatchEvent(new CustomEvent('map-pin:center', { detail: { ...spot, zoom } }));
    };

    document.querySelectorAll('[data-center-pin]').forEach((select) => {
        const box = document.querySelector(`[data-map-pin="${select.dataset.centerPin}"]`);
        select.addEventListener('change', () => {
            const option = select.options[select.selectedIndex];
            if (box && option?.dataset.place && !box.mapPinHasPin?.()) center(box, option.dataset.place, Number(select.dataset.centerZoom || 13));
        });
    });
    // Run after map-pin has drawn its maps (same DOMContentLoaded, registered earlier).
    setTimeout(() => document.querySelectorAll('[data-map-pin][data-place]').forEach((box) => {
        if (!box.mapPinHasPin?.()) center(box, box.dataset.place, Number(box.dataset.placeZoom || 13));
    }));
});
</script>
@endonce
