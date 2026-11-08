// Logistics company registration: inline email verification (Verify sends a code, a dialog takes it),
// linked address dropdowns, the auto age, document previews and the same checks the server runs.
const form = document.querySelector('[data-register]');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

const NAME = /^[A-Za-zÑñ' -]+$/u;
const INITIAL = /^[A-Za-zÑñ]\.?$/u;
const MOBILE = /^(09\d{9}|\+639\d{9})$/;
const EMAIL = /^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/;
const MAX_FILE = 5 * 1024 * 1024;
const FILE_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];

// Show / hide password, as on the sign-in page.
document.querySelectorAll('[data-password-toggle]').forEach(button => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(show));
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
});

if (form) {
    const $ = selector => form.querySelector(selector);
    const emailInput = $('#email');
    const submit = $('[data-submit]');
    let verified = emailInput.readOnly;

    // ----- Field errors shown in the browser (the server repeats every check) -----
    function setError(input, message) {
        const field = input.closest('.prl-field');
        let error = field.querySelector('.prl-error[data-client]');
        if (!message) {
            error?.remove();
            if (!field.querySelector('.prl-error:not([data-client])')) input.classList.remove('is-invalid');
            return true;
        }
        if (!error) {
            error = Object.assign(document.createElement('span'), { className: 'prl-error' });
            error.dataset.client = '';
            field.append(error);
        }
        error.textContent = message;
        input.classList.add('is-invalid');
        return false;
    }

    function check(input) {
        const value = input.value.trim();
        if (input.type !== 'file' && input.value !== value && input.dataset.rule !== 'password' && input.dataset.rule !== 'confirm') input.value = value;
        if (input.required && !value && input.type !== 'checkbox') return setError(input, 'This field is required.');
        switch (input.dataset.rule) {
            case 'name':
                if (value.length < 2 || value.length > 50) return setError(input, 'Use 2 to 50 characters.');
                if (!NAME.test(value)) return setError(input, 'Use letters, spaces, hyphens and apostrophes only.');
                break;
            case 'initial':
                if (value && !INITIAL.test(value)) return setError(input, 'Enter one letter, optionally followed by a period (e.g. A or A.).');
                break;
            case 'mobile':
                if (!MOBILE.test(value.replace(/\s+/g, ''))) return setError(input, 'Enter a Philippine mobile number like 09171234567 or +639171234567.');
                break;
            case 'password':
                if (!passwordChecks(input.value).every(Boolean)) return setError(input, 'The password does not meet every rule below.');
                break;
            case 'confirm':
                if (input.value !== $('#password').value) return setError(input, 'The passwords do not match.');
                break;
        }
        if (input.id === 'street_address' && value.length < 5) return setError(input, 'Enter at least 5 characters.');
        if (input.id === 'birthday') return checkBirthday(input);
        return setError(input, null);
    }

    form.querySelectorAll('input:not([type=file]):not([type=checkbox]):not([readonly]), select').forEach(input => {
        input.addEventListener('blur', () => input.value && check(input));
        input.addEventListener('change', () => check(input));
    });

    // ----- Age from birthday -----
    const birthday = $('#birthday');
    const age = $('[data-age]');
    function ageOn(date) {
        const today = new Date();
        let years = today.getFullYear() - date.getFullYear();
        if (today.getMonth() < date.getMonth() || (today.getMonth() === date.getMonth() && today.getDate() < date.getDate())) years--;
        return years;
    }
    function checkBirthday(input) {
        age.value = '';
        if (!input.value) return setError(input, 'This field is required.');
        const [y, m, d] = input.value.split('-').map(Number);
        const date = new Date(y, m - 1, d);
        if (!y || date.getFullYear() !== y || date.getMonth() !== m - 1 || date.getDate() !== d) return setError(input, 'Enter a valid date.');
        if (date > new Date()) return setError(input, 'The birthday cannot be in the future.');
        const years = ageOn(date);
        age.value = years;
        if (years < 18) return setError(input, 'You must be at least 18 years old.');
        return setError(input, null);
    }
    birthday.addEventListener('input', () => checkBirthday(birthday));
    if (birthday.value) checkBirthday(birthday);

    // ----- Password rules -----
    function passwordChecks(value) {
        return [value.length >= 8, /[A-Z]/.test(value), /[a-z]/.test(value), /\d/.test(value), /[^A-Za-z0-9]/.test(value)];
    }
    const rules = form.querySelectorAll('[data-password-rules] li');
    $('#password')?.addEventListener('input', event => {
        passwordChecks(event.target.value).forEach((ok, i) => rules[i].classList.toggle('is-met', ok));
        const confirm = $('#password_confirmation');
        if (confirm.value) check(confirm);
    });

    // ----- Linked address dropdowns (PSGC) -----
    const selects = { province: $('#province'), municipality: $('#municipality'), barangay: $('#barangay') };
    const wanted = { province: form.dataset.province, municipality: form.dataset.municipality, barangay: form.dataset.barangay };
    async function load(select, url, placeholder, pick) {
        select.disabled = true;
        select.innerHTML = '<option value="">Loading...</option>';
        try {
            const rows = await (await fetch(url, { headers: { Accept: 'application/json' } })).json();
            select.innerHTML = `<option value="">${placeholder}</option>`;
            rows.forEach(row => select.add(new Option(row.name, row.code, false, row.code === pick)));
            select.disabled = false;
        } catch {
            select.innerHTML = '<option value="">Could not load. Refresh the page.</option>';
        }
    }
    function reset(select, text) {
        select.innerHTML = `<option value="">${text}</option>`;
        select.disabled = true;
    }
    async function loadMunicipalities(pick) {
        reset(selects.barangay, 'Select a city first');
        if (!selects.province.value) return reset(selects.municipality, 'Select a province first');
        await load(selects.municipality, `${form.dataset.municipalitiesUrl}?province=${encodeURIComponent(selects.province.value)}`, 'Select city / municipality', pick);
    }
    async function loadBarangays(pick) {
        if (!selects.municipality.value) return reset(selects.barangay, 'Select a city first');
        await load(selects.barangay, `${form.dataset.barangaysUrl}?municipality=${encodeURIComponent(selects.municipality.value)}`, 'Select barangay', pick);
    }
    selects.province.addEventListener('change', () => loadMunicipalities());
    selects.municipality.addEventListener('change', () => loadBarangays());
    (async () => {
        await load(selects.province, form.dataset.provincesUrl, 'Select province', wanted.province);
        if (selects.province.value) await loadMunicipalities(wanted.municipality);
        if (selects.municipality.value) await loadBarangays(wanted.barangay);
    })();

    // ----- Documents: type, size, name and preview -----
    form.querySelectorAll('[data-file]').forEach(box => {
        const input = box.querySelector('input');
        const preview = box.querySelector('[data-file-preview]');
        const name = box.querySelector('[data-file-name]');
        const meta = box.querySelector('[data-file-meta]');
        const original = { preview: preview.innerHTML, meta: meta.textContent };
        input.addEventListener('change', () => {
            const file = input.files[0];
            box.classList.remove('is-invalid', 'has-file');
            setError(input, null);
            preview.innerHTML = original.preview;
            if (!file) { name.textContent = 'Choose a file'; meta.textContent = original.meta; return; }
            const extension = file.name.split('.').pop().toLowerCase();
            if (!FILE_TYPES.includes(file.type) || !['jpg', 'jpeg', 'png', 'pdf'].includes(extension)) {
                input.value = '';
                box.classList.add('is-invalid');
                return setError(input, 'Upload a JPG, JPEG, PNG or PDF file.');
            }
            if (file.size > MAX_FILE) {
                input.value = '';
                box.classList.add('is-invalid');
                return setError(input, 'The file must be 5 MB or smaller.');
            }
            box.classList.add('has-file');
            name.textContent = file.name;
            meta.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB · click to replace`;
            if (file.type.startsWith('image/')) {
                const img = new Image();
                img.alt = '';
                img.src = URL.createObjectURL(file);
                img.onload = () => URL.revokeObjectURL(img.src);
                preview.replaceChildren(img);
            } else {
                preview.innerHTML = '<span class="prr-file-pdf">PDF</span>';
            }
        });
    });

    // ----- Email verification -----
    const box = $('[data-email-box]');
    const verifyLink = $('[data-verify-email]');
    const changeLink = $('[data-change-email]');
    const badge = $('[data-verified-badge]');
    const hint = $('[data-email-hint]');
    const submitHint = form.querySelector('[data-submit-hint]');
    const dialog = document.querySelector('[data-otp-dialog]');
    const otp = {
        form: dialog.querySelector('[data-otp-form]'),
        input: dialog.querySelector('[data-otp-input]'),
        error: dialog.querySelector('[data-otp-error]'),
        message: dialog.querySelector('[data-otp-message]'),
        expiry: dialog.querySelector('[data-otp-expiry]'),
        submit: dialog.querySelector('[data-otp-submit]'),
        resend: dialog.querySelector('[data-otp-resend]'),
        resendTimer: dialog.querySelector('[data-otp-resend-timer]'),
    };
    let expiryTimer = null;
    let resendTimer = null;

    const clock = seconds => `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;

    function countdown(seconds, onTick, onDone) {
        const end = Date.now() + seconds * 1000;
        const tick = () => {
            const left = Math.max(0, Math.round((end - Date.now()) / 1000));
            onTick(left);
            if (left === 0) { clearInterval(timer); onDone?.(); }
        };
        const timer = setInterval(tick, 1000);
        tick();
        return timer;
    }

    function startResendTimer(seconds) {
        clearInterval(resendTimer);
        otp.resend.disabled = true;
        resendTimer = countdown(seconds, left => { otp.resendTimer.textContent = left ? `in ${clock(left)}` : ''; },
            () => { otp.resend.disabled = false; });
    }

    function startExpiryTimer(seconds) {
        clearInterval(expiryTimer);
        expiryTimer = countdown(seconds, left => { otp.expiry.innerHTML = left ? `Code expires in <strong>${clock(left)}</strong>` : '<strong>This code has expired.</strong> Send a new one.'; });
    }

    function showOtpError(message) {
        otp.error.textContent = message || '';
        otp.error.hidden = !message;
        otp.input.classList.toggle('is-invalid', Boolean(message));
    }

    async function post(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(body),
        });
        const data = await response.json().catch(() => ({}));
        return { ok: response.ok, status: response.status, data };
    }

    const firstError = data => data.message || Object.values(data.errors || {})[0]?.[0] || 'Something went wrong. Try again.';

    async function sendCode() {
        const email = emailInput.value.trim().toLowerCase();
        emailInput.value = email;
        if (!EMAIL.test(email)) return setError(emailInput, 'Enter a valid email address.');
        setError(emailInput, null);

        if (!verifyLink) return;
        verifyLink.disabled = otp.resend.disabled = true;
        verifyLink.textContent = 'Sending...';
        const { ok, data } = await post(form.dataset.sendUrl, { email });
        verifyLink.disabled = false;
        verifyLink.textContent = 'Verify';

        if (data.verified) return markVerified();
        if (!ok) {
            if (data.resend_in && dialog.open) { startResendTimer(data.resend_in); showOtpError(firstError(data)); return; }
            if (data.resend_in) { openDialog(); startResendTimer(data.resend_in); showOtpError(firstError(data)); return; }
            if (dialog.open) { showOtpError(firstError(data)); otp.resend.disabled = false; return; }
            return setError(emailInput, firstError(data));
        }

        otp.message.textContent = `${data.message} Enter it below.`;
        showOtpError(null);
        otp.input.value = '';
        startExpiryTimer(data.expires_in);
        startResendTimer(data.resend_in);
        openDialog();
    }

    function openDialog() {
        if (!dialog.open) dialog.showModal();
        otp.input.focus();
    }

    async function verifyCode(event) {
        event.preventDefault();
        const code = otp.input.value.replace(/\s+/g, '');
        if (!/^\d{6}$/.test(code)) return showOtpError('The code must be exactly 6 digits.');

        otp.submit.disabled = true;
        otp.submit.textContent = 'Checking...';
        const { data } = await post(form.dataset.verifyUrl, { email: emailInput.value, code });
        otp.submit.disabled = false;
        otp.submit.textContent = 'Verify code';

        if (data.verified) {
            dialog.close();
            return markVerified();
        }
        showOtpError(firstError(data));
        if (data.locked_for) {
            clearInterval(expiryTimer);
            otp.expiry.innerHTML = '';
            startResendTimer(data.locked_for);
        }
    }

    function markVerified() {
        verified = true;
        clearInterval(expiryTimer);
        clearInterval(resendTimer);
        emailInput.readOnly = true;
        box.classList.add('is-verified');
        verifyLink.hidden = true;
        badge.hidden = false;
        changeLink.hidden = false;
        hint.textContent = 'Your email is verified.';
        setError(emailInput, null);
        submit.disabled = false;
        submitHint.hidden = true;
    }

    function unverify() {
        verified = false;
        emailInput.readOnly = false;
        box.classList.remove('is-verified');
        verifyLink.hidden = false;
        badge.hidden = changeLink.hidden = true;
        hint.textContent = 'Click Verify and we will email you a 6-digit code.';
        submit.disabled = true;
        submitHint.hidden = false;
        emailInput.focus();
    }

    // A resubmission keeps the verified sign-in email, so it has no Verify or Change.
    verifyLink?.addEventListener('click', sendCode);
    changeLink?.addEventListener('click', unverify);
    otp.resend.addEventListener('click', sendCode);
    otp.form.addEventListener('submit', verifyCode);
    otp.input.addEventListener('input', () => {
        otp.input.value = otp.input.value.replace(/\D/g, '').slice(0, 6);
        showOtpError(null);
    });
    dialog.querySelector('[data-otp-close]').addEventListener('click', () => dialog.close());
    emailInput.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !verified && verifyLink) { event.preventDefault(); sendCode(); }
    });

    // ----- Submit: check everything, then lock the button so it cannot post twice -----
    const submitLabel = submit.textContent.trim();
    form.addEventListener('submit', event => {
        let ok = true;
        form.querySelectorAll('input[required]:not([type=checkbox]):not([type=file]), select[required], [data-rule="initial"]').forEach(input => {
            if (!input.readOnly && !check(input)) ok = false;
        });
        form.querySelectorAll('[data-file] input[required]').forEach(input => {
            if (!input.files.length) { input.closest('[data-file]').classList.add('is-invalid'); ok = setError(input, 'This field is required.') && ok; }
        });
        const terms = form.querySelector('input[name=terms]');
        const termsError = form.querySelector('[data-terms-error]');
        termsError?.remove();
        if (!terms.checked) {
            ok = false;
            terms.closest('.prr-terms').insertAdjacentHTML('afterend', '<span class="prl-error" data-terms-error>You must agree to the Terms and Privacy Policy.</span>');
        }
        if (!verified) { ok = false; setError(emailInput, 'Verify this email address first.'); }

        if (!ok) {
            event.preventDefault();
            form.querySelector('.is-invalid, [data-terms-error]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        submit.disabled = true;
        submit.innerHTML = '<span class="prl-spinner" aria-hidden="true"></span> Submitting...';
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted) { submit.disabled = !verified; submit.textContent = submitLabel; }
    });
}
