const addressConfig = document.querySelector('[data-profile-address]');
const provinceSelect = document.querySelector('[data-profile-province]');
const municipalitySelect = document.querySelector('[data-profile-municipality]');
const barangaySelect = document.querySelector('[data-profile-barangay]');

if (addressConfig && provinceSelect && municipalitySelect && barangaySelect) {
    const baseUrl = 'https://psgc.gitlab.io/api';
    const address = {
        province: addressConfig.dataset.province || '',
        municipality: addressConfig.dataset.municipality || '',
        barangay: addressConfig.dataset.barangay || ''
    };
    const hiddenFields = {
        province: document.querySelector('[data-profile-hidden="province"]'),
        municipality: document.querySelector('[data-profile-hidden="municipality"]'),
        barangay: document.querySelector('[data-profile-hidden="barangay"]')
    };
    const fetchJson = async (url, cacheKey) => {
        const cached = window.localStorage.getItem(cacheKey);
        if (cached) return JSON.parse(cached);

        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 6000);
        try {
            const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`Address API returned ${response.status}`);
            const data = await response.json();
            window.localStorage.setItem(cacheKey, JSON.stringify(data));
            return data;
        } finally {
            window.clearTimeout(timeout);
        }
    };
    const normalizeName = value => String(value || '').toLowerCase().replace(/\bcity of\b/g, '').replace(/\b(city|municipality|barangay)\b/g, '').replace(/[^a-z0-9]/g, '');
    const setOptions = (select, items, placeholder, selectedValue) => {
        select.innerHTML = `<option value="">${placeholder}</option>`;
        items.sort((first, second) => first.name.localeCompare(second.name));
        const selectedName = normalizeName(selectedValue);
        items.forEach(item => {
            const option = new Option(item.name, item.name);
            option.dataset.code = item.code;
            option.selected = normalizeName(item.name) === selectedName;
            select.add(option);
        });
        if (selectedValue && !select.selectedOptions[0]?.dataset.code) select.add(new Option(selectedValue, selectedValue, true, true));
        select.disabled = false;
    };
    const fallback = (select, value, placeholder) => {
        select.innerHTML = `<option value="">${placeholder}</option>`;
        if (value) select.add(new Option(value, value, true, true));
        select.disabled = true;
    };
    const loadAddress = async () => {
        try {
            const provinces = await fetchJson(`${baseUrl}/provinces/`, 'picksell.psgc.provinces');
            setOptions(provinceSelect, provinces, '-- Select Province --', address.province);
            const provinceCode = provinceSelect.selectedOptions[0]?.dataset.code;
            if (!provinceCode) return;
            const municipalities = await fetchJson(`${baseUrl}/provinces/${provinceCode}/cities-municipalities/`, `picksell.psgc.municipalities.${provinceCode}`);
            setOptions(municipalitySelect, municipalities, '-- Select Municipality --', address.municipality);
            const municipalityCode = municipalitySelect.selectedOptions[0]?.dataset.code;
            if (!municipalityCode) {
                fallback(municipalitySelect, address.municipality, '-- Select Municipality --');
                fallback(barangaySelect, address.barangay, '-- Select Barangay --');
                return;
            }
            const barangays = await fetchJson(`${baseUrl}/cities-municipalities/${municipalityCode}/barangays/`, `picksell.psgc.barangays.${municipalityCode}`);
            setOptions(barangaySelect, barangays, '-- Select Barangay --', address.barangay);
        } catch {
            fallback(provinceSelect, address.province, '-- Select Province --');
            fallback(municipalitySelect, address.municipality, '-- Select Municipality --');
            fallback(barangaySelect, address.barangay, '-- Select Barangay --');
        }
    };
    [provinceSelect, municipalitySelect, barangaySelect].forEach(select => {
        select.disabled = true;
        select.classList.add('profile-address-select');
    });
    Object.entries(hiddenFields).forEach(([key, field]) => { if (field) field.value = address[key]; });
    fallback(municipalitySelect, address.municipality, 'Loading municipalities...');
    fallback(barangaySelect, address.barangay, 'Loading barangays...');
    loadAddress();
}
