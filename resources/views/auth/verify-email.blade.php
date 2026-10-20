@extends('layouts.app')
@section('title', 'Verify Your Email')

@section('styles')
@vite('resources/css/views/auth-register.css')
@endsection

@section('content')
<div class="auth-wrapper">
    <div class="auth-shell">
        <div class="auth-card">
        <div class="auth-logo"><a href="/"><img src="{{ asset('images/transparent logo.png') }}" alt="PickSell logo"><span>Pick<span>Sell</span></span></a></div>
        <h1 class="auth-title">Verify your email</h1>
        <p class="auth-sub">{{ $applicant->business_name }} · {{ $applicant->email }}</p>

        @if(session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif
        @if(session('warning'))
            <div class="alert-warning">{{ session('warning') }}</div>
        @endif
        @if($errors->any())
            <div class="alert-error">{{ $errors->first() }}</div>
        @endif

        <div class="notice-box">
            <strong>Status: Unverified.</strong>
            <span>Enter the 6-digit code we emailed you. Codes expire {{ \App\Services\EmailVerificationService::CODE_TTL_MINUTES }} minutes after they are sent. Your application goes to the PickSell Admin team for approval once your email is verified.</span>
        </div>

        <form method="POST" action="{{ route('register.verify-email.verify') }}" id="verifyEmailForm">
            @csrf
            <div class="form-group">
                <label for="code">Verification code <span class="req">*</span></label>
                <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" title="Enter the 6-digit code" placeholder="6-digit code" required autofocus class="otp-code-input">
            </div>
            <button type="submit" class="btn-submit">Verify</button>
        </form>

        <form method="POST" action="{{ route('register.verify-email.resend') }}" class="otp-resend-form">
            @csrf
            <button type="submit" class="otp-button" id="otpResend" data-wait="{{ $resendWait }}">Resend code</button>
        </form>

        <div class="auth-footer"><a href="/login">Back to log in</a></div>
        </div>
    </div>
</div>

@section('scripts')
<script>
    const code = document.getElementById('code');
    code.addEventListener('input', () => { code.value = code.value.replace(/\D/g, '').slice(0, 6); });
    const resend = document.getElementById('otpResend');
    let wait = Number(resend.dataset.wait) || 0;
    const tick = () => {
        resend.disabled = wait > 0;
        resend.textContent = wait > 0 ? `Resend in ${wait}s` : 'Resend code';
        if (wait-- > 0) setTimeout(tick, 1000);
    };
    tick();
</script>
@endsection
@endsection
