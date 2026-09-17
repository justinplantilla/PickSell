@extends('layouts.app')
@section('title', 'Create Account')

@section('styles')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap');

    body { display: flex; flex-direction: column; min-height: 100vh; }
    .auth-wrapper { flex: 1; display: flex; align-items: flex-start; justify-content: center; padding: 2.5rem 1rem; background: radial-gradient(circle at 50% 0, rgba(255,111,97,.08), transparent 34rem); }
    .auth-card { background: rgba(255,255,255,.94); border: 1px solid #e8e2d8; border-radius: 20px; padding: 2.5rem 2rem; width: 100%; max-width: 680px; box-shadow: 0 18px 55px rgba(45,45,45,0.1); animation: register-card-in .7s cubic-bezier(.22,1,.36,1) both; }

    .auth-logo { display: flex; justify-content: center; margin-bottom: 1.5rem; }
    .auth-logo a { display: inline-flex; align-items: center; gap: 0.55rem; color: var(--charcoal); font-family: 'Manrope', sans-serif; font-size: 1.35rem; font-weight: 800; letter-spacing: -0.04em; }
    .auth-logo img { width: 38px; height: 38px; object-fit: contain; }
    .auth-logo a span { color: var(--coral); }
    .auth-title { font-family: 'Manrope', sans-serif; font-size: 1.3rem; font-weight: 800; text-align: center; margin-bottom: 0.3rem; }
    .auth-sub { text-align: center; color: #888; font-size: 0.85rem; margin-bottom: 1.8rem; }

    /* Role Selector */
    .role-selector { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.8rem; margin-bottom: 2rem; }
    .role-btn { border: 2px solid #e8e2d8; border-radius: 12px; padding: 0.9rem 0.5rem; text-align: center; cursor: pointer; transition: border-color .25s, background .25s, transform .25s, box-shadow .25s; background: var(--bone); }
    .role-btn:hover { border-color: var(--coral); transform: translateY(-3px); box-shadow: 0 8px 18px rgba(255,111,97,.12); }
    .role-btn.active { border-color: var(--coral); background: #fff3f0; transform: translateY(-2px); box-shadow: 0 8px 18px rgba(255,111,97,.16); }
    .role-btn input { display: none; }
    .role-icon { display: flex; align-items: center; justify-content: center; margin-bottom: 0.3rem; color: #888; }
    .role-icon { transition: transform .25s, color .25s; }
    .role-btn.active .role-icon { color: var(--coral); transform: scale(1.08); }
    .role-label { font-size: 0.85rem; font-weight: 700; color: var(--charcoal); }
    .role-btn.active .role-label { color: var(--coral); }

    /* Form */
    .form-section { margin-bottom: 1.5rem; animation: section-rise .55s cubic-bezier(.22,1,.36,1) both; }
    .form-section:nth-of-type(1) { animation-delay: .08s; }
    .form-section:nth-of-type(2) { animation-delay: .14s; }
    .form-section:nth-of-type(3) { animation-delay: .20s; }
    .form-section:nth-of-type(4) { animation-delay: .26s; }
    .form-section:nth-of-type(5) { animation-delay: .32s; }
    .form-section-title { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--coral); margin-bottom: 0.8rem; padding-bottom: 0.4rem; border-bottom: 1px solid #f0ebe0; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 0.5fr; gap: 1rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group.full { grid-column: 1 / -1; }

    label { display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.35rem; color: var(--charcoal); }
    label .req { color: var(--coral); }
    label .opt { color: #aaa; font-weight: 400; font-size: 0.75rem; }

    input[type="text"], input[type="email"], input[type="password"],
    input[type="tel"], input[type="date"], input[type="number"],
    select, textarea {
        width: 100%; padding: 0.65rem 0.9rem; border: 1.5px solid #ddd6c8;
        border-radius: 8px; font-size: 0.9rem; background: var(--bone);
        color: var(--charcoal); outline: none; transition: border-color .25s, background .25s, box-shadow .25s, transform .25s;
        font-family: inherit;
    }
    input:focus, select:focus, textarea:focus { border-color: var(--coral); background: #fff; box-shadow: 0 0 0 3px rgba(255,111,97,.12); transform: translateY(-1px); }
    input[readonly] { background: #f0ebe0; color: #888; cursor: not-allowed; }
    input[disabled], select[disabled] { background: #f0ebe0; color: #aaa; cursor: not-allowed; }

    .field-error { color: #c0392b; font-size: 0.78rem; margin-top: 0.25rem; }

    /* File upload */
    .file-upload-label {
        display: flex; align-items: center; gap: 0.6rem;
        padding: 0.65rem 0.9rem; border: 1.5px dashed #ddd6c8;
        border-radius: 8px; background: var(--bone); cursor: pointer;
        font-size: 0.85rem; color: #888; transition: border-color 0.2s;
    }
    .file-upload-label:hover { border-color: var(--coral); color: var(--coral); }
    .file-upload-label input { display: none; }
    .file-name { font-size: 0.8rem; color: #555; margin-top: 0.3rem; }

    /* Role-specific sections */
    .seller-fields, .courier-fields { display: none; }
    .provider-type-fields { display: none; }

    .btn-submit { position: relative; overflow: hidden; width: 100%; padding: 0.85rem; background: var(--coral); color: #fff; border: none; border-radius: 10px; font-family: 'Manrope', sans-serif; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background .25s, transform .25s, box-shadow .25s; margin-top: 0.5rem; box-shadow: 0 8px 18px rgba(255,111,97,.24); }
    .btn-submit::after { content: ''; position: absolute; inset: 0 auto 0 -35%; width: 18%; background: rgba(255,255,255,.32); transform: skewX(-20deg); transition: left .6s ease; }
    .btn-submit:hover::after { left: 125%; }
    .btn-submit:hover { background: var(--coral-dark); transform: translateY(-2px); box-shadow: 0 12px 24px rgba(255,111,97,.32); }
    .btn-submit.is-submitting { pointer-events: none; opacity: .78; }
    .btn-submit.is-submitting::after { left: 125%; animation: submit-sweep 1s linear infinite; }

    .password-meter { height: 4px; margin-top: .55rem; border-radius: 999px; background: #e8e2d8; overflow: hidden; opacity: 0; transition: opacity .25s; }
    .password-meter.visible { opacity: 1; }
    .password-meter-bar { display: block; width: 0; height: 100%; border-radius: inherit; background: #d9534f; transition: width .3s ease, background .3s ease; }
    .password-hint { min-height: 1rem; margin-top: .25rem; color: #999; font-size: .7rem; }

    .auth-footer { text-align: center; margin-top: 1.2rem; font-size: 0.85rem; color: #888; }
    .auth-footer a { color: var(--coral); font-weight: 600; }

    .alert-error { background: #fef2f0; border: 1px solid #f5c6be; color: #c0392b; padding: 0.7rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1.2rem; }

    .notice-box { background: #fff8f0; border: 1px solid #fde8cc; border-radius: 8px; padding: 0.8rem 1rem; font-size: 0.82rem; color: #7a4f00; margin-top: 1rem; line-height: 1.6; }
    .notice-box strong { display: block; margin-bottom: 0.2rem; }

    .pw-wrap { position: relative; }
    .pw-wrap input { padding-right: 2.5rem; }
    .pw-eye { position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #aaa; padding: 0; display: flex; align-items: center; }
    .pw-eye:hover { color: var(--coral); }

    /* Dark mode */
    body.dark { background: #111; color: #e0e0e0; }
    body.dark .auth-card { background: #1e1e1e; border-color: #333; }
    body.dark input, body.dark select, body.dark textarea { background: #2a2a2a !important; border-color: #444 !important; color: #e0e0e0 !important; }
    body.dark input:focus, body.dark select:focus { background: #2a2a2a !important; border-color: var(--coral) !important; }
    body.dark label { color: #ccc; }
    body.dark .form-section-title { color: var(--coral); border-color: #333; }
    body.dark .role-btn { background: #2a2a2a; border-color: #444; }
    body.dark .role-btn.active { background: #2a1a18; border-color: var(--coral); }
    body.dark .file-upload-label { background: #2a2a2a; border-color: #444; color: #888; }
    body.dark .notice-box { background: #2a1e10; border-color: #5a3a10; color: #d4a96a; }
    body.dark .auth-footer, body.dark .auth-sub { color: #888; }
    .dm-toggle { position: fixed; bottom: 1.2rem; right: 1.2rem; width: 40px; height: 40px; border-radius: 50%; background: #2D2D2D; color: #fff; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 12px rgba(0,0,0,0.2); z-index: 999; transition: background 0.2s; }
    .dm-toggle:hover { background: var(--coral); }
    body.dark .dm-toggle { background: #444; }
    @keyframes register-card-in { from { opacity: 0; transform: translateY(24px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes section-rise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes submit-sweep { from { left: -35%; } to { left: 125%; } }
    @media (prefers-reduced-motion: reduce) {
        .auth-card, .form-section { animation: none; }
        .role-btn, .role-icon, input, select, textarea, .btn-submit { transition: none; }
        .btn-submit::after, .btn-submit.is-submitting::after { animation: none; }
    }
    @media (min-width: 1440px) {
        .auth-card { max-width: 820px; padding: 3rem 3.5rem; }
        .auth-title { font-size: 1.55rem; }
        .form-row, .form-row-3 { gap: 1.25rem; }
    }
    @media (max-width: 640px) {
        .auth-wrapper { padding: 1.25rem 0.75rem; }
        .auth-card { padding: 1.75rem 1rem; border-radius: 14px; }
        .role-selector, .form-row, .form-row-3 { grid-template-columns: 1fr; gap: 0.65rem; }
        .role-selector { margin-bottom: 1.5rem; }
        .role-btn { display: flex; align-items: center; gap: 0.65rem; padding: 0.7rem 0.85rem; text-align: left; }
        .role-icon { margin-bottom: 0; }
        .form-section { margin-bottom: 1.25rem; }
        .btn-submit { min-height: 48px; }
    }
    @media (max-width: 380px) {
        .auth-wrapper { padding-inline: 0.5rem; }
        .auth-card { padding-inline: 0.8rem; }
        .auth-title { font-size: 1.15rem; }
    }
</style>
@endsection

@section('content')
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-logo"><a href="/"><img src="{{ asset('images/transparent logo.png') }}" alt="PickSell logo"><span>Pick<span>Sell</span></span></a></div>
        <h1 class="auth-title">Create your account</h1>
        <p class="auth-sub">Choose your role to get started</p>

        @if($errors->any())
            <div class="alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/register" enctype="multipart/form-data" id="registerForm">
            @csrf

            {{-- Role Selector --}}
            <div class="role-selector">
                <label class="role-btn {{ old('role','buyer') === 'buyer' ? 'active' : '' }}" id="role-buyer">
                    <input type="radio" name="role" value="buyer" {{ old('role','buyer') === 'buyer' ? 'checked' : '' }}>
                    <span class="role-icon"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM5.2 5H2V3H0v2h2l3.6 7.59L4.25 15A2 2 0 0 0 6 18h14v-2H6.42a.25.25 0 0 1-.25-.25l.03-.12L7.1 14h9.45c.75 0 1.41-.41 1.75-1.03L21.7 6.5A1 1 0 0 0 20.83 5H5.2z"/></svg></span>
                    <span class="role-label">Buyer</span>
                </label>
                <label class="role-btn {{ old('role') === 'seller' ? 'active' : '' }}" id="role-seller">
                    <input type="radio" name="role" value="seller" {{ old('role') === 'seller' ? 'checked' : '' }}>
                    <span class="role-icon"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-9 3h2v2h-2V7zm0 4h2v6h-2v-6zM7 7h2v2H7V7zm0 4h2v6H7v-6zm10 6h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg></span>
                    <span class="role-label">Seller</span>
                </label>
                <label class="role-btn {{ old('role') === 'courier' ? 'active' : '' }}" id="role-courier">
                    <input type="radio" name="role" value="courier" {{ old('role') === 'courier' ? 'checked' : '' }}>
                    <span class="role-icon"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 24 24"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2a3 3 0 0 0 6 0h6a3 3 0 0 0 6 0h2v-5l-3-4zM6 18.5A1.5 1.5 0 1 1 7.5 17 1.5 1.5 0 0 1 6 18.5zm13.5-9 1.96 2.5H17V9.5h2.5zm-1.5 9A1.5 1.5 0 1 1 19.5 17a1.5 1.5 0 0 1-1.5 1.5z"/></svg></span>
                    <span class="role-label">Courier</span>
                </label>
                <label class="role-btn {{ old('role') === 'logistics' ? 'active' : '' }}" id="role-logistics">
                    <input type="radio" name="role" value="logistics" {{ old('role') === 'logistics' ? 'checked' : '' }}>
                    <span class="role-icon"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-8 14H7v-2h4v2zm6 0h-4v-2h4v2zm0-4H7V7h10v6z"/></svg></span>
                    <span class="role-label">Logistics</span>
                </label>
            </div>

            {{-- Personal Information --}}
            <div class="form-section">
                <div class="form-section-title">Personal Information</div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label>Last Name <span class="req">*</span></label>
                        <input type="text" name="last_name" value="{{ old('last_name') }}" placeholder="dela Cruz" required>
                        @error('last_name')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>First Name <span class="req">*</span></label>
                        <input type="text" name="first_name" value="{{ old('first_name') }}" placeholder="Juan" required>
                        @error('first_name')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>M.I. <span class="opt">(opt)</span></label>
                        <input type="text" name="middle_initial" value="{{ old('middle_initial') }}" placeholder="A" maxlength="5">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Sex <span class="req">*</span></label>
                        <select name="sex" required>
                            <option value="">-- Select --</option>
                            <option value="Male" {{ old('sex') === 'Male' ? 'selected' : '' }}>Male</option>
                            <option value="Female" {{ old('sex') === 'Female' ? 'selected' : '' }}>Female</option>
                        </select>
                        @error('sex')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>Contact No. <span class="req">*</span></label>
                        <input type="tel" name="contact_no" value="{{ old('contact_no') }}" placeholder="09XXXXXXXXX" inputmode="numeric" pattern="09[0-9]{9}" minlength="11" maxlength="11" title="Enter an 11-digit Philippine mobile number starting with 09" required>
                        @error('contact_no')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Birthday <span class="req">*</span></label>
                        <input type="date" name="birthday" id="birthday" value="{{ old('birthday') }}" required>
                        @error('birthday')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>Age</label>
                        <input type="number" name="age" id="age" readonly placeholder="Auto-generated">
                    </div>
                </div>
            </div>

            {{-- Account Credentials --}}
            <div class="form-section">
                <div class="form-section-title">Account Credentials</div>
                <div class="form-group">
                    <label>Email Address <span class="req">*</span></label>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required>
                    @error('email')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span class="req">*</span></label>
                        <div class="pw-wrap">
                            <input type="password" name="password" placeholder="Min. 8 characters" required>
                            <button type="button" class="pw-eye" onclick="togglePw(this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
                            </button>
                        </div>
                        <div class="password-meter" aria-hidden="true"><span class="password-meter-bar"></span></div>
                        <div class="password-hint" aria-live="polite"></div>
                        @error('password')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>Confirm Password <span class="req">*</span></label>
                        <div class="pw-wrap">
                            <input type="password" name="password_confirmation" placeholder="Repeat password" required>
                            <button type="button" class="pw-eye" onclick="togglePw(this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Address --}}
            <div class="form-section">
                <div class="form-section-title">Address</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Province <span class="req">*</span></label>
                        <select name="province" id="province" required>
                            <option value="">-- Select Province --</option>
                        </select>
                        @error('province')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>Municipality / City <span class="req">*</span></label>
                        <select name="municipality" id="municipality" required disabled>
                            <option value="">-- Select Municipality --</option>
                        </select>
                        @error('municipality')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Barangay <span class="req">*</span></label>
                        <select name="barangay" id="barangay" required disabled>
                            <option value="">-- Select Barangay --</option>
                        </select>
                        @error('barangay')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>House No. / Unit <span class="opt">(opt)</span></label>
                        <input type="text" name="house_no" value="{{ old('house_no') }}" placeholder="e.g. 123">
                    </div>
                </div>
                <div class="form-group">
                    <label>Street / Subdivision <span class="opt">(opt)</span></label>
                    <input type="text" name="street" value="{{ old('street') }}" placeholder="e.g. Rizal St., Sunshine Subd.">
                </div>
            </div>

            {{-- Upload ID --}}
            <div class="form-section">
                <div class="form-section-title">Valid ID</div>
                <div class="form-group">
                    <label>Upload Government-Issued ID <span class="req">*</span></label>
                    <label class="file-upload-label" for="id_upload">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M16.5 6v11.5a4 4 0 0 1-8 0V5a2.5 2.5 0 0 1 5 0v10.5a1 1 0 0 1-2 0V6H10v9.5a2.5 2.5 0 0 0 5 0V5a4 4 0 0 0-8 0v12.5a5.5 5.5 0 0 0 11 0V6h-1.5z"/></svg> <span id="id_upload_name">Click to upload (JPG, PNG, PDF — max 5MB)</span>
                        <input type="file" id="id_upload" name="id_upload" accept=".jpg,.jpeg,.png,.pdf" required>
                    </label>
                    @error('id_upload')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Seller Fields --}}
            <div class="form-section seller-fields" id="sellerFields">
                <div class="form-section-title">Business Information</div>
                <div class="form-group provider-type-fields" id="providerTypeFields">
                    <label>Provider Type <span class="req">*</span></label>
                    <select name="provider_type" id="provider_type">
                        <option value="">-- Select Provider Type --</option>
                        <option value="company" {{ old('provider_type') === 'company' ? 'selected' : '' }}>Company Logistics Provider</option>
                        <option value="individual" {{ old('provider_type') === 'individual' ? 'selected' : '' }}>Individual Logistics Provider</option>
                    </select>
                    @error('provider_type')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label id="businessNameLabel">Business Name <span class="req">*</span></label>
                        <input type="text" name="business_name" value="{{ old('business_name') }}" placeholder="e.g. Juan's Store">
                        @error('business_name')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label id="businessTypeLabel">Line of Business <span class="req">*</span></label>
                        <select name="line_of_business" id="line_of_business">
                            <option value="">-- Select Category --</option>
                            @foreach(['Electronics','Fashion','Home & Living','Sports','Beauty','Food & Grocery','Books','Toys','Others'] as $cat)
                                <option value="{{ $cat }}" {{ old('line_of_business') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
                        </select>
                        @error('line_of_business')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-group">
                    <label>Upload Business Permit <span class="req">*</span></label>
                    <label class="file-upload-label" for="business_permit">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M16.5 6v11.5a4 4 0 0 1-8 0V5a2.5 2.5 0 0 1 5 0v10.5a1 1 0 0 1-2 0V6H10v9.5a2.5 2.5 0 0 0 5 0V5a4 4 0 0 0-8 0v12.5a5.5 5.5 0 0 0 11 0V6h-1.5z"/></svg> <span id="business_permit_name">Click to upload (JPG, PNG, PDF — max 5MB)</span>
                        <input type="file" id="business_permit" name="business_permit" accept=".jpg,.jpeg,.png,.pdf">
                    </label>
                    @error('business_permit')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Courier Fields --}}
            <div class="form-section courier-fields" id="courierFields">
                <div class="form-section-title">Courier Application and Vehicle Information</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Vehicle Type <span class="req">*</span></label>
                        <select name="vehicle_type">
                            <option value="">-- Select Vehicle --</option>
                            <option value="Motorcycle" {{ old('vehicle_type') === 'Motorcycle' ? 'selected' : '' }}>Motorcycle</option>
                            <option value="Bicycle" {{ old('vehicle_type') === 'Bicycle' ? 'selected' : '' }}>Bicycle</option>
                            <option value="Car" {{ old('vehicle_type') === 'Car' ? 'selected' : '' }}>Car</option>
                            <option value="Van" {{ old('vehicle_type') === 'Van' ? 'selected' : '' }}>Van</option>
                            <option value="Truck" {{ old('vehicle_type') === 'Truck' ? 'selected' : '' }}>Truck</option>
                        </select>
                        @error('vehicle_type')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>Plate Number <span class="req">*</span></label>
                        <input type="text" name="plate_number" value="{{ old('plate_number') }}" placeholder="e.g. ABC 1234">
                        @error('plate_number')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-group">
                    <label>Preferred Delivery Area <span class="req">*</span></label>
                    <input type="text" name="delivery_area" value="{{ old('delivery_area') }}" placeholder="e.g. Santa Cruz, Laguna">
                    @error('delivery_area')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Upload OR/CR & Driver's License <span class="req">*</span></label>
                    <label class="file-upload-label" for="or_cr_upload">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M16.5 6v11.5a4 4 0 0 1-8 0V5a2.5 2.5 0 0 1 5 0v10.5a1 1 0 0 1-2 0V6H10v9.5a2.5 2.5 0 0 0 5 0V5a4 4 0 0 0-8 0v12.5a5.5 5.5 0 0 0 11 0V6h-1.5z"/></svg> <span id="or_cr_upload_name">Click to upload (JPG, PNG, PDF — max 5MB)</span>
                        <input type="file" id="or_cr_upload" name="or_cr_upload" accept=".jpg,.jpeg,.png,.pdf">
                    </label>
                    @error('or_cr_upload')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="notice-box">
                <strong>Note:</strong>
                <span id="registrationNote">Your registration will be reviewed by PickSell. Please wait for the approval result before logging in.</span>
            </div>

            <button type="submit" class="btn-submit" style="margin-top:1.5rem;">Submit Registration</button>
        </form>

        <div class="auth-footer">Already have an account? <a href="/login">Log in</a></div>
    </div>
</div>

<button class="dm-toggle" onclick="toggleDark()" title="Toggle dark mode">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z"/></svg>
</button>

<script>
function togglePw(btn) {
    const inp = btn.previousElementSibling || btn.parentElement.querySelector('input');
    const isText = inp.type === 'text';
    inp.type = isText ? 'password' : 'text';
    btn.style.color = isText ? '' : 'var(--coral)';
}
function toggleDark() {
    document.body.classList.toggle('dark');
    localStorage.setItem('darkMode', document.body.classList.contains('dark'));
}
if (localStorage.getItem('darkMode') === 'true') document.body.classList.add('dark');
</script>

<script>
// Role selector toggle
const roleBtns = document.querySelectorAll('.role-btn');
const sellerFields = document.getElementById('sellerFields');
const courierFields = document.getElementById('courierFields');
const registerForm = document.getElementById('registerForm');
const submitButton = registerForm.querySelector('.btn-submit');
const passwordInput = registerForm.querySelector('input[name="password"]');
const passwordMeter = registerForm.querySelector('.password-meter');
const passwordMeterBar = registerForm.querySelector('.password-meter-bar');
const passwordHint = registerForm.querySelector('.password-hint');

function updateRole(role) {
    roleBtns.forEach(b => b.classList.remove('active'));
    document.getElementById('role-' + role).classList.add('active');

    sellerFields.style.display  = ['seller', 'logistics'].includes(role) ? 'block' : 'none';
    courierFields.style.display = role === 'courier' ? 'block' : 'none';
    const providerTypeFields = document.getElementById('providerTypeFields');
    providerTypeFields.style.display = role === 'logistics' ? 'block' : 'none';

    // Toggle required on seller/courier fields
    document.querySelectorAll('#sellerFields input, #sellerFields select').forEach(el => {
        el.required = ['seller', 'logistics'].includes(role);
    });
    document.querySelectorAll('#courierFields input, #courierFields select').forEach(el => {
        el.required = role === 'courier';
    });

    document.querySelectorAll('#sellerFields input, #sellerFields select').forEach(el => {
        if (el.id !== 'provider_type') el.required = ['seller', 'logistics'].includes(role);
    });
    document.getElementById('provider_type').required = role === 'logistics';
    updateProviderFields();
    updateRegistrationNote(role);
}

function updateProviderFields() {
    const providerType = document.getElementById('provider_type').value;
    const logisticsSelected = document.querySelector('input[name="role"]:checked')?.value === 'logistics';
    const businessType = document.getElementById('line_of_business');
    const selectedType = businessType.value;
    const typeOptions = logisticsSelected
        ? ['Local Delivery', 'Same-Day Delivery', 'Express Delivery', 'Freight and Cargo', 'Other']
        : ['Electronics', 'Fashion', 'Home & Living', 'Sports', 'Beauty', 'Food & Grocery', 'Books', 'Toys', 'Others'];
    businessType.innerHTML = '<option value="">-- Select ' + (logisticsSelected ? 'Service Type' : 'Category') + ' --</option>'
        + typeOptions.map(option => '<option value="' + option + '">' + option + '</option>').join('');
    if (typeOptions.includes(selectedType)) businessType.value = selectedType;
    document.getElementById('businessNameLabel').innerHTML = logisticsSelected && providerType === 'individual'
        ? 'Provider Name <span class="req">*</span>'
        : 'Business Name <span class="req">*</span>';
    document.getElementById('businessTypeLabel').innerHTML = logisticsSelected && providerType === 'individual'
        ? 'Service Type <span class="req">*</span>'
        : 'Line of Business <span class="req">*</span>';
    document.querySelector('input[name="business_name"]').placeholder = logisticsSelected && providerType === 'individual'
        ? 'e.g. Juan Dela Cruz Delivery' : "e.g. Juan's Store";
}

function updateRegistrationNote(role) {
    const note = document.getElementById('registrationNote');
    const messages = {
        courier: 'Courier applications are reviewed by the PickSell Logistics team. Please wait for the approval result before logging in.',
        logistics: 'Logistics provider applications are reviewed by the PickSell Admin team. Please wait for approval before logging in.',
        seller: 'Seller applications are reviewed by the PickSell Admin team. Please wait for the approval result before logging in.',
        buyer: 'Your registration will be reviewed by PickSell. Please wait for the approval result before logging in.'
    };
    note.textContent = messages[role] || messages.buyer;
}

function updatePasswordStrength(password) {
    const score = [
        password.length >= 8,
        /[a-z]/.test(password) && /[A-Z]/.test(password),
        /\d/.test(password),
        /[^A-Za-z0-9]/.test(password)
    ].filter(Boolean).length;
    const widths = ['0%', '25%', '50%', '75%', '100%'];
    const colors = ['#d9534f', '#d9534f', '#d99a3d', '#79a85b', '#4c7a5e'];
    const hints = ['', 'Use at least 8 characters.', 'Add upper and lowercase letters.', 'Add a number.', 'Strong password.'];
    passwordMeter.classList.toggle('visible', password.length > 0);
    passwordMeterBar.style.width = widths[score];
    passwordMeterBar.style.background = colors[score];
    passwordHint.textContent = hints[score];
}

roleBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        const role = btn.querySelector('input').value;
        btn.querySelector('input').checked = true;
        updateRole(role);
    });
});

document.getElementById('provider_type').addEventListener('change', updateProviderFields);

passwordInput.addEventListener('input', () => updatePasswordStrength(passwordInput.value));
registerForm.addEventListener('submit', () => {
    submitButton.classList.add('is-submitting');
    submitButton.textContent = 'Submitting...';
});

// Init on load
updateRole('{{ old("role", "buyer") }}');

// Age autogenerate
document.getElementById('birthday').addEventListener('change', function () {
    const dob = new Date(this.value);
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
    document.getElementById('age').value = age >= 0 ? age : '';
});

// File name display
function bindFileLabel(inputId, labelId) {
    document.getElementById(inputId).addEventListener('change', function () {
        document.getElementById(labelId).textContent = this.files[0]?.name || 'Click to upload';
    });
}
bindFileLabel('id_upload', 'id_upload_name');
bindFileLabel('business_permit', 'business_permit_name');
bindFileLabel('or_cr_upload', 'or_cr_upload_name');

// PSGC API — Province → Municipality → Barangay
const BASE = 'https://psgc.gitlab.io/api';

async function fetchJSON(url) {
    const res = await fetch(url);
    return res.json();
}

function populateSelect(sel, items, valueKey, labelKey, placeholder) {
    sel.innerHTML = `<option value="">${placeholder}</option>`;
    items.sort((a, b) => a[labelKey].localeCompare(b[labelKey]));
    items.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item[labelKey];
        opt.dataset.code = item[valueKey];
        opt.textContent = item[labelKey];
        sel.appendChild(opt);
    });
}

// Load provinces on page load
fetchJSON(`${BASE}/provinces/`).then(data => {
    populateSelect(document.getElementById('province'), data, 'code', 'name', '-- Select Province --');
    // Restore old value
    @if(old('province'))
        const provSel = document.getElementById('province');
        [...provSel.options].forEach(o => { if(o.value === '{{ old("province") }}') o.selected = true; });
        provSel.dispatchEvent(new Event('change'));
    @endif
});

document.getElementById('province').addEventListener('change', async function () {
    const selected = this.options[this.selectedIndex];
    const code = selected?.dataset.code;
    const munSel = document.getElementById('municipality');
    const barSel = document.getElementById('barangay');

    munSel.innerHTML = '<option value="">-- Select Municipality --</option>';
    barSel.innerHTML = '<option value="">-- Select Barangay --</option>';
    munSel.disabled = true;
    barSel.disabled = true;

    if (!code) return;

    const data = await fetchJSON(`${BASE}/provinces/${code}/cities-municipalities/`);
    populateSelect(munSel, data, 'code', 'name', '-- Select Municipality --');
    munSel.disabled = false;

    @if(old('municipality'))
        [...munSel.options].forEach(o => { if(o.value === '{{ old("municipality") }}') o.selected = true; });
        munSel.dispatchEvent(new Event('change'));
    @endif
});

document.getElementById('municipality').addEventListener('change', async function () {
    const selected = this.options[this.selectedIndex];
    const code = selected?.dataset.code;
    const barSel = document.getElementById('barangay');

    barSel.innerHTML = '<option value="">-- Select Barangay --</option>';
    barSel.disabled = true;

    if (!code) return;

    const data = await fetchJSON(`${BASE}/cities-municipalities/${code}/barangays/`);
    populateSelect(barSel, data, 'code', 'name', '-- Select Barangay --');
    barSel.disabled = false;

    @if(old('barangay'))
        [...barSel.options].forEach(o => { if(o.value === '{{ old("barangay") }}') o.selected = true; });
    @endif
});
</script>
@endsection
