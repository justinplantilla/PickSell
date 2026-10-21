@extends('layouts.app')
@section('title', 'Create Account')

@section('styles')
@vite('resources/css/views/auth-register.css')
@endsection

@section('content')
<div class="auth-wrapper">
    <div class="auth-shell">
        <div class="auth-card">
        <div class="auth-logo"><a href="/"><img src="{{ asset('images/transparent logo.png') }}" alt="PickSell logo"><span>Pick<span>Sell</span></span></a></div>
        <h1 class="auth-title">Create your account</h1>
        <p class="auth-sub">How will you use PickSell?</p>

        @if($errors->any())
            <div class="alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/register" enctype="multipart/form-data" id="registerForm">
            <div data-register-config data-role="{{ old('role', $registrationRoles[0]) }}" data-region="{{ old('region') }}" data-province="{{ old('province') }}" data-municipality="{{ old('municipality') }}" data-barangay="{{ old('barangay') }}" hidden></div>
            @csrf
            <input type="hidden" name="registration_token" value="{{ old('registration_token', $registrationToken) }}">

            <div class="wizard-progress" id="wizardProgress" aria-label="Registration progress">
                <div class="wizard-progress-item active">Role</div>
                <div class="wizard-progress-item">Personal</div>
                <div class="wizard-progress-item">Account</div>
                <div class="wizard-progress-item">Documents</div>
                <div class="wizard-progress-item">Review</div>
            </div>

            {{-- Role Selector --}}
            <div class="role-selector">
                @if(in_array('buyer', $registrationRoles, true))
                <label class="role-btn {{ old('role', $registrationRoles[0]) === 'buyer' ? 'active' : '' }}" id="role-buyer">
                    <input type="radio" name="role" value="buyer" {{ old('role', $registrationRoles[0]) === 'buyer' ? 'checked' : '' }}>
                    <span class="role-icon"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM5.2 5H2V3H0v2h2l3.6 7.59L4.25 15A2 2 0 0 0 6 18h14v-2H6.42a.25.25 0 0 1-.25-.25l.03-.12L7.1 14h9.45c.75 0 1.41-.41 1.75-1.03L21.7 6.5A1 1 0 0 0 20.83 5H5.2z"/></svg></span>
                    <span class="role-label">Buyer</span>
                    <span class="role-description">Shop and order products</span>
                </label>
                @endif
                @if(in_array('seller', $registrationRoles, true))
                <label class="role-btn {{ old('role', $registrationRoles[0]) === 'seller' ? 'active' : '' }}" id="role-seller">
                    <input type="radio" name="role" value="seller" {{ old('role', $registrationRoles[0]) === 'seller' ? 'checked' : '' }}>
                    <span class="role-icon"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-9 3h2v2h-2V7zm0 4h2v6h-2v-6zM7 7h2v2H7V7zm0 4h2v6H7v-6zm10 6h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg></span>
                    <span class="role-label">Seller</span>
                    <span class="role-description">Sell products on PickSell</span>
                </label>
                @endif
            </div>

            {{-- Personal Information --}}
            <div class="form-section">
                <div class="form-section-title">Personal Information</div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label>Last Name <span class="req">*</span></label>
                        <input type="text" name="last_name" value="{{ old('last_name') }}" placeholder="dela Cruz" minlength="2" maxlength="50" pattern="\p{L}[\p{L}\s.'\-]*" title="2 to 50 characters: letters, spaces, hyphens, apostrophes and periods only" required autocomplete="family-name">
                        @error('last_name')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>First Name <span class="req">*</span></label>
                        <input type="text" name="first_name" value="{{ old('first_name') }}" placeholder="Juan" minlength="2" maxlength="50" pattern="\p{L}[\p{L}\s.'\-]*" title="2 to 50 characters: letters, spaces, hyphens, apostrophes and periods only" required autocomplete="given-name">
                        @error('first_name')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>M.I. <span class="opt">(opt)</span></label>
                        <input type="text" name="middle_initial" value="{{ old('middle_initial') }}" placeholder="A" maxlength="5">
                        @error('middle_initial')<div class="field-error">{{ $message }}</div>@enderror
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
                        <input type="tel" name="contact_no" value="{{ old('contact_no') }}" placeholder="09XXXXXXXXX" inputmode="tel" pattern="(\+?63|0)9[0-9]{9}" maxlength="13" title="Enter an 11-digit mobile number starting with 09 (or +63)" required autocomplete="tel-national">
                        @error('contact_no')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Birthday <span class="req">*</span></label>
                        <input type="date" name="birthday" id="birthday" value="{{ old('birthday') }}" max="{{ now()->subYears(18)->toDateString() }}" required>
                        <small class="field-hint">You must be at least 18 years old.</small>
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
                    <input type="email" name="email" value="{{ old('email', $email) }}" placeholder="you@example.com" required autocomplete="email">
                    @error('email')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="email-otp" id="emailOtp" data-send-url="{{ route('register.email-code') }}" data-verify-url="{{ route('register.email-code.verify') }}" data-verified-email="{{ session('registration_verified_email') }}" hidden>
                    <p class="email-otp-intro">Verify your email to continue. We will send a 6-digit code that expires in 5 minutes.</p>
                    <div class="email-otp-row">
                        <button type="button" class="otp-button" id="otpSend">Send code</button>
                        <div class="email-otp-entry" id="otpEntry" hidden>
                            <input type="text" id="otpCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="6-digit code" aria-label="Verification code">
                            <button type="button" class="otp-button otp-button--primary" id="otpVerify">Verify</button>
                        </div>
                    </div>
                    <div class="email-otp-status" id="otpStatus" role="status" aria-live="polite"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span class="req">*</span></label>
                        <div class="pw-wrap">
                            <input type="password" name="password" placeholder="Min. 8 characters" minlength="8" required autocomplete="new-password">
                            <button type="button" class="pw-eye" data-password-toggle>
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
                            <input type="password" name="password_confirmation" placeholder="Repeat password" minlength="8" required autocomplete="new-password">
                            <button type="button" class="pw-eye" data-password-toggle>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Address --}}
            <div class="form-section">
                <div class="form-section-title">Address</div>
                <div class="form-group">
                    <label>Region <span class="req">*</span></label>
                    <select name="region" id="region" required>
                        <option value="">-- Select Region --</option>
                    </select>
                    @error('region')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Province <span class="req">*</span></label>
                        <select name="province" id="province" required disabled>
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
                <div class="form-group" data-roles="seller">
                    <label>Postal Code <span class="req">*</span></label>
                    <input type="text" name="postal_code" value="{{ old('postal_code') }}" placeholder="e.g. 4009" inputmode="numeric" minlength="4" maxlength="4" pattern="\d{4}" title="Enter the 4-digit postal code" data-required>
                    @error('postal_code')<div class="field-error">{{ $message }}</div>@enderror
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

            {{-- Business Fields: each group shows for the roles in data-roles (and, for sellers, the seller types in data-seller-types); its data-required fields are required only while shown. --}}
            <div class="form-section seller-fields" id="sellerFields">
                <div class="form-section-title">Business Information</div>
                <div class="form-group" data-roles="seller">
                    <label>Seller Type <span class="req">*</span></label>
                    <select name="seller_type" id="seller_type" data-required>
                        <option value="">-- Select Seller Type --</option>
                        @foreach(\App\Support\SellerTypes::LABELS as $type => $label)
                            <option value="{{ $type }}" {{ old('seller_type') === $type ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <small class="field-hint">Individuals upload a government ID only. Sole proprietorships also upload DTI and BIR certificates; corporations upload SEC and BIR certificates.</small>
                    @error('seller_type')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-row">
                    <div class="form-group" data-roles="seller">
                        <label id="businessNameLabel">Shop Name <span class="req">*</span></label>
                        <input type="text" name="business_name" value="{{ old('business_name') }}" placeholder="e.g. Juan's Store" minlength="3" maxlength="30" data-required>
                        @error('business_name')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group" data-roles="seller">
                        <label id="businessTypeLabel">Line of Business <span class="req">*</span></label>
                        <select name="line_of_business" id="line_of_business" data-required>
                            <option value="">-- Select Category --</option>
                            @foreach(['Electronics','Fashion','Home & Living','Sports','Beauty','Food & Grocery','Books','Toys','Others'] as $cat)
                                <option value="{{ $cat }}" {{ old('line_of_business') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
                        </select>
                        @error('line_of_business')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-group" data-roles="seller">
                    <label>TIN (Tax Identification Number) <span class="req">*</span></label>
                    <input type="text" name="tin" value="{{ old('tin') }}" placeholder="000-000-000-000" inputmode="numeric" maxlength="15" pattern="\d{3}[\-\s]?\d{3}[\-\s]?\d{3}[\-\s]?\d{3}" title="Enter the 12-digit TIN in the format 000-000-000-000" data-required>
                    @error('tin')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                @foreach(\App\Support\SellerTypes::DOCUMENT_LABELS as $document => $label)
                    <div class="form-group" data-roles="seller" data-seller-types="{{ implode(' ', \App\Support\SellerTypes::typesRequiring($document)) }}">
                        <label>Upload {{ $label }} <span class="req">*</span></label>
                        <label class="file-upload-label" for="{{ $document }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M16.5 6v11.5a4 4 0 0 1-8 0V5a2.5 2.5 0 0 1 5 0v10.5a1 1 0 0 1-2 0V6H10v9.5a2.5 2.5 0 0 0 5 0V5a4 4 0 0 0-8 0v12.5a5.5 5.5 0 0 0 11 0V6h-1.5z"/></svg> <span id="{{ $document }}_name">Click to upload (JPG, PNG, PDF — max 5MB)</span>
                            <input type="file" id="{{ $document }}" name="{{ $document }}" accept=".jpg,.jpeg,.png,.pdf" data-required>
                        </label>
                        @error($document)<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>

            <div class="notice-box">
                <strong>Note:</strong>
                <span id="registrationNote">Your registration will be reviewed by PickSell. Please wait for the approval result before logging in.</span>
            </div>

            <div class="confirm-group">
                <label class="confirm-row">
                    <input type="checkbox" name="confirmed" value="1" required>
                    <span>I confirm that the information and documents I provided are accurate.</span>
                </label>
                @error('confirmed')<div class="field-error">{{ $message }}</div>@enderror

                <label class="confirm-row">
                    <input type="checkbox" name="terms" value="1" {{ old('terms') ? 'checked' : '' }} required>
                    <span>I have read and agree to the <a href="{{ route('terms') }}" target="_blank" rel="noopener">Terms of Service</a> and <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</span>
                </label>
                @error('terms')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn-submit">Submit Registration</button>
        </form>

        <div class="auth-footer">Already have an account? <a href="/login">Log in</a></div>
        </div>

    </div>
</div>

<button class="dm-toggle" data-theme-toggle title="Toggle dark mode">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z"/></svg>
</button>

@section('scripts')
@vite(['resources/js/views/auth-register.js', 'resources/js/views/auth-register-wizard.js'])
@endsection
@endsection
