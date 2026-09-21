@extends('layouts.app')
@section('title', 'Log In')

@section('styles')
@vite('resources/css/pages/login.css')
@endsection

@section('content')
<div class="auth-wrapper">
    <div class="auth-shell">
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
                    <button type="button" class="pw-eye" data-password-toggle="password">
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
</div>

<button class="dm-toggle" data-theme-toggle title="Toggle dark mode">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z"/></svg>
</button>

@section('scripts')
@vite('resources/js/pages/login.js')
@endsection
