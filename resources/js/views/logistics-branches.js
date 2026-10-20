const provinceSelect = document.getElementById('hub-province');
const municipalitySelect = document.getElementById('hub-municipality');

if (provinceSelect && municipalitySelect) {
    const apiBase = 'https://psgc.gitlab.io/api';
    const loadJson = async url => {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`Location lookup failed (${response.status}).`);
        return response.json();
    };
    const populate = (select, rows, prompt) => {
        select.replaceChildren(new Option(prompt, ''));
        rows.sort((first, second) => first.name.localeCompare(second.name)).forEach(row => {
            const option = new Option(row.name, row.name);
            option.dataset.code = row.code;
            select.add(option);
        });
    };
    const provinces = loadJson(`${apiBase}/provinces/`).then(rows => {
        populate(provinceSelect, rows, 'Select province');
        if (provinceSelect.dataset.selected) {
            provinceSelect.value = provinceSelect.dataset.selected;
            return loadMunicipalities(provinceSelect.dataset.selected, municipalitySelect.dataset.selected);
        }
    });
    const loadMunicipalities = async (provinceName, selectedName = '') => {
        const selected = [...provinceSelect.options].find(option => option.value === provinceName);
        municipalitySelect.disabled = true;
        municipalitySelect.replaceChildren(new Option('Loading municipalities...', ''));
        if (!selected?.dataset.code) return;
        const rows = await loadJson(`${apiBase}/provinces/${selected.dataset.code}/cities-municipalities/`);
        populate(municipalitySelect, rows, 'Select municipality');
        municipalitySelect.disabled = false;
        if (selectedName) municipalitySelect.value = selectedName;
    };

    provinceSelect.addEventListener('change', async () => {
        await loadMunicipalities(provinceSelect.value);
    });

    provinces.catch(error => {
        municipalitySelect.disabled = true;
        municipalitySelect.replaceChildren(new Option('Could not load municipalities', ''));
        const alert = document.createElement('p');
        alert.className = 'alert alert-error';
        alert.textContent = error.message;
        provinceSelect.closest('form')?.prepend(alert);
    });
}
