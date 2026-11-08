<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Application status | {{ config('pickrush.portal_name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/pickrush-logo.svg') }}">
    @vite(['resources/css/pickrush/public.css'])
</head>
<body class="prp">
@php($status = $company?->status ?? 'pending')
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
            <span class="prr-status-icon {{ $status === 'pending' ? '' : 'is-alert' }}" aria-hidden="true">
                @if(session('success'))
                <p class="prr-note" role="status"><strong>{{ session('success') }}</strong></p>
            @endif

            @if($status === 'pending')
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                @else
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v5M12 16.5v.5"/><circle cx="12" cy="12" r="9"/></svg>
                @endif
            </span>
            <div class="prl-head">
                <h1>{{ match ($status) { 'rejected' => 'Registration not approved', 'suspended' => 'Account suspended', default => 'Your registration is under review' } }}</h1>
                <p>{{ $company?->name }} · signed in as {{ $staff->email }}</p>
            </div>

            @if($status === 'pending')
                <p class="prr-note">A PickSell administrator is reviewing your registration. You can't add hubs, staff or riders, or accept deliveries, until it is approved. We'll email you the decision.</p>
            @elseif($status === 'rejected')
                <p class="prr-note">A PickSell administrator did not approve your registration.</p>
                @if($company->rejection_reason)
                    <div class="prr-alert"><strong>Reason:</strong> {{ $company->rejection_reason }}</div>
                @endif
                @if($company->owner_staff_id === $staff->id)
                    <p class="prr-note">Correct your details or documents, then send your registration back for review.</p>
                    <a class="prp-btn prl-submit" href="{{ route('pickrush.application.edit') }}">Correct and resubmit</a>
                @endif
            @else
                <p class="prr-note">This company's account is suspended. Contact PickSell support for help.</p>
            @endif

            <form method="POST" action="{{ route('pickrush.logout') }}">
                @csrf
                <button type="submit" class="{{ $status === 'rejected' ? 'prp-btn prp-btn-dark' : 'prp-btn' }} prl-submit">Sign out</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>
