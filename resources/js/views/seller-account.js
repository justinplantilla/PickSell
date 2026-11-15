/**
 * Seller My Account: keeps the shop pin in step with the address.
 *
 * - Picking a new province/town/barangay (profile-address:change) moves the pin map's view there
 *   (never the pin itself: riders need the exact door) and asks the seller to re-pin.
 * - A pin dropped but not saved with "Save Pin" is sent along with "Save Changes" too, so it is
 *   not lost when the seller saves the profile form instead.
 */
const pinBox = document.querySelector('[data-map-pin="seller-pin"]');
const profileForm = document.querySelector('form[data-seller-profile-form]');
const note = document.querySelector('[data-pin-address-note]');

// OpenStreetMap knows "Tanauan City" better than PSGC's "City of Tanauan".
const searchable = name => name.replace(/City of ([^,]+)/i, '$1 City');
async function geocode(place) {
    const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=ph&q='
        + encodeURIComponent(`${searchable(place)}, Philippines`);
    try {
        const hits = await (await fetch(url, { headers: { 'Accept-Language': 'en' } })).json();
        return hits[0] ? { lat: Number(hits[0].lat), lng: Number(hits[0].lon) } : null;
    } catch {
        return null;
    }
}

let lookup = 0;
document.addEventListener('profile-address:change', async event => {
    const { place, barangay, municipality } = event.detail;
    if (!pinBox || !municipality) return;
    const mine = ++lookup;
    const spot = await geocode(place) ?? (barangay ? await geocode([municipality, event.detail.province].filter(Boolean).join(', ')) : null);
    if (!spot || mine !== lookup) return;
    pinBox.dispatchEvent(new CustomEvent('map-pin:center', { detail: { ...spot, zoom: barangay ? 16 : 14 } }));
    if (note) note.hidden = false;
});

if (pinBox && profileForm) {
    const lat = pinBox.querySelector('[data-map-pin-lat]');
    const lng = pinBox.querySelector('[data-map-pin-lng]');
    profileForm.addEventListener('submit', () => {
        profileForm.querySelectorAll('[data-pin-carry]').forEach(input => input.remove());
        const differs = (a, b) => b === '' || Math.abs(Number(a) - Number(b)) > 1e-7;
        const moved = lat.value && lng.value && (differs(lat.value, pinBox.dataset.lat) || differs(lng.value, pinBox.dataset.lng));
        if (!moved) return;
        [['pickup_lat', lat.value], ['pickup_lng', lng.value]].forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            input.dataset.pinCarry = '';
            profileForm.appendChild(input);
        });
    });
}
