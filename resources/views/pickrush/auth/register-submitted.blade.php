<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registration submitted | {{ config('pickrush.portal_name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/pickrush-logo.svg') }}">
    @vite(['resources/css/pickrush/public.css'])
</head>
<body class="prp">
<div class="prl">
    @include('pickrush.auth.partials.panel', [
        'tagline' => 'Register your logistics company to deliver PickSell orders.',
        'values' => [
            ['M9 12l2 2 4-4M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z', 'Reviewed by a PickSell administrator'],
            ['M3 9l9-6 9 6v12H3zM9 21v-6h6v6', 'Run your own hubs, staff and riders'],
            ['M4 4h16v16H4zM4 9h16M9 4v16', 'Set your own shipping rates'],
        ],
    ])

    <main class="prl-main">
        <div class="prl-form prr-status">
            <span class="prr-status-icon" aria-hidden="true">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            </span>
            <div class="prl-head">
                <h1>Registration submitted</h1>
                <p>Thank you for registering <strong>{{ $company }}</strong>.</p>
            </div>
            <p class="prr-note">After submitting your registration, please wait for the administrator's approval, which will be sent to your email.</p>
            <a class="prp-btn prl-submit" href="{{ route('pickrush.dashboard') }}">Back to home</a>
        </div>
    </main>
</div>
</body>
</html>
