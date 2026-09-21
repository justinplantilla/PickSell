@extends('layouts.app')
@section('title', 'Reset Password')

@section('styles')
@vite('resources/css/views/auth-reset-password.css')
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
