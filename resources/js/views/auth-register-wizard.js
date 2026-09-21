const registerForm = document.getElementById('registerForm');
const registerConfig = document.querySelector('[data-register-config]');
if (registerForm && registerConfig) {
    const roleBtns = document.querySelectorAll('.role-btn');
    const sellerFields = document.getElementById('sellerFields');
    const courierFields = document.getElementById('courierFields');
    const submitButton = registerForm.querySelector('.btn-submit');
    const passwordInput = registerForm.querySelector('input[name="password"]');
    const passwordMeter = registerForm.querySelector('.password-meter');
    const passwordMeterBar = registerForm.querySelector('.password-meter-bar');
    const passwordHint = registerForm.querySelector('.password-hint');
    const wizardProgressItems = [...document.querySelectorAll('.wizard-progress-item')];
    const wizardSections = [...registerForm.querySelectorAll('.form-section')];
    const wizardGroups = [
        [registerForm.querySelector('.role-selector')], [wizardSections[0]],
        [wizardSections[1], wizardSections[2]],
        [wizardSections[3], wizardSections[4], wizardSections[5]],
        [registerForm.querySelector('.notice-box'), registerForm.querySelector('.confirm-row'), submitButton]
    ];
    const wizardPanels = wizardGroups.map((nodes, index) => {
        const panel = document.createElement('div');
        panel.className = 'wizard-panel' + (index === 0 ? ' active' : '');
        nodes.filter(Boolean).forEach(node => panel.appendChild(node));
        registerForm.appendChild(panel);
        return panel;
    });
    let currentWizardStep = 0;

    const validateWizardStep = () => {
        const fields = [...wizardPanels[currentWizardStep].querySelectorAll('input, select, textarea')]
            .filter(field => field.required && !field.disabled && (field.type !== 'radio' || field.checked) && (field.type === 'radio' || field.type === 'file' || field.offsetParent !== null));
        const invalidField = fields.find(field => !field.checkValidity());
        if (invalidField) { invalidField.reportValidity(); return false; }
        return true;
    };
    const setWizardStep = step => {
        currentWizardStep = step;
        wizardPanels.forEach((panel, index) => panel.classList.toggle('active', index === step));
        wizardProgressItems.forEach((item, index) => { item.classList.toggle('active', index === step); item.classList.toggle('complete', index < step); });
        wizardPanels[step].querySelector('input, select, textarea, button')?.focus({ preventScroll: true });
        window.scrollTo({ top: registerForm.offsetTop - 24, behavior: 'smooth' });
    };
    const setContinueLoading = (button, loading) => {
        button.classList.toggle('is-loading', loading); button.disabled = loading;
        button.setAttribute('aria-busy', loading ? 'true' : 'false'); button.textContent = loading ? 'Loading...' : 'Continue';
    };
    wizardPanels.forEach((panel, index) => {
        const actions = document.createElement('div'); actions.className = 'wizard-actions';
        if (index > 0) {
            const backButton = document.createElement('button'); backButton.type = 'button'; backButton.className = 'wizard-back'; backButton.textContent = 'Back';
            backButton.addEventListener('click', () => setWizardStep(currentWizardStep - 1)); actions.appendChild(backButton);
        }
        if (index < wizardPanels.length - 1) {
            const nextButton = document.createElement('button'); nextButton.type = 'button'; nextButton.className = 'wizard-next'; nextButton.textContent = 'Continue';
            nextButton.addEventListener('click', () => { if (!validateWizardStep()) return; setContinueLoading(nextButton, true); window.setTimeout(() => { setContinueLoading(nextButton, false); setWizardStep(currentWizardStep + 1); }, 420); });
            actions.appendChild(nextButton);
        } else { submitButton.style.marginTop = '0'; actions.appendChild(submitButton); }
        panel.appendChild(actions);
    });
    registerForm.querySelector('input[name="role"]')?.setAttribute('required', 'required');

    const updateProviderFields = () => {
        const providerType = document.getElementById('provider_type').value;
        const logisticsSelected = document.querySelector('input[name="role"]:checked')?.value === 'logistics';
        const businessType = document.getElementById('line_of_business');
        const selectedType = businessType.value;
        const typeOptions = logisticsSelected ? ['Local Delivery', 'Same-Day Delivery', 'Express Delivery', 'Freight and Cargo', 'Other'] : ['Electronics', 'Fashion', 'Home & Living', 'Sports', 'Beauty', 'Food & Grocery', 'Books', 'Toys', 'Others'];
        businessType.innerHTML = '<option value="">-- Select ' + (logisticsSelected ? 'Service Type' : 'Category') + ' --</option>' + typeOptions.map(option => '<option value="' + option + '">' + option + '</option>').join('');
        if (typeOptions.includes(selectedType)) businessType.value = selectedType;
        document.getElementById('businessNameLabel').innerHTML = logisticsSelected && providerType === 'individual' ? 'Provider Name <span class="req">*</span>' : 'Business Name <span class="req">*</span>';
        document.getElementById('businessTypeLabel').innerHTML = logisticsSelected && providerType === 'individual' ? 'Service Type <span class="req">*</span>' : 'Line of Business <span class="req">*</span>';
        document.querySelector('input[name="business_name"]').placeholder = logisticsSelected && providerType === 'individual' ? 'e.g. Juan Dela Cruz Delivery' : "e.g. Juan's Store";
    };
    const updateRegistrationNote = role => {
        const messages = { courier: 'Courier applications are reviewed by the PickSell Logistics team. Please wait for the approval result before logging in.', logistics: 'Logistics provider applications are reviewed by the PickSell Admin team. Please wait for approval before logging in.', seller: 'Seller applications are reviewed by the PickSell Admin team. Please wait for the approval result before logging in.', buyer: 'Your registration will be reviewed by PickSell. Please wait for the approval result before logging in.' };
        document.getElementById('registrationNote').textContent = messages[role] || messages.buyer;
    };
    const updateRole = role => {
        roleBtns.forEach(button => button.classList.remove('active'));
        document.getElementById('role-' + role)?.classList.add('active');
        sellerFields.style.display = ['seller', 'logistics'].includes(role) ? 'block' : 'none';
        courierFields.style.display = role === 'courier' ? 'block' : 'none';
        document.getElementById('providerTypeFields').style.display = role === 'logistics' ? 'block' : 'none';
        document.querySelectorAll('#sellerFields input, #sellerFields select').forEach(element => { if (element.id !== 'provider_type') element.required = ['seller', 'logistics'].includes(role); });
        document.querySelectorAll('#courierFields input, #courierFields select').forEach(element => { element.required = role === 'courier'; });
        document.getElementById('provider_type').required = role === 'logistics';
        updateProviderFields(); updateRegistrationNote(role);
    };
    roleBtns.forEach(button => button.addEventListener('click', () => { const role = button.querySelector('input').value; button.querySelector('input').checked = true; updateRole(role); }));
    document.getElementById('provider_type').addEventListener('change', updateProviderFields);
    passwordInput.addEventListener('input', () => {
        const password = passwordInput.value;
        const score = [password.length >= 8, /[a-z]/.test(password) && /[A-Z]/.test(password), /\d/.test(password), /[^A-Za-z0-9]/.test(password)].filter(Boolean).length;
        passwordMeter.classList.toggle('visible', password.length > 0);
        passwordMeterBar.style.width = ['0%', '25%', '50%', '75%', '100%'][score];
        passwordMeterBar.style.background = ['#d9534f', '#d9534f', '#d99a3d', '#79a85b', '#4c7a5e'][score];
        passwordHint.textContent = ['', 'Use at least 8 characters.', 'Add upper and lowercase letters.', 'Add a number.', 'Strong password.'][score];
    });
    registerForm.addEventListener('submit', () => { submitButton.classList.add('is-submitting'); submitButton.textContent = 'Submitting...'; });
    updateRole(registerConfig.dataset.role);

    document.getElementById('birthday').addEventListener('change', function () { const dob = new Date(this.value); const today = new Date(); let age = today.getFullYear() - dob.getFullYear(); const month = today.getMonth() - dob.getMonth(); if (month < 0 || (month === 0 && today.getDate() < dob.getDate())) age--; document.getElementById('age').value = age >= 0 ? age : ''; });
    ['id_upload', 'business_permit', 'or_cr_upload'].forEach(inputId => document.getElementById(inputId).addEventListener('change', function () { document.getElementById(`${inputId}_name`).textContent = this.files[0]?.name || 'Click to upload'; }));

    const BASE = 'https://psgc.gitlab.io/api';
    const fetchJSON = url => fetch(url).then(response => response.json());
    const populateSelect = (select, items, valueKey, labelKey, placeholder) => { select.innerHTML = `<option value="">${placeholder}</option>`; items.sort((first, second) => first[labelKey].localeCompare(second[labelKey])).forEach(item => { const option = document.createElement('option'); option.value = item[labelKey]; option.dataset.code = item[valueKey]; option.textContent = item[labelKey]; select.appendChild(option); }); };
    const restore = (select, value) => { if (!value) return; [...select.options].forEach(option => { if (option.value === value) option.selected = true; }); select.dispatchEvent(new Event('change')); };
    fetchJSON(`${BASE}/provinces/`).then(data => { const province = document.getElementById('province'); populateSelect(province, data, 'code', 'name', '-- Select Province --'); restore(province, registerConfig.dataset.province); });
    document.getElementById('province').addEventListener('change', async function () { const code = this.options[this.selectedIndex]?.dataset.code; const municipality = document.getElementById('municipality'); const barangay = document.getElementById('barangay'); municipality.innerHTML = '<option value="">-- Select Municipality --</option>'; barangay.innerHTML = '<option value="">-- Select Barangay --</option>'; municipality.disabled = true; barangay.disabled = true; if (!code) return; const data = await fetchJSON(`${BASE}/provinces/${code}/cities-municipalities/`); populateSelect(municipality, data, 'code', 'name', '-- Select Municipality --'); municipality.disabled = false; restore(municipality, registerConfig.dataset.municipality); });
    document.getElementById('municipality').addEventListener('change', async function () { const code = this.options[this.selectedIndex]?.dataset.code; const barangay = document.getElementById('barangay'); barangay.innerHTML = '<option value="">-- Select Barangay --</option>'; barangay.disabled = true; if (!code) return; const data = await fetchJSON(`${BASE}/cities-municipalities/${code}/barangays/`); populateSelect(barangay, data, 'code', 'name', '-- Select Barangay --'); barangay.disabled = false; restore(barangay, registerConfig.dataset.barangay); });
}
