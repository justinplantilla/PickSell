<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f6f6f6; padding: 40px 16px; color: #1a1a1a; }
    .wrap { max-width: 480px; margin: 0 auto; }
    .top-bar { height: 4px; border-radius: 4px 4px 0 0; }
    .top-approved    { background: #16a34a; }
    .top-suspended   { background: #dc2626; }
    .top-deactivated { background: #9ca3af; }
    .card { background: #fff; border-radius: 0 0 12px 12px; padding: 40px 40px 32px; }
    .brand { font-size: 1.1rem; font-weight: 800; color: #E8472A; letter-spacing: -0.3px; margin-bottom: 32px; }
    .brand span { color: #1a1a1a; }
    h1 { font-size: 1.35rem; font-weight: 700; color: #1a1a1a; margin-bottom: 12px; line-height: 1.3; }
    p { font-size: 0.9rem; color: #555; line-height: 1.75; margin-bottom: 14px; }
    .divider { border: none; border-top: 1px solid #f0f0f0; margin: 28px 0; }
    .info-box { padding: 12px 16px; border-radius: 6px; font-size: 0.88rem; line-height: 1.65; margin: 16px 0; }
    .info-approved    { background: #f0fdf4; border-left: 3px solid #16a34a; color: #166534; }
    .info-suspended   { background: #fef2f2; border-left: 3px solid #dc2626; color: #991b1b; }
    .info-deactivated { background: #f9f9f9; border-left: 3px solid #9ca3af; color: #555; }
    .btn { display: inline-block; background: #E8472A; color: #fff !important; padding: 12px 28px; border-radius: 7px; text-decoration: none; font-weight: 600; font-size: 0.88rem; }
    .footer { margin-top: 24px; text-align: center; font-size: 0.72rem; color: #bbb; line-height: 1.7; }
</style>
</head>
<body>
<div class="wrap">
    <div class="top-bar top-{{ $newStatus }}"></div>
    <div class="card">
        <div class="brand">Pick<span>Sell</span></div>

        <h1>Your account has been {{ $newStatus }}</h1>
        <p>Hi <strong>{{ $user->full_name }}</strong>,</p>
        <p>Your PickSell account status has been updated by our admin team.</p>

        <div class="info-box info-{{ $newStatus }}">
            @if($newStatus === 'approved')
                Your account is now active. You can log in and use the platform normally.
            @elseif($newStatus === 'suspended')
                Your account has been temporarily suspended. Please contact our support team to resolve this.
            @elseif($newStatus === 'deactivated')
                Your account has been deactivated. If you believe this is an error, please contact support.
            @endif
        </div>

        <hr class="divider">

        @if($newStatus === 'approved')
            <p><a href="{{ url('/login') }}" class="btn">Log in to PickSell</a></p>
        @else
            <p style="font-size:0.85rem;">Need help? Email us at <a href="mailto:{{ config('mail.from.address') }}" style="color:#E8472A;text-decoration:none;">{{ config('mail.from.address') }}</a></p>
        @endif
    </div>
    <div class="footer">
        © {{ date('Y') }} PickSell &nbsp;·&nbsp; All rights reserved.
    </div>
</div>
</body>
</html>
