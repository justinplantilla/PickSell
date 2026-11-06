/**
 * Province → city/municipality → barangay selects for address-book forms ([data-address-picker]),
 * from the PSGC API like registration. Selects post names; the server looks the codes up itself.
 * Lists are cached in localStorage. If the API is unreachable the saved values stay selectable.
 */
const BASE = 'https://psgc.gitlab.io/api';
// Metro Manila has districts, not provinces: offer it as one and list its cities by region.
const METRO_MANILA = { code: '130000000', name: 'Metro Manila' };

async function fetchList(url, cacheKey) {
    try {
        const cached = window.localStorage.getItem(cacheKey);
        if (cached) return JSON.parse(cached);
    } catch { /* storage blocked: fetch instead */ }

    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 8000);
    try {
        const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(`Address API returned ${response.status}`);
        const data = await response.json();
        try { window.localStorage.setItem(cacheKey, JSON.stringify(data)); } catch { /* full or blocked */ }
        return data;
    } finally {
        window.clearTimeout(timeout);
    }
}

const normalize = value => String(value || '').toLowerCase().replace(/\bcity of\b/g, '').replace(/\b(city|municipality|barangay)\b/g, '').replace(/[^a-z0-9]/g, '');

function fill(select, items, placeholder, wanted) {
    select.innerHTML = `<option value="">${placeholder}</option>`;
    [...items].sort((a, b) => a.name.localeCompare(b.name)).forEach(item => {
        const option = new Option(item.name, item.name);
        option.dataset.code = item.code;
        option.selected = wanted !== '' && normalize(item.name) === normalize(wanted);
        select.add(option);
    });
    select.disabled = false;
}

function reset(select, placeholder) {
    select.innerHTML = `<option value="">${placeholder}</option>`;
    select.disabled = true;
}

function initPicker(root) {
    const province = root.querySelector('[data-address-level="province"]');
    const municipality = root.querySelector('[data-address-level="municipality"]');
    const barangay = root.querySelector('[data-address-level="barangay"]');
    const saved = { province: root.dataset.province || '', municipality: root.dataset.municipality || '', barangay: root.dataset.barangay || '' };

    const loadBarangays = async wanted => {
        reset(barangay, 'Loading barangays...');
        const code = municipality.selectedOptions[0]?.dataset.code;
        if (!code) return reset(barangay, 'Select a city first');
        fill(barangay, await fetchList(`${BASE}/cities-municipalities/${code}/barangays/`, `picksell.psgc.barangays.${code}`), '-- Select barangay --', wanted);
    };
    const loadCities = async (wanted, wantedBarangay) => {
        reset(municipality, 'Loading cities...');
        reset(barangay, 'Select a city first');
        const code = province.selectedOptions[0]?.dataset.code;
        if (!code) return reset(municipality, 'Select a province first');
        const url = code === METRO_MANILA.code ? `${BASE}/regions/${code}/cities-municipalities/` : `${BASE}/provinces/${code}/cities-municipalities/`;
        fill(municipality, await fetchList(url, `picksell.psgc.municipalities.${code}`), '-- Select city / municipality --', wanted);
        if (municipality.value) await loadBarangays(wantedBarangay);
    };

    province.addEventListener('change', () => loadCities('', '').catch(() => {}));
    municipality.addEventListener('change', () => loadBarangays('').catch(() => {}));

    (async () => {
        try {
            fill(province, [...await fetchList(`${BASE}/provinces/`, 'picksell.psgc.provinces'), METRO_MANILA], '-- Select province --', saved.province);
            if (province.value) await loadCities(saved.municipality, saved.barangay);
        } catch {
            // Offline: keep whatever was saved so an edit can still be submitted.
            [[province, saved.province], [municipality, saved.municipality], [barangay, saved.barangay]].forEach(([select, value]) => {
                select.innerHTML = value ? '' : '<option value="">Address list unavailable, try again later</option>';
                if (value) select.add(new Option(value, value, true, true));
                select.disabled = !value;
            });
        }
    })();
}

document.querySelectorAll('[data-address-picker]').forEach(initPicker);
