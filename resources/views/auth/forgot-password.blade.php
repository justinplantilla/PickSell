@extends('layouts.app')
@section('title', 'Forgot Password')

@section('styles')
@vite('resources/css/views/auth-forgot-password.css')
@endsection

@section('content')
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-logo"><a href="/">Pick<span>Sell</span></a></div>
        <h1 class="auth-title">Forgot Password</h1>
        <p class="auth-sub">Enter your email and we'll send you a reset link.</p>

        @if(session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/forgot-password">
            @csrf
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required autofocus>
            </div>
            <button type="submit" class="btn-submit">Send Reset Link</button>
        </form>

        <div class="auth-footer"><a href="/login">← Back to Login</a></div>
    </div>
</div>
@endsection
