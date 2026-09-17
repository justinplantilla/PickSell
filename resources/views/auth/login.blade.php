@extends('layouts.app')
@section('title', 'Log In')

@section('styles')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap');

    body { display: flex; flex-direction: column; min-height: 100vh; }
    .auth-wrapper { flex: 1; display: flex; align-items: center; justify-content: center; padding: 3rem 1rem; background: radial-gradient(circle at 50% 0, rgba(255,111,97,.08), transparent 30rem); }
    .auth-card { background: rgba(255,255,255,.94); border: 1px solid #e8e2d8; border-radius: 20px; padding: 2.5rem 2rem; width: 100%; max-width: 420px; box-shadow: 0 18px 55px rgba(45,45,45,0.1); animation: login-card-in .7s cubic-bezier(.22,1,.36,1) both; }

    .auth-logo { display: flex; justify-content: center; margin-bottom: 1.8rem; }
    .auth-logo a { display: inline-flex; align-items: center; gap: 0.55rem; color: var(--charcoal); font-family: 'Manrope', sans-serif; font-size: 1.35rem; font-weight: 800; letter-spacing: -0.04em; }
    .auth-logo img { width: 38px; height: 38px; object-fit: contain; }
    .auth-logo a span { color: var(--coral); }
    .auth-title { font-family: 'Manrope', sans-serif; font-size: 1.4rem; font-weight: 800; text-align: center; margin-bottom: 0.3rem; }
    .auth-sub { text-align: center; color: #888; font-size: 0.88rem; margin-bottom: 2rem; }

    .form-group { margin-bottom: 1.2rem; }
    label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.4rem; color: var(--charcoal); }
    input[type="email"], input[type="password"], input[type="text"] {
        width: 100%; padding: 0.7rem 1rem; border: 1.5px solid #ddd6c8; border-radius: 8px;
        font-size: 0.95rem; background: var(--bone); color: var(--charcoal);
        transition: border-color .25s, background .25s, box-shadow .25s, transform .25s; outline: none; box-sizing: border-box;
    }
    input:focus { border-color: var(--coral); background: #fff; box-shadow: 0 0 0 3px rgba(255,111,97,.12); transform: translateY(-1px); }

    .form-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; font-size: 0.85rem; }
    .form-row label { margin: 0; font-weight: 400; display: flex; align-items: center; gap: 0.4rem; cursor: pointer; }
    .form-row a { color: var(--coral); font-weight: 600; }
    .form-row a:hover { text-decoration: underline; }

    .btn-submit { position: relative; overflow: hidden; width: 100%; padding: 0.8rem; background: var(--coral); color: #fff; border: none; border-radius: 10px; font-family: 'Manrope', sans-serif; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background .25s, transform .25s, box-shadow .25s; box-shadow: 0 8px 18px rgba(255,111,97,.24); }
    .btn-submit::after { content: ''; position: absolute; inset: 0 auto 0 -35%; width: 18%; background: rgba(255,255,255,.32); transform: skewX(-20deg); transition: left .6s ease; }
    .btn-submit:hover::after { left: 125%; }
    .btn-submit:hover { background: var(--coral-dark); transform: translateY(-2px); box-shadow: 0 12px 24px rgba(255,111,97,.32); }
    .btn-submit.is-submitting { pointer-events: none; opacity: .78; }
    .btn-submit.is-submitting::after { left: 125%; animation: login-submit-sweep 1s linear infinite; }

    .auth-footer { text-align: center; margin-top: 1.5rem; font-size: 0.88rem; color: #888; }
    .auth-footer a { color: var(--coral); font-weight: 600; }
    .auth-footer a:hover { text-decoration: underline; }

    .alert-error { background: #fef2f0; border: 1px solid #f5c6be; color: #c0392b; padding: 0.7rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1.2rem; }
    .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 0.7rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1.2rem; }

    .pw-wrap { position: relative; }
    .pw-wrap input { padding-right: 2.8rem; }
    .pw-eye { position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #aaa; padding: 0; display: flex; align-items: center; line-height: 1; }
    .pw-eye:hover { color: var(--coral); }

    /* Dark mode */
    body.dark { background: #111; color: #e0e0e0; }
    body.dark .auth-card { background: #1e1e1e; border-color: #333; }
    body.dark input[type="email"], body.dark input[type="password"], body.dark input[type="text"] { background: #2a2a2a; border-color: #444; color: #e0e0e0; }
    body.dark input:focus { background: #2a2a2a; border-color: var(--coral); }
    body.dark label { color: #ccc; }
    body.dark .auth-sub, body.dark .auth-footer { color: #888; }
    body.dark .form-row a { color: var(--coral); }
    .dm-toggle { position: fixed; bottom: 1.2rem; right: 1.2rem; width: 40px; height: 40px; border-radius: 50%; background: #2D2D2D; color: #fff; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 12px rgba(0,0,0,0.2); z-index: 999; transition: background 0.2s; }
    .dm-toggle:hover { background: var(--coral); }
    body.dark .dm-toggle { background: #444; }
    @keyframes login-card-in { from { opacity: 0; transform: translateY(24px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes login-submit-sweep { from { left: -35%; } to { left: 125%; } }
    @media (prefers-reduced-motion: reduce) {
        .auth-card, input, .btn-submit { animation: none; transition: none; }
        .btn-submit::after, .btn-submit.is-submitting::after { animation: none; }
    }
</style>
@endsection

@section('content')
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-logo"><a href="/"><img src="{{ asset('images/transparent logo.png') }}" alt="PickSell logo"><span>Pick<span>Sell</span></span></a></div>
        <h1 class="auth-title">Welcome back</h1>
        <p class="auth-sub">Log in to your PickSell account</p>

        @if(session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/login" id="loginForm">
            @csrf
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="pw-wrap">
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                    <button type="button" class="pw-eye" onclick="togglePw('password', this)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
                    </button>
                </div>
            </div>
            <div class="form-row">
                <label><input type="checkbox" name="remember"> Remember me</label>
                <a href="/forgot-password">Forgot password?</a>
            </div>
            <button type="submit" class="btn-submit">Log In</button>
        </form>

        <div class="auth-footer">
            Don't have an account? <a href="/register">Sign up free</a>
        </div>
    </div>
</div>

<button class="dm-toggle" onclick="toggleDark()" title="Toggle dark mode">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z"/></svg>
</button>

<script>
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const isText = inp.type === 'text';
    inp.type = isText ? 'password' : 'text';
    btn.style.color = isText ? '' : 'var(--coral)';
}
const loginForm = document.getElementById('loginForm');
const loginSubmit = loginForm.querySelector('.btn-submit');
loginForm.addEventListener('submit', () => {
    loginSubmit.classList.add('is-submitting');
    loginSubmit.textContent = 'Logging in...';
});
function toggleDark() {
    document.body.classList.toggle('dark');
    localStorage.setItem('darkMode', document.body.classList.contains('dark'));
}
if (localStorage.getItem('darkMode') === 'true') document.body.classList.add('dark');
</script>
@endsection
