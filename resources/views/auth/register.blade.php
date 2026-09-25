@extends('layouts.app')
@section('title', 'Create Account')

@section('styles')
@vite('resources/css/views/auth-register.css')
@endsection

@section('content')
@php($isLogisticsSite = request()->getHost() === 'logistics.pick-sell.shop')
<div class="auth-wrapper">
    <div class="auth-shell">
        <div class="auth-card">
        <div class="auth-logo"><a href="/"><img src="{{ asset('images/transparent logo.png') }}" alt="PickSell logo"><span>Pick<span>Sell</span></span></a></div>
        <h1 class="auth-title">{{ $isLogisticsSite ? 'Join PickSell Logistics' : 'Create your account' }}</h1>
        <p class="auth-sub">{{ $isLogisticsSite ? 'Choose what you would like to apply for' : 'How will you use PickSell?' }}</p>

        @if($errors->any())
            <div class="alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/register" enctype="multipart/form-data" id="registerForm">
            <div data-register-config data-role="{{ old('role', $registrationRoles[0]) }}" data-province="{{ old('province') }}" data-municipality="{{ old('municipality') }}" data-barangay="{{ old('barangay') }}" hidden></div>
            @csrf

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
                @if(in_array('courier', $registrationRoles, true))
                <label class="role-btn {{ old('role', $registrationRoles[0]) === 'courier' ? 'active' : '' }}" id="role-courier">
                    <input type="radio" name="role" value="courier" {{ old('role', $registrationRoles[0]) === 'courier' ? 'checked' : '' }}>
                    <span class="role-icon"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 24 24"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2a3 3 0 0 0 6 0h6a3 3 0 0 0 6 0h2v-5l-3-4zM6 18.5A1.5 1.5 0 1 1 7.5 17 1.5 1.5 0 0 1 6 18.5zm13.5-9 1.96 2.5H17V9.5h2.5zm-1.5 9A1.5 1.5 0 1 1 19.5 17a1.5 1.5 0 0 1-1.5 1.5z"/></svg></span>
                    <span class="role-label">Courier / Rider</span>
                    <span class="role-description">Deliver parcels to customers</span>
                </label>
                @endif
                @if(in_array('logistics', $registrationRoles, true))
                <label class="role-btn {{ old('role', $registrationRoles[0]) === 'logistics' ? 'active' : '' }}" id="role-logistics">
                    <input type="radio" name="role" value="logistics" {{ old('role', $registrationRoles[0]) === 'logistics' ? 'checked' : '' }}>
                    <span class="role-icon"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-8 14H7v-2h4v2zm6 0h-4v-2h4v2zm0-4H7V7h10v6z"/></svg></span>
                    <span class="role-label">Logistics Provider</span>
                    <span class="role-description">Manage delivery operations</span>
                </label>
                @endif
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
                    <input type="email" name="email" value="{{ old('email', $email) }}" placeholder="you@example.com" required>
                    @error('email')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span class="req">*</span></label>
                        <div class="pw-wrap">
                            <input type="password" name="password" placeholder="Min. 8 characters" required>
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
                            <input type="password" name="password_confirmation" placeholder="Repeat password" required>
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

            <label class="confirm-row">
                <input type="checkbox" name="confirmed" value="1" required>
                <span>I confirm that the information and documents I provided are accurate.</span>
            </label>

            <button type="submit" class="btn-submit blade-inline-1">Submit Registration</button>
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
