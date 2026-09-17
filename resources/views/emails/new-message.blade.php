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
    .brand span { color: #1a1a1a; }
    h1 { font-size: 1.35rem; font-weight: 700; color: #1a1a1a; margin-bottom: 12px; line-height: 1.3; }
    p { font-size: 0.9rem; color: #555; line-height: 1.75; margin-bottom: 14px; }
    .divider { border: none; border-top: 1px solid #f0f0f0; margin: 28px 0; }
    .sender-row { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
    .avatar { width: 38px; height: 38px; border-radius: 50%; background: #E8472A; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; flex-shrink: 0; }
    .sender-name { font-size: 0.9rem; font-weight: 700; color: #1a1a1a; }
    .sender-role { font-size: 0.75rem; color: #aaa; margin-top: 1px; }
    .product-box { background: #fafafa; border: 1px solid #f0f0f0; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; }
    .product-label { font-size: 0.7rem; font-weight: 700; color: #aaa; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px; }
    .product-name { font-size: 0.88rem; font-weight: 600; color: #1a1a1a; }
    .product-price { font-size: 0.82rem; color: #E8472A; font-weight: 700; margin-top: 2px; }
    .message-box { background: #fafafa; border-radius: 8px; padding: 14px 16px; font-size: 0.9rem; color: #444; line-height: 1.65; margin-bottom: 24px; border-left: 3px solid #E8472A; }
    .btn { display: inline-block; background: #E8472A; color: #fff !important; padding: 12px 28px; border-radius: 7px; text-decoration: none; font-weight: 600; font-size: 0.88rem; }
    .footer { margin-top: 24px; text-align: center; font-size: 0.72rem; color: #bbb; line-height: 1.7; }
</style>
</head>
<body>
<div class="wrap">
    <div class="top-bar"></div>
    <div class="card">
        <div class="brand">Pick<span>Sell</span></div>

        <h1>You have a new message</h1>
        <p>Hi <strong>{{ $message->receiver->full_name }}</strong>,</p>

        <div class="sender-row">
            <div class="avatar">{{ strtoupper(substr($message->sender->first_name, 0, 1)) }}</div>
            <div>
                <div class="sender-name">{{ $message->sender->full_name }}</div>
                <div class="sender-role">{{ ucfirst($message->sender->role) }}</div>
            </div>
        </div>

        @if($message->product)
        <div class="product-box">
            <div class="product-label">Inquiring about</div>
            <div class="product-name">{{ $message->product->name }}</div>
            <div class="product-price">₱{{ number_format($message->product->effective_price, 2) }}</div>
        </div>
        @endif

        <div class="message-box">{{ $message->body }}</div>

        <a href="{{ url('/'.($message->receiver->role === 'seller' ? 'seller' : 'buyer').'/chat?user='.$message->sender_id) }}" class="btn">Reply on PickSell</a>
    </div>
    <div class="footer">
        © {{ date('Y') }} PickSell &nbsp;·&nbsp; You received this because someone messaged you on PickSell.
    </div>
</div>
</body>
</html>
