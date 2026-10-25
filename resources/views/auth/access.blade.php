@extends('layouts.app')
@section('title', $mode === 'register' ? 'Create Account' : 'Log In')
@section('bare', true)

@section('styles')
@vite('resources/css/pages/auth.css')
@endsection

@section('content')
{{-- /login and /register share this page so switching between them slides the brand panel instead of reloading. Only the active form shows validation errors. --}}
@php($noErrors = new \Illuminate\Support\ViewErrorBag)
<div class="auth-split" data-auth-split data-mode="{{ $mode }}" data-login-url="{{ route('login') }}" data-register-url="{{ route('register') }}">
    <div class="auth-panes">
        <section class="auth-pane auth-pane--login {{ $mode === 'login' ? 'is-active' : '' }}" data-auth-pane="login" aria-labelledby="loginTitle" @if($mode !== 'login') hidden inert @endif>
            <div class="auth-pane-inner">
                @include('auth.partials.login-form', $mode === 'login' ? [] : ['errors' => $noErrors])
            </div>
        </section>

        <section class="auth-pane auth-pane--register {{ $mode === 'register' ? 'is-active' : '' }}" data-auth-pane="register" data-auth-scroll aria-labelledby="registerTitle" @if($mode !== 'register') hidden inert @endif>
            <div class="auth-pane-inner">
                @include('auth.partials.register-form', $mode === 'register' ? [] : ['errors' => $noErrors])
            </div>
        </section>
    </div>

    <aside class="auth-brand">
        <span class="auth-orb auth-orb--coral" aria-hidden="true"></span>
        <span class="auth-orb auth-orb--slate" aria-hidden="true"></span>
        @include('auth.partials.brand-scene')
        <a class="auth-back" href="/shop">
            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
            Back to shop
        </a>

        <div class="auth-brand-body">
            <img class="auth-brand-logo" src="{{ asset('images/transparent logo.png') }}" alt="">
            <a class="auth-brand-name" href="/">Pick<span>Sell</span></a>
            <p class="auth-brand-tagline">Buy from local sellers, sell to nearby buyers, delivered by PickRush.</p>
            <ul class="auth-values">
                <li>
                    <span class="auth-value-icon"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/></svg></span>
                    Secure checkout
                </li>
                <li>
                    <span class="auth-value-icon"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1.5-5h15L21 9"/><path d="M3 9h18v2a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0V9z"/><path d="M5 13v7h14v-7"/></svg></span>
                    Verified local sellers
                </li>
                <li>
                    <span class="auth-value-icon"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h11v10H3z"/><path d="M14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/></svg></span>
                    Fast delivery via PickRush
                </li>
            </ul>
        </div>

        <div class="auth-brand-switch">
            <div class="auth-brand-msg auth-brand-msg--login">
                <p>New to PickSell?</p>
                <a class="auth-switch" href="{{ route('register') }}">Create an account</a>
            </div>
            <div class="auth-brand-msg auth-brand-msg--register">
                <p>Already have an account?</p>
                <a class="auth-switch" href="{{ route('login') }}">Log in</a>
            </div>
        </div>

        <nav class="auth-tabs" aria-label="Log in or register">
            <a class="auth-tab auth-tab--login" href="{{ route('login') }}">Log in</a>
            <a class="auth-tab auth-tab--register" href="{{ route('register') }}">Register</a>
        </nav>
    </aside>
</div>
@endsection

@section('scripts')
@vite(['resources/js/pages/auth.js', 'resources/js/views/auth-register-wizard.js'])
@endsection
