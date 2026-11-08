<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Log In | {{ config('pickrush.portal_name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/pickrush-logo.svg') }}">
    @vite(['resources/css/pickrush/public.css', 'resources/js/pickrush/login.js'])
</head>
<body class="prp">
<div class="prl">
    @include('pickrush.auth.partials.panel', [
        'tagline' => 'The portal for logistics companies delivering PickSell orders.',
        'values' => [
            ['M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8', 'Parcels across every hub'],
            ['M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11zM12 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6z', 'Live trip and rider status'],
            ['M4 4h16v16H4zM8 9h8M8 13h8M8 17h5', 'Labels, manifests and reports'],
        ],
    ])

    <main class="prl-main">
        <form class="prl-form" method="POST" action="{{ route('pickrush.login.attempt') }}" novalidate>
            @csrf
            <div class="prl-head">
                <h1>Staff sign-in</h1>
                <p>For logistics company staff: HQ, province hubs and station hubs. Riders sign in with the rider app.</p>
            </div>

            <div class="prl-field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="prp-input" autocomplete="username" required autofocus>
                @error('email')<span class="prl-error">{{ $message }}</span>@enderror
            </div>
            <div class="prl-field">
                <label for="password">Password</label>
                <div class="prl-pw">
                    <input id="password" type="password" name="password" class="prp-input" autocomplete="current-password" required>
                    <button type="button" class="prl-eye" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                @error('password')<span class="prl-error">{{ $message }}</span>@enderror
            </div>
            <label class="prl-check"><input type="checkbox" name="remember" value="1"> Keep me signed in on this device</label>
            <button type="submit" class="prp-btn prl-submit">Sign in</button>
            <p class="prl-foot">New logistics company? <a href="{{ route('pickrush.register') }}">Register your company</a></p>
            <p class="prl-foot">Looking for a parcel? <a href="{{ route('pickrush.track') }}">Track it here</a></p>
        </form>
    </main>
</div>
</body>
</html>
