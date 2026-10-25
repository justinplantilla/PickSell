<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Log In | Pickrush Logistics</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/pickrush-logo.svg') }}">
    @vite(['resources/css/pickrush/public.css', 'resources/js/pickrush/login.js'])
</head>
<body class="prp">
<div class="prl">
    <aside class="prl-panel">
        <span class="prl-orb" aria-hidden="true"></span>
        <a class="prl-back" href="{{ route('pickrush.dashboard') }}">
            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
            Back to home
        </a>
        <div class="prl-panel-body">
            @include('pickrush.partials.logo-mark', ['class' => 'prl-mark', 'tone' => 'coral', 'size' => 140])
            <div class="prl-name">Pickrush <span>Logistics</span></div>
            <p class="prl-tagline">The staff portal for HQ, province hubs and station hubs.</p>
            <ul class="prl-values">
                <li><span><svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8"/></svg></span>Parcels across every hub</li>
                <li><span><svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="10" r="3"/><path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/></svg></span>Live trip and rider status</li>
                <li><span><svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h8M8 17h5"/></svg></span>Labels, manifests and reports</li>
            </ul>
        </div>

        {{-- A rider loops along the road at the bottom of the panel. --}}
        <svg class="prl-road" viewBox="0 0 640 120" preserveAspectRatio="xMinYMax slice" aria-hidden="true">
            <rect x="0" y="86" width="640" height="34" fill="#1f2328"/>
            <line x1="0" y1="103" x2="640" y2="103" stroke="#f6f3ee" stroke-width="3" stroke-dasharray="20 14" opacity=".5"/>
            <g class="prl-ride">
                <g class="prp-bob">
                    <rect x="0" y="34" width="28" height="24" rx="3" fill="#f6f3ee" stroke="#1f2328" stroke-width="3"/>
                    <path d="M14 34v24" stroke="#E8472A" stroke-width="3"/>
                    <rect x="14" y="58" width="64" height="9" rx="4.5" fill="#1f2328"/>
                    <path d="M70 60l-7-28" stroke="#1f2328" stroke-width="5" stroke-linecap="round"/>
                    <path d="M56 32h15" stroke="#1f2328" stroke-width="4" stroke-linecap="round"/>
                    <path d="M38 56l8-28" stroke="#1f2328" stroke-width="9" stroke-linecap="round"/>
                    <path d="M46 32l16 2" stroke="#1f2328" stroke-width="5" stroke-linecap="round"/>
                    <circle cx="49" cy="17" r="9" fill="#f6f3ee" stroke="#1f2328" stroke-width="3"/>
                    <path d="M41 15a8 8 0 0 1 16-2h-5" fill="#1f2328"/>
                </g>
                <g class="prp-wheel"><circle cx="24" cy="76" r="11" fill="#f6f3ee" stroke="#1f2328" stroke-width="4"/></g>
                <g class="prp-wheel"><circle cx="74" cy="76" r="11" fill="#f6f3ee" stroke="#1f2328" stroke-width="4"/></g>
            </g>
        </svg>
    </aside>

    <main class="prl-main">
        <form class="prl-form" method="POST" action="{{ route('pickrush.login.attempt') }}" novalidate>
            @csrf
            <div class="prl-head">
                <h1>Staff sign-in</h1>
                <p>For HQ, province hub and station hub staff. Riders sign in with the rider app.</p>
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
            <p class="prl-foot">Looking for a parcel? <a href="{{ route('pickrush.track') }}">Track it here</a></p>
        </form>
    </main>
</div>
</body>
</html>
