<style>
    .profile-address-select { background: #f4f1eb; cursor: not-allowed; }
</style>
<script>
(() => {
    const baseUrl = 'https://psgc.gitlab.io/api';
    const address = {
        province: @json(auth()->user()->province),
        municipality: @json(auth()->user()->municipality),
        barangay: @json(auth()->user()->barangay)
    };

    const provinceSelect = document.querySelector('[data-profile-province]');
    const municipalitySelect = document.querySelector('[data-profile-municipality]');
    const barangaySelect = document.querySelector('[data-profile-barangay]');
    if (!provinceSelect || !municipalitySelect || !barangaySelect) return;

    const hiddenFields = {
        province: document.querySelector('[data-profile-hidden="province"]'),
        municipality: document.querySelector('[data-profile-hidden="municipality"]'),
        barangay: document.querySelector('[data-profile-hidden="barangay"]')
    };

    const normalizeName = value => String(value || '')
        .toLowerCase()
        .replace(/\bcity of\b/g, '')
        .replace(/\b(city|municipality)\b/g, '')
        .replace(/[^a-z0-9]/g, '');

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
        select.disabled = false;
    };

    const fallback = (select, value, placeholder) => {
        select.innerHTML = `<option value="">${placeholder}</option>`;
        if (value) select.add(new Option(value, value, true, true));
        select.disabled = true;
    };

    const loadAddress = async () => {
        try {
            const provinces = await fetch(`${baseUrl}/provinces/`).then(response => response.json());
            setOptions(provinceSelect, provinces, '-- Select Province --', address.province);
            const provinceCode = provinceSelect.selectedOptions[0]?.dataset.code;
            if (!provinceCode) return;

            const municipalities = await fetch(`${baseUrl}/provinces/${provinceCode}/cities-municipalities/`).then(response => response.json());
            setOptions(municipalitySelect, municipalities, '-- Select Municipality --', address.municipality);
            const municipalityCode = municipalitySelect.selectedOptions[0]?.dataset.code;
            if (!municipalityCode) {
                fallback(municipalitySelect, address.municipality, '-- Select Municipality --');
                fallback(barangaySelect, address.barangay, '-- Select Barangay --');
                return;
            }

            const barangays = await fetch(`${baseUrl}/cities-municipalities/${municipalityCode}/barangays/`).then(response => response.json());
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
    loadAddress();
})();
</script>