<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f6f6f6; padding: 40px 16px; color: #1a1a1a; }
    .wrap { max-width: 480px; margin: 0 auto; }
    .top-bar { height: 4px; background: #d97706; border-radius: 4px 4px 0 0; }
    .card { background: #fff; border-radius: 0 0 12px 12px; padding: 40px 40px 32px; }
    .brand { font-size: 1.1rem; font-weight: 800; color: #E8472A; letter-spacing: -0.3px; margin-bottom: 32px; }
    .brand span { color: #1a1a1a; }
    h1 { font-size: 1.35rem; font-weight: 700; color: #1a1a1a; margin-bottom: 12px; line-height: 1.3; }
    p { font-size: 0.9rem; color: #555; line-height: 1.75; margin-bottom: 14px; }
    .divider { border: none; border-top: 1px solid #f0f0f0; margin: 28px 0; }
    .warning-box { background: #fffbeb; border-left: 3px solid #d97706; padding: 12px 16px; border-radius: 0 6px 6px 0; font-size: 0.88rem; color: #555; line-height: 1.65; margin: 16px 0; }
    .notice { background: #fafafa; border-radius: 6px; padding: 12px 16px; font-size: 0.85rem; color: #777; line-height: 1.65; }
    .footer { margin-top: 24px; text-align: center; font-size: 0.72rem; color: #bbb; line-height: 1.7; }
</style>
</head>
<body>
<div class="wrap">
    <div class="top-bar"></div>
    <div class="card">
        <div class="brand">Pick<span>Sell</span></div>

        <h1>Compliance warning issued</h1>
        <p>Hi <strong>{{ $user->full_name }}</strong>,</p>
        <p>You have received an official compliance warning from the PickSell admin team regarding your seller account.</p>

        <hr class="divider">

        <p style="font-size:0.78rem;font-weight:700;color:#999;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:6px;">Warning Details</p>
        <div class="warning-box">{{ $warning }}</div>

        <hr class="divider">

        <div class="notice">
            Please take immediate action to address this issue. Continued violations may result in the suspension or permanent deactivation of your seller account.
        </div>

            <p style="margin-top:20px;">Questions? Contact us at <a href="mailto:{{ config('mail.from.address') }}" style="color:#E8472A;text-decoration:none;">{{ config('mail.from.address') }}</a></p>
    </div>
    <div class="footer">
        © {{ date('Y') }} PickSell &nbsp;·&nbsp; All rights reserved.
    </div>
</div>
</body>
</html>
