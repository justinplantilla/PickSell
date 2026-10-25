<h1 class="auth-title" id="loginTitle" tabindex="-1">Welcome back</h1>
<p class="auth-sub">Log in to your PickSell account</p>

@if(session('success'))
    <div class="alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert-error">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert-error">{{ $errors->first() }}</div>
@endif

<form method="POST" action="/login" id="loginForm">
    @csrf
    <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required @if($mode === 'login') autofocus @endif autocomplete="email">
    </div>
    <div class="form-group">
        <div class="label-row">
            <label for="password">Password</label>
            <a class="auth-link" href="/forgot-password">Forgot password?</a>
        </div>
        <div class="pw-wrap">
            <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
            <button type="button" class="pw-eye" data-password-toggle="password" aria-label="Show password">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
            </button>
        </div>
    </div>
    <label class="check-row"><input type="checkbox" name="remember"> Remember me</label>
    <button type="submit" class="btn-submit">Log In</button>
</form>

<div class="auth-footer">
    Don't have an account? <a href="{{ route('register') }}">Sign up free</a>
</div>
