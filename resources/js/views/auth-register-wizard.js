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
        [registerForm.querySelector('.notice-box'), registerForm.querySelector('.confirm-group'), submitButton]
    ];
    const wizardPanels = wizardGroups.map((nodes, index) => {
        const panel = document.createElement('div');
        panel.className = 'wizard-panel' + (index === 0 ? ' active' : '');
        nodes.filter(Boolean).forEach(node => panel.appendChild(node));
        registerForm.appendChild(panel);
        return panel;
    });
    let currentWizardStep = 0;

    // Buyers, sellers and logistics providers verify their email with a one-time code on the Account step before they can continue.
    const emailOtp = (() => {
        const box = document.getElementById('emailOtp');
        const emailInput = registerForm.querySelector('input[name="email"]');
        if (!box || !emailInput) return { toggle() {}, blocksStep: () => false };
        const sendButton = document.getElementById('otpSend');
        const entry = document.getElementById('otpEntry');
        const codeInput = document.getElementById('otpCode');
        const verifyButton = document.getElementById('otpVerify');
        const status = document.getElementById('otpStatus');
        const csrf = registerForm.querySelector('input[name="_token"]')?.value;
        let active = false;
        let verifiedEmail = (box.dataset.verifiedEmail || '').toLowerCase();
        let cooldownTimer = null;
        const currentEmail = () => emailInput.value.trim().toLowerCase();
        const isVerified = () => verifiedEmail !== '' && verifiedEmail === currentEmail();
        const say = (message, tone = '') => { status.textContent = message; status.className = 'email-otp-status' + (tone ? ' is-' + tone : ''); };
        const post = async (url, body) => {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body),
            });
            const data = await response.json().catch(() => ({}));
            const message = data.errors ? Object.values(data.errors)[0][0] : data.message;
            return { ok: response.ok, status: response.status, data, message };
        };
        const cooldown = seconds => {
            clearInterval(cooldownTimer);
            let left = seconds;
            sendButton.disabled = true;
            const tick = () => {
                if (left <= 0) { clearInterval(cooldownTimer); sendButton.disabled = false; sendButton.textContent = 'Resend code'; return; }
                sendButton.textContent = `Resend in ${left}s`;
                left--;
            };
            tick();
            cooldownTimer = setInterval(tick, 1000);
        };
        const render = () => {
            const verified = isVerified();
            box.classList.toggle('is-verified', verified);
            sendButton.hidden = verified;
            entry.hidden = verified || entry.dataset.shown !== 'true';
            if (verified) say('✓ ' + currentEmail() + ' is verified.', 'success');
        };
        const reset = () => {
            entry.dataset.shown = 'false';
            codeInput.value = '';
            clearInterval(cooldownTimer);
            sendButton.disabled = false;
            sendButton.textContent = 'Send code';
            say('');
            render();
        };

        sendButton.addEventListener('click', async () => {
            if (!emailInput.checkValidity()) { emailInput.reportValidity(); return; }
            sendButton.disabled = true;
            say('Sending code…');
            const firstName = registerForm.querySelector('input[name="first_name"]')?.value || '';
            const result = await post(box.dataset.sendUrl, { email: currentEmail(), first_name: firstName }).catch(() => null);
            if (!result) { sendButton.disabled = false; say('Could not reach the server. Check your connection and try again.', 'error'); return; }
            if (result.data.verified) { verifiedEmail = currentEmail(); render(); return; }
            if (!result.ok) {
                say(result.message || 'The code could not be sent.', 'error');
                if (result.status === 429 && result.data.wait) cooldown(result.data.wait); else sendButton.disabled = false;
                return;
            }
            entry.dataset.shown = 'true';
            render();
            say(result.message);
            codeInput.focus();
            cooldown(result.data.wait || 60);
        });

        codeInput.addEventListener('input', () => { codeInput.value = codeInput.value.replace(/\D/g, '').slice(0, 6); });
        codeInput.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); verifyButton.click(); } });
        verifyButton.addEventListener('click', async () => {
            if (!/^\d{6}$/.test(codeInput.value)) { say('Enter the 6-digit code from the email.', 'error'); codeInput.focus(); return; }
            verifyButton.disabled = true;
            say('Checking code…');
            const result = await post(box.dataset.verifyUrl, { email: currentEmail(), code: codeInput.value }).catch(() => null);
            verifyButton.disabled = false;
            if (!result) { say('Could not reach the server. Check your connection and try again.', 'error'); return; }
            if (!result.ok || !result.data.verified) { say(result.message || 'That code did not work.', 'error'); codeInput.select(); return; }
            verifiedEmail = currentEmail();
            clearInterval(cooldownTimer);
            render();
        });

        emailInput.addEventListener('input', () => { if (!isVerified()) reset(); });

        return {
            toggle(role) { active = ['buyer', 'seller', 'logistics'].includes(role); box.hidden = !active; render(); },
            blocksStep(panel) {
                if (!active || !panel.contains(box) || isVerified()) return false;
                if (!emailInput.checkValidity()) { emailInput.reportValidity(); return true; }
                say('Verify your email with the 6-digit code before continuing.', 'error');
                (entry.hidden ? sendButton : codeInput).focus();
                return true;
            },
        };
    })();

    const validateWizardStep = () => {
        const fields = [...wizardPanels[currentWizardStep].querySelectorAll('input, select, textarea')]
            .filter(field => field.required && !field.disabled && (field.type !== 'radio' || field.checked) && (field.type === 'radio' || field.type === 'file' || field.offsetParent !== null));
        const invalidField = fields.find(field => !field.checkValidity());
        if (invalidField) { invalidField.reportValidity(); return false; }
        if (!serviceAreasValid(wizardPanels[currentWizardStep])) return false;
        return !emailOtp.blocksStep(wizardPanels[currentWizardStep]);
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
        const actions = document.createElement('div');
        actions.className = 'wizard-actions' + (index === wizardPanels.length - 1 ? ' review-actions' : '');
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

    // Logistics providers pick at least one region they serve.
    const serviceAreas = document.getElementById('serviceAreas');
    const serviceAreasError = document.getElementById('serviceAreasError');
    const serviceAreasValid = panel => {
        if (!panel.contains(serviceAreas) || serviceAreas.style.display === 'none') return true;
        const valid = serviceAreas.querySelector('input:checked') !== null;
        serviceAreasError.hidden = valid;
        if (!valid) serviceAreas.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return valid;
    };
    serviceAreas.addEventListener('change', () => { if (serviceAreas.querySelector('input:checked')) serviceAreasError.hidden = true; });

    const updateProviderFields = () => {
        const logisticsSelected = selectedRole() === 'logistics';
        const businessName = document.querySelector('input[name="business_name"]');
        document.getElementById('businessNameLabel').innerHTML = (logisticsSelected ? 'Company Name' : 'Shop Name') + ' <span class="req">*</span>';
        businessName.placeholder = logisticsSelected ? 'e.g. Bilis Hatid Express Inc.' : "e.g. Juan's Store";
        // Company names are 2 to 100 characters; shop names 3 to 30.
        if (logisticsSelected) { businessName.minLength = 2; businessName.maxLength = 100; } else { businessName.minLength = 3; businessName.maxLength = 30; }
    };
    const syncRoleFields = role => {
        const sellerType = document.getElementById('seller_type').value;
        registerForm.querySelectorAll('[data-roles]').forEach(group => {
            const sellerTypes = group.dataset.sellerTypes?.split(' ');
            const shown = group.dataset.roles.split(' ').includes(role)
                && (role !== 'seller' || !sellerTypes || sellerTypes.includes(sellerType));
            group.style.display = shown ? 'block' : 'none';
            group.querySelectorAll('[data-required]').forEach(field => { field.required = shown; });
        });
    };
    const updateRegistrationNote = role => {
        const messages = { courier: 'Courier applications are reviewed by the station hub for your town. Please wait for the approval result before logging in.', logistics: 'Logistics provider applications are reviewed by the PickSell Admin team. Please wait for approval before logging in.', seller: 'Seller applications are reviewed by the PickSell Admin team. Please wait for the approval result before logging in.', buyer: 'Your registration will be reviewed by PickSell. Please wait for the approval result before logging in.' };
        document.getElementById('registrationNote').textContent = messages[role] || messages.buyer;
    };
    const updateRole = role => {
        roleBtns.forEach(button => button.classList.remove('active'));
        document.getElementById('role-' + role)?.classList.add('active');
        sellerFields.style.display = ['seller', 'logistics'].includes(role) ? 'block' : 'none';
        courierFields.style.display = role === 'courier' ? 'block' : 'none';
        document.querySelectorAll('#courierFields input, #courierFields select').forEach(element => { element.required = role === 'courier'; });
        syncRoleFields(role); updateProviderFields(); updateRegistrationNote(role); emailOtp.toggle(role);
    };
    const selectedRole = () => registerForm.querySelector('input[name="role"]:checked')?.value;
    roleBtns.forEach(button => button.addEventListener('click', () => { const role = button.querySelector('input').value; button.querySelector('input').checked = true; updateRole(role); }));
    document.querySelector('input[name="sec_registration_no"]').addEventListener('input', function () { this.value = this.value.toUpperCase(); });
    document.getElementById('seller_type').addEventListener('change', () => syncRoleFields(selectedRole()));
    passwordInput.addEventListener('input', () => {
        const password = passwordInput.value;
        const score = [password.length >= 8, /[a-z]/.test(password) && /[A-Z]/.test(password), /\d/.test(password), /[^A-Za-z0-9]/.test(password)].filter(Boolean).length;
        passwordMeter.classList.toggle('visible', password.length > 0);
        passwordMeterBar.style.width = ['0%', '25%', '50%', '75%', '100%'][score];
        passwordMeterBar.style.background = ['#d9534f', '#d9534f', '#d99a3d', '#79a85b', '#4c7a5e'][score];
        const missing = [
            [password.length >= 8, '8+ characters'],
            [/[A-Z]/.test(password), 'an uppercase letter'],
            [/[a-z]/.test(password), 'a lowercase letter'],
            [/\d/.test(password), 'a number'],
            [/[^A-Za-z0-9]/.test(password), 'a special character'],
        ].filter(([ok]) => !ok).map(([, label]) => label);
        passwordHint.textContent = password.length === 0 ? '' : (missing.length ? 'Still needs: ' + missing.join(', ') + '.' : 'Strong password.');
    });
    // Block repeat clicks so only one registration (and one verification email) is sent.
    registerForm.addEventListener('submit', event => {
        if (registerForm.dataset.submitting === 'true') { event.preventDefault(); return; }
        registerForm.dataset.submitting = 'true';
        submitButton.classList.add('is-submitting'); submitButton.textContent = 'Submitting...';
        setTimeout(() => { submitButton.disabled = true; }, 0);
    });
    updateRole(registerConfig.dataset.role);

    document.getElementById('birthday').addEventListener('change', function () { const dob = new Date(this.value); const today = new Date(); let age = today.getFullYear() - dob.getFullYear(); const month = today.getMonth() - dob.getMonth(); if (month < 0 || (month === 0 && today.getDate() < dob.getDate())) age--; document.getElementById('age').value = age >= 0 ? age : ''; });
    // Documents must be JPG, PNG or PDF files of at most 5 MB; anything else is cleared with a message.
    const ACCEPTED_FILE = /\.(jpe?g|png|pdf)$/i;
    const MAX_FILE_BYTES = 5 * 1024 * 1024;
    ['id_upload', 'business_permit', 'or_cr_upload', 'dti_certificate', 'sec_certificate', 'bir_certificate'].forEach(inputId => document.getElementById(inputId).addEventListener('change', function () {
        const group = this.closest('.form-group');
        const file = this.files[0];
        const problem = !file ? '' : !ACCEPTED_FILE.test(file.name) ? `${file.name} was not accepted. Only JPG, PNG and PDF files are accepted.` : file.size > MAX_FILE_BYTES ? `${file.name} was not accepted. Each file must be 5 MB or smaller.` : '';
        let error = group.querySelector('.field-error[data-file-error]');
        if (!error) { error = document.createElement('div'); error.className = 'field-error'; error.dataset.fileError = ''; group.appendChild(error); }
        error.textContent = problem;
        error.hidden = !problem;
        if (problem) this.value = '';
        document.getElementById(`${inputId}_name`).textContent = this.files[0]?.name || 'Click to upload (JPG, PNG, PDF — max 5MB)';
    }));

    const BASE = 'https://psgc.gitlab.io/api';
    const fetchJSON = url => fetch(url).then(response => response.json());
    const populateSelect = (select, items, valueKey, labelKey, placeholder) => { select.innerHTML = `<option value="">${placeholder}</option>`; items.sort((first, second) => first[labelKey].localeCompare(second[labelKey])).forEach(item => { const option = document.createElement('option'); option.value = item[labelKey]; option.dataset.code = item[valueKey]; option.textContent = item[labelKey]; select.appendChild(option); }); };
    const restore = (select, value) => { if (!value) return; [...select.options].forEach(option => { if (option.value === value) option.selected = true; }); select.dispatchEvent(new Event('change')); };
    // Region → Province → City / Municipality → Barangay; changing one clears everything below it.
    // Metro Manila (NCR) has no provinces, so it is offered as one and its cities are listed by region.
    const NCR_CODE = '130000000';
    fetchJSON(`${BASE}/regions/`).then(data => { const region = document.getElementById('region'); populateSelect(region, data.map(item => ({ code: item.code, name: item.regionName && item.regionName !== item.name ? `${item.regionName} (${item.name})` : item.name })), 'code', 'name', '-- Select Region --'); restore(region, registerConfig.dataset.region); });
    document.getElementById('region').addEventListener('change', async function () {
        const code = this.options[this.selectedIndex]?.dataset.code;
        const province = document.getElementById('province'); const municipality = document.getElementById('municipality'); const barangay = document.getElementById('barangay');
        province.innerHTML = '<option value="">-- Select Province --</option>'; municipality.innerHTML = '<option value="">-- Select Municipality --</option>'; barangay.innerHTML = '<option value="">-- Select Barangay --</option>';
        province.disabled = true; municipality.disabled = true; barangay.disabled = true;
        if (!code) return;
        const data = code === NCR_CODE ? [{ code: `region:${NCR_CODE}`, name: 'Metro Manila' }] : await fetchJSON(`${BASE}/regions/${code}/provinces/`);
        populateSelect(province, data, 'code', 'name', '-- Select Province --'); province.disabled = false; restore(province, registerConfig.dataset.province);
    });
    document.getElementById('province').addEventListener('change', async function () { const code = this.options[this.selectedIndex]?.dataset.code; const municipality = document.getElementById('municipality'); const barangay = document.getElementById('barangay'); municipality.innerHTML = '<option value="">-- Select Municipality --</option>'; barangay.innerHTML = '<option value="">-- Select Barangay --</option>'; municipality.disabled = true; barangay.disabled = true; if (!code) return; const data = await fetchJSON(code.startsWith('region:') ? `${BASE}/regions/${code.slice(7)}/cities-municipalities/` : `${BASE}/provinces/${code}/cities-municipalities/`); populateSelect(municipality, data, 'code', 'name', '-- Select Municipality --'); municipality.disabled = false; restore(municipality, registerConfig.dataset.municipality); });
    document.getElementById('municipality').addEventListener('change', async function () { const code = this.options[this.selectedIndex]?.dataset.code; const barangay = document.getElementById('barangay'); barangay.innerHTML = '<option value="">-- Select Barangay --</option>'; barangay.disabled = true; if (!code) return; const data = await fetchJSON(`${BASE}/cities-municipalities/${code}/barangays/`); populateSelect(barangay, data, 'code', 'name', '-- Select Barangay --'); barangay.disabled = false; restore(barangay, registerConfig.dataset.barangay); });
}
