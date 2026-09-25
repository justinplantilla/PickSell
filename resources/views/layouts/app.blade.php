<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('images/transparent logo.png') }}">
    <title>PickSell — @yield('title', 'Pick it. Sell it. We handle the rest.')</title>
    @vite(['resources/css/app.css', 'resources/css/layouts/app.css', 'resources/js/app.js'])
    @vite('resources/js/components/form-behaviors.js')
    @yield('styles')
</head>
@php($isAuthPage = request()->is('login', 'register', 'forgot-password', 'reset-password/*'))
<body class="{{ request()->is('/') ? 'landing-page' : '' }}{{ $isAuthPage ? ' auth-page' : '' }}">
    @include('partials.navbar')

    @yield('content')

    @include('partials.footer')

    <button class="dm-toggle" data-theme-toggle title="Toggle dark mode" aria-label="Toggle dark mode">
        <svg id="dmIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z"/></svg>
    </button>

    @yield('scripts')
</body>
</html>
