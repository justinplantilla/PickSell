const modal = document.getElementById('branchModal');
const form = document.getElementById('branchModalForm');
if (modal && form) {
    const method = document.getElementById('branchModalMethod');
    const title = document.getElementById('branchModalTitle');
    const submit = document.getElementById('branchModalSubmit');
    const storeAction = form.action;
    const field = id => document.getElementById(id);
    const apiBase = 'https://psgc.gitlab.io/api';
    const fetchJson = url => fetch(url).then(response => response.json());
    const populate = (select, items, placeholder) => {
        select.innerHTML = `<option value="">${placeholder}</option>`;
        items.sort((first, second) => first.name.localeCompare(second.name)).forEach(item => { const option = document.createElement('option'); option.value = item.name; option.dataset.code = item.code; option.textContent = item.name; select.appendChild(option); });
    };
    const loadMunicipalities = async (provinceName, municipalityName = '') => {
        const province = [...field('branchProvince').options].find(option => option.value === provinceName);
        const municipality = field('branchMunicipality'); municipality.disabled = true; municipality.innerHTML = '<option value="">Loading municipalities...</option>';
        if (!province?.dataset.code) return;
        const cities = await fetchJson(`${apiBase}/provinces/${province.dataset.code}/cities-municipalities/`);
        populate(municipality, cities, '-- Select Municipality --'); municipality.disabled = false;
        if (municipalityName) municipality.value = municipalityName;
    };
    const provincesReady = fetchJson(`${apiBase}/provinces/`).then(provinces => populate(field('branchProvince'), provinces, '-- Select Province --'));
    field('branchProvince').addEventListener('change', () => loadMunicipalities(field('branchProvince').value));
    const openModal = async (mode, button) => {
        const edit = mode === 'edit'; await provincesReady;
        title.textContent = edit ? 'Edit Branch' : 'Add Municipality Branch'; submit.textContent = edit ? 'Save Changes' : 'Add Branch';
        form.action = edit ? button.dataset.action : storeAction; method.value = edit ? 'PUT' : 'POST';
        field('branchName').value = edit ? button.dataset.name : ''; field('branchProvince').value = edit ? button.dataset.province : '';
        await loadMunicipalities(edit ? button.dataset.province : ''); if (!edit) field('branchMunicipality').value = '';
        if (edit) field('branchMunicipality').value = button.dataset.municipality;
        field('branchAddress').value = edit ? button.dataset.address : ''; field('branchManager').value = edit ? button.dataset.manager : ''; field('branchStatus').value = edit ? button.dataset.status : 'active';
        modal.classList.add('open'); modal.setAttribute('aria-hidden', 'false'); field('branchName').focus();
    };
    const closeModal = () => { modal.classList.remove('open'); modal.setAttribute('aria-hidden', 'true'); };
    document.querySelectorAll('[data-open-branch-modal]').forEach(button => button.addEventListener('click', () => openModal(button.dataset.openBranchModal, button)));
    document.querySelectorAll('[data-close-branch-modal]').forEach(button => button.addEventListener('click', closeModal));
    modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeModal(); });
}
