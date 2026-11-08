<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($mode ?? 'register') === 'resubmit' ? 'Resubmit your registration' : 'Register your company' }} | {{ config('pickrush.portal_name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/pickrush-logo.svg') }}">
    @vite(['resources/css/pickrush/public.css', 'resources/js/pickrush/register.js'])
</head>
<body class="prp">
@php
    // The same form registers a company and, after a rejection, corrects and resubmits it.
    $resubmit = ($mode ?? 'register') === 'resubmit';
    $values ??= [];
    $v = fn (string $name) => old($name, $values[$name] ?? null);
    $verifiedEmail ??= null;
    $email = $resubmit ? $values['email'] : old('email', $verifiedEmail);
    $isVerified = $resubmit || ($email && $email === $verifiedEmail);
    $field = fn (string $name) => $errors->has($name) ? 'prp-input is-invalid' : 'prp-input';
@endphp
<div class="prl prl--long">
    @include('pickrush.auth.partials.panel', [
        'tagline' => 'Register your logistics company to deliver PickSell orders.',
        'values' => [
            ['M9 12l2 2 4-4M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z', 'Reviewed by a PickSell administrator'],
            ['M3 9l9-6 9 6v12H3zM9 21v-6h6v6', 'Run your own hubs, staff and riders'],
            ['M4 4h16v16H4zM4 9h16M9 4v16', 'Set your own shipping rates'],
        ],
    ])

    <main class="prl-main">
        <form class="prl-form prr-form" method="POST" action="{{ $resubmit ? route('pickrush.application.update') : route('pickrush.register.store') }}" enctype="multipart/form-data" novalidate
              data-register
              data-send-url="{{ route('pickrush.register.email-code') }}"
              data-verify-url="{{ route('pickrush.register.email-code.verify') }}"
              data-provinces-url="{{ route('pickrush.register.address.provinces') }}"
              data-municipalities-url="{{ route('pickrush.register.address.municipalities') }}"
              data-barangays-url="{{ route('pickrush.register.address.barangays') }}"
              data-province="{{ $v('province') }}" data-municipality="{{ $v('municipality') }}" data-barangay="{{ $v('barangay') }}">
            @csrf
            @if($resubmit) @method('PUT') @endif
            <div class="prl-head">
                <h1>{{ $resubmit ? 'Correct and resubmit' : 'Register your company' }}</h1>
                <p>Fields marked <span class="prr-req">*</span> are required. After you submit, a PickSell administrator reviews your registration.</p>
            </div>

            @if($resubmit && $rejectionReason)
                <div class="prr-alert"><strong>Why it was not approved:</strong> {{ $rejectionReason }}</div>
            @endif
            @if($errors->any())
                <div class="prr-alert" role="alert">Please check the highlighted fields.{{ $resubmit ? ' Attach any replacement documents again.' : ' For your security, attach your documents and enter your password again.' }}</div>
            @endif

            <fieldset class="prr-section">
                <legend><span>1</span> Personal information</legend>
                <div class="prr-grid">
                    <div class="prl-field">
                        <label for="last_name">Last name <span class="prr-req">*</span></label>
                        <input id="last_name" name="last_name" value="{{ $v('last_name') }}" class="{{ $field('last_name') }}" maxlength="50" autocomplete="family-name" required data-rule="name">
                        @error('last_name')<span class="prl-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="prl-field">
                        <label for="first_name">First name <span class="prr-req">*</span></label>
                        <input id="first_name" name="first_name" value="{{ $v('first_name') }}" class="{{ $field('first_name') }}" maxlength="50" autocomplete="given-name" required data-rule="name">
                        @error('first_name')<span class="prl-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="prl-field">
                        <label for="middle_initial">Middle initial</label>
                        <input id="middle_initial" name="middle_initial" value="{{ $v('middle_initial') }}" class="{{ $field('middle_initial') }}" maxlength="2" placeholder="e.g. A" data-rule="initial">
                        @error('middle_initial')<span class="prl-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="prl-field">
                        <label for="sex">Sex <span class="prr-req">*</span></label>
                        <select id="sex" name="sex" class="{{ $field('sex') }}" required>
                            <option value="">Select</option>
                            <option value="male" @selected($v('sex') === 'male')>Male</option>
                            <option value="female" @selected($v('sex') === 'female')>Female</option>
                        </select>
                        @error('sex')<span class="prl-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="prl-field prr-span">
                        <label for="email">Email address <span class="prr-req">*</span></label>
                        <div class="prr-email {{ $isVerified ? 'is-verified' : '' }}" data-email-box>
                            <input id="email" type="email" name="email" value="{{ $email }}" class="{{ $field('email') }}" autocomplete="email" required @readonly($isVerified)>
                            @unless($resubmit)<button type="button" class="prr-link" data-verify-email @if($isVerified) hidden @endif>Verify</button>@endunless
                            <span class="prr-verified" data-verified-badge @unless($isVerified) hidden @endunless>
                                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                Verified
                            </span>
                            @unless($resubmit)<button type="button" class="prr-link prr-link-muted" data-change-email @unless($isVerified) hidden @endunless>Change</button>@endunless
                        </div>
                        <span class="prr-hint" data-email-hint>{{ $resubmit ? 'Your sign-in email. It cannot be changed here.' : ($isVerified ? 'Your email is verified.' : 'Click Verify and we will email you a 6-digit code.') }}</span>
                        @error('email')<span class="prl-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="prl-field">
                        <label for="contact_no">Contact number <span class="prr-req">*</span></label>
                        <input id="contact_no" type="tel" name="contact_no" value="{{ $v('contact_no') }}" class="{{ $field('contact_no') }}" maxlength="13" placeholder="09171234567" autocomplete="tel" required data-rule="mobile">
                        @error('contact_no')<span class="prl-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="prr-grid prr-grid-tight">
                        <div class="prl-field">
                            <label for="birthday">Birthday <span class="prr-req">*</span></label>
                            <input id="birthday" type="date" name="birthday" value="{{ $v('birthday') }}" class="{{ $field('birthday') }}" max="{{ now()->subYears(18)->toDateString() }}" required>
                            @error('birthday')<span class="prl-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="prl-field">
                            <label for="age">Age <span class="prr-req">*</span></label>
                            <input id="age" class="prp-input" readonly tabindex="-1" aria-describedby="ageHint" data-age>
                            <span class="prr-hint" id="ageHint">From your birthday</span>
                        </div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="prr-section">
                <legend><span>2</span> Business information</legend>
                <div class="prr-grid">
                    <div class="prl-field">
                        <label for="business_name">Business name <span class="prr-req">*</span></label>
                        <input id="business_name" name="business_name" value="{{ $v('business_name') }}" class="{{ $field('business_name') }}" maxlength="150" autocomplete="organization" required>
                        @error('business_name')<span class="prl-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="prl-field">
                        <label for="position">Position <span class="prr-req">*</span></label>
                        <input id="position" name="position" value="{{ $v('position') }}" class="{{ $field('position') }}" maxlength="100" placeholder="e.g. Operations Manager" autocomplete="organization-title" required>
                        @error('position')<span class="prl-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="prr-section">
                <legend><span>3</span> Business address</legend>
                <div class="prr-grid">
                    <div class="prl-field">
                        <label for="province">Province <span class="prr-req">*</span></label>
                        <select id="province" name="province" class="{{ $field('province') }}" required data-address="province"><option value="">Loading...</option></select>
                        @error('province')<span class="prl-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="prl-field">
                        <label for="municipality">City / Municipality <span class="prr-req">*</span></label>
                        <select id="municipality" name="municipality" class="{{ $field('municipality') }}" required disabled data-address="municipality"><option value="">Select a province first</option></select>
                        @error('municipality')<span class="prl-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="prl-field">
                        <label for="barangay">Barangay <span class="prr-req">*</span></label>
                        <select id="barangay" name="barangay" class="{{ $field('barangay') }}" required disabled data-address="barangay"><option value="">Select a city first</option></select>
                        @error('barangay')<span class="prl-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="prl-field">
                        <label for="street_address">Street address <span class="prr-req">*</span></label>
                        <input id="street_address" name="street_address" value="{{ $v('street_address') }}" class="{{ $field('street_address') }}" minlength="5" maxlength="255" placeholder="House no., street, building, subdivision" autocomplete="street-address" required>
                        @error('street_address')<span class="prl-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="prr-section">
                <legend><span>4</span> Required documents</legend>
                <p class="prr-hint">JPG, JPEG, PNG or PDF, up to 5 MB each. Only PickSell administrators can see your documents.{{ $resubmit ? ' Leave a document empty to keep the one you sent before.' : '' }}</p>
                <div class="prr-grid">
                    @foreach(['valid_id' => ['Upload valid ID', 'A readable government-issued ID'], 'dti_permit' => ['Upload business / DTI permit', 'Business registration or DTI permit']] as $type => [$label, $about])
                        <div class="prl-field">
                            <span class="prr-label">{{ $label }} @unless($resubmit)<span class="prr-req">*</span>@endunless</span>
                            <label class="prr-file {{ $errors->has($type) ? 'is-invalid' : '' }}" data-file>
                                <input type="file" name="{{ $type }}" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" @unless($resubmit) required @endunless>
                                <span class="prr-file-preview" data-file-preview aria-hidden="true">
                                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M7 9l5-5 5 5M4 20h16"/></svg>
                                </span>
                                <span class="prr-file-text">
                                    <strong data-file-name>Choose a file</strong>
                                    <small data-file-meta>{{ $resubmit && isset($currentDocuments[$type]) ? 'Current: '.$currentDocuments[$type].' · choose a file to replace it' : $about }}</small>
                                </span>
                            </label>
                            @error($type)<span class="prl-error">{{ $message }}</span>@enderror
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="prr-section">
                <legend><span>5</span> {{ $resubmit ? 'Agreement' : 'Account credentials' }}</legend>
                @unless($resubmit)
                <div class="prr-grid">
                    <div class="prl-field">
                        <label for="password">Password <span class="prr-req">*</span></label>
                        <div class="prl-pw">
                            <input id="password" type="password" name="password" class="{{ $field('password') }}" autocomplete="new-password" required data-rule="password">
                            <button type="button" class="prl-eye" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <ul class="prr-rules" data-password-rules>
                            <li data-check="length">At least 8 characters</li>
                            <li data-check="upper">An uppercase letter</li>
                            <li data-check="lower">A lowercase letter</li>
                            <li data-check="number">A number</li>
                            <li data-check="symbol">A special character</li>
                        </ul>
                        @error('password')<span class="prl-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="prl-field">
                        <label for="password_confirmation">Confirm password <span class="prr-req">*</span></label>
                        <div class="prl-pw">
                            <input id="password_confirmation" type="password" name="password_confirmation" class="prp-input" autocomplete="new-password" required data-rule="confirm">
                            <button type="button" class="prl-eye" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">
                                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                @endunless
                <label class="prl-check prr-terms">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms')) required>
                    <span>I agree to the <a href="{{ rtrim(config('app.url'), '/') }}/terms" target="_blank" rel="noopener">Terms</a> and <a href="{{ rtrim(config('app.url'), '/') }}/privacy" target="_blank" rel="noopener">Privacy Policy</a>. <span class="prr-req">*</span></span>
                </label>
                @error('terms')<span class="prl-error">{{ $message }}</span>@enderror
            </fieldset>

            <button type="submit" class="prp-btn prl-submit" data-submit @disabled(! $isVerified)>{{ $resubmit ? 'Resubmit registration' : 'Submit registration' }}</button>
            <p class="prr-hint prr-center" data-submit-hint @if($isVerified) hidden @endif>Verify your email address to submit.</p>
            @if($resubmit)
                <p class="prl-foot"><a href="{{ route('pickrush.dashboard') }}">Back to application status</a></p>
            @else
                <p class="prl-foot">Already registered? <a href="{{ route('pickrush.login') }}">Sign in</a></p>
            @endif
        </form>
    </main>
</div>

<dialog class="prr-dialog" data-otp-dialog aria-labelledby="otpTitle">
    <form method="dialog" class="prr-dialog-body" data-otp-form novalidate>
        <button type="button" class="prr-dialog-close" data-otp-close aria-label="Close">
            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
        <span class="prr-dialog-icon" aria-hidden="true">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
        </span>
        <h2 id="otpTitle">Enter the verification code</h2>
        <p class="prr-hint" data-otp-message>We sent a 6-digit code to your email.</p>
        <input class="prp-input prr-otp" data-otp-input inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" placeholder="000000" aria-label="6-digit code">
        <span class="prl-error" data-otp-error role="alert" hidden></span>
        <p class="prr-timer" data-otp-expiry>Code expires in <strong>5:00</strong></p>
        <button type="submit" class="prp-btn prl-submit" data-otp-submit>Verify code</button>
        <p class="prr-hint prr-center">Didn't get it? <button type="button" class="prr-link" data-otp-resend disabled>Resend code</button> <span data-otp-resend-timer></span></p>
    </form>
</dialog>
</body>
</html>
