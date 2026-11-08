<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f6f6f6; padding: 40px 16px; color: #1a1a1a; }
    .wrap { max-width: 480px; margin: 0 auto; }
    .top-bar { height: 4px; background: #E8472A; border-radius: 4px 4px 0 0; }
    .card { background: #fff; border-radius: 0 0 12px 12px; padding: 40px 40px 32px; }
    .brand { font-size: 1.1rem; font-weight: 800; color: #E8472A; letter-spacing: -0.3px; margin-bottom: 32px; }
    h1 { font-size: 1.35rem; font-weight: 700; margin-bottom: 12px; line-height: 1.3; }
    p { font-size: 0.9rem; color: #555; line-height: 1.75; margin-bottom: 14px; }
    .note { background: #fff3f0; border: 1px solid #fcd5cc; border-radius: 8px; padding: 14px 16px; margin: 20px 0; font-size: 0.9rem; color: #1a1a1a; line-height: 1.6; }
    .btn { display: inline-block; margin-top: 6px; padding: 12px 22px; border-radius: 8px; background: #1f2328; color: #fff !important; font-size: 0.9rem; font-weight: 700; text-decoration: none; }
    .footer { margin-top: 24px; text-align: center; font-size: 0.72rem; color: #bbb; line-height: 1.7; }
</style>
</head>
<body>
<div class="wrap">
    <div class="top-bar"></div>
    <div class="card">
        <div class="brand">PickSell Logistics</div>
        @switch($decision)
            @case('approved')
                <h1>{{ $companyName }} is approved!</h1>
                <p>Hi {{ $name }}, a PickSell administrator approved your logistics company. You can now sign in and set up your hubs, staff and riders.</p>
                <div class="note">Your parcels' tracking numbers will start with <strong>{{ $prefix }}</strong>.</div>
                <a class="btn" href="{{ $loginUrl }}">Sign in</a>
                @break
            @case('reinstated')
                <h1>{{ $companyName }} is active again</h1>
                <p>Hi {{ $name }}, a PickSell administrator lifted the suspension on your logistics company. Your staff can sign in again.</p>
                <a class="btn" href="{{ $loginUrl }}">Sign in</a>
                @break
            @case('suspended')
                <h1>{{ $companyName }} is suspended</h1>
                <p>Hi {{ $name }}, a PickSell administrator suspended your logistics company. Your staff cannot use the portal until the suspension is lifted.</p>
                @if($reason)<div class="note"><strong>Reason:</strong> {{ $reason }}</div>@endif
                <p>If you have questions, contact PickSell support.</p>
                @break
            @default
                <h1>Your registration was not approved</h1>
                <p>Hi {{ $name }}, a PickSell administrator reviewed the registration of {{ $companyName }} and could not approve it.</p>
                @if($reason)<div class="note"><strong>Reason:</strong> {{ $reason }}</div>@endif
                <p>Sign in to correct your details or documents and submit your registration again.</p>
                <a class="btn" href="{{ $loginUrl }}">Sign in and resubmit</a>
        @endswitch
    </div>
    <div class="footer">PickSell Logistics · logistics partners of PickSell</div>
</div>
</body>
</html>
