@extends('layouts.app')
@section('title', 'Reset Password')

@section('styles')
<style>
    body { display: flex; flex-direction: column; min-height: 100vh; }
    .auth-wrapper { flex: 1; display: flex; align-items: center; justify-content: center; padding: 3rem 1rem; }
    .auth-card { background: #fff; border: 1px solid #e8e2d8; border-radius: 16px; padding: 2.5rem 2rem; width: 100%; max-width: 420px; box-shadow: 0 4px 32px rgba(45,45,45,0.08); }
    .auth-logo { text-align: center; margin-bottom: 1.8rem; }
    .auth-logo a { color: var(--coral); font-size: 2rem; font-weight: 800; letter-spacing: -1px; }
    .auth-logo a span { color: var(--charcoal); }
    .auth-title { font-size: 1.4rem; font-weight: 800; text-align: center; margin-bottom: 0.3rem; }
    .auth-sub { text-align: center; color: #888; font-size: 0.88rem; margin-bottom: 2rem; }
    .form-group { margin-bottom: 1.2rem; }
    label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.4rem; color: var(--charcoal); }
    input { width: 100%; padding: 0.7rem 1rem; border: 1.5px solid #ddd6c8; border-radius: 8px; font-size: 0.95rem; background: var(--bone); color: var(--charcoal); transition: border-color 0.2s; outline: none; }
    input:focus { border-color: var(--coral); background: #fff; }
    .btn-submit { width: 100%; padding: 0.8rem; background: var(--coral); color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background 0.2s; }
    .btn-submit:hover { background: var(--coral-dark); }
    .auth-footer { text-align: center; margin-top: 1.5rem; font-size: 0.88rem; color: #888; }
    .auth-footer a { color: var(--coral); font-weight: 600; }
    .alert-error { background: #fef2f0; border: 1px solid #f5c6be; color: #c0392b; padding: 0.7rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1.2rem; }
</style>
@endsection

@section('content')
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-logo"><a href="/">Pick<span>Sell</span></a></div>
        <h1 class="auth-title">Reset Password</h1>
        <p class="auth-sub">Enter your new password below.</p>

        @if($errors->any())
            <div class="alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/reset-password">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" placeholder="Min. 8 characters" required>
            </div>
            <div class="form-group">
                <label for="password_confirmation">Confirm New Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Repeat password" required>
            </div>
            <button type="submit" class="btn-submit">Reset Password</button>
        </form>

        <div class="auth-footer"><a href="/login">← Back to Login</a></div>
    </div>
</div>
@endsection
