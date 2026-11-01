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
    .footer { margin-top: 24px; text-align: center; font-size: 0.72rem; color: #bbb; line-height: 1.7; }
</style>
</head>
<body>
<div class="wrap">
    <div class="top-bar"></div>
    <div class="card">
        <div class="brand">Pickrush</div>
        @if($approved)
            <h1>You're approved, {{ $name }}!</h1>
            <p>Your rider application{{ $station ? ' at '.$station : '' }} has been approved. Your Pickrush rider account is now active.</p>
            <div class="note">Log in to the Pickrush rider app with your email and password, then go online to start receiving pickups.</div>
            <p>New riders start on probation with lower cash and parcel limits. Your station raises them as you complete deliveries.</p>
        @else
            <h1>Your rider application was not approved</h1>
            <p>Hi {{ $name }}, the station{{ $station ? ' at '.$station : '' }} reviewed your rider application and could not approve it.</p>
            @if($reason)
                <div class="note"><strong>Reason:</strong> {{ $reason }}</div>
            @endif
            <p>If you have questions, contact the station.</p>
        @endif
    </div>
    <div class="footer">Pickrush Logistics · a PickSell company</div>
</div>
</body>
</html>
