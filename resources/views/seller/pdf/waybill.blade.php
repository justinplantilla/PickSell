<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Waybill {{ $order->order_number }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 14px 12px; background: #fff; color: #20242b; font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; }
        .receipt { width: 100%; }
        .brand { text-align: center; color: #e8472a; font-size: 20px; font-weight: 800; letter-spacing: -1px; }
        .brand span { color: #20242b; }
        .subtitle { margin-top: 2px; color: #6b7280; font-size: 8px; text-align: center; text-transform: uppercase; letter-spacing: 1.3px; }
        .rule { border-top: 1px dashed #9ca3af; margin: 10px 0; }
        .receipt-title { text-align: center; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .meta { width: 100%; margin-top: 8px; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta td:first-child { color: #6b7280; width: 38%; }
        .meta td:last-child { text-align: right; font-weight: 700; word-break: break-all; }
        .section-title { margin: 10px 0 5px; color: #e8472a; font-size: 8px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .address { line-height: 1.45; }
        .item { width: 100%; border-collapse: collapse; margin-top: 3px; }
        .item th { padding: 4px 0; border-bottom: 1px solid #d1d5db; color: #6b7280; font-size: 8px; text-align: left; text-transform: uppercase; }
        .item td { padding: 6px 0; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .item th:last-child, .item td:last-child { text-align: right; }
        .product-name { font-weight: 700; }
        .muted { color: #6b7280; font-size: 8px; }
        .waybill { margin: 10px 0; padding: 9px 7px; border: 1px solid #e8472a; text-align: center; }
        .waybill-label { color: #e8472a; font-size: 8px; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; }
        .waybill-number { margin-top: 4px; font-size: 15px; font-weight: 800; letter-spacing: 1px; word-break: break-all; }
        .status { display: inline-block; padding: 4px 8px; border-radius: 10px; background: #fff3f0; color: #c93a20; font-size: 8px; font-weight: 800; text-transform: uppercase; }
        .footer { margin-top: 13px; color: #6b7280; font-size: 8px; line-height: 1.5; text-align: center; }
        .footer strong { color: #20242b; }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="brand">Pick<span>Sell</span></div>
        <div class="subtitle">Seller dispatch receipt</div>
        <div class="rule"></div>
        <div class="receipt-title">Waybill / Handover Slip</div>

        <table class="meta">
            <tr><td>Order no.</td><td>{{ $order->order_number }}</td></tr>
            <tr><td>Issued</td><td>{{ $order->handed_over_at?->format('M d, Y h:i A') ?? now()->format('M d, Y h:i A') }}</td></tr>
        </table>

        <div class="waybill">
            <div class="waybill-label">Waybill number</div>
            <div class="waybill-number">{{ $order->waybill_number ?? 'PENDING' }}</div>
        </div>

        <div class="section-title">Shipment details</div>
        <table class="meta">
            <tr><td>Seller</td><td>{{ $order->seller?->business_name ?? $order->seller?->full_name ?? '—' }}</td></tr>
            <tr><td>Buyer</td><td>{{ $order->buyer?->full_name ?? '—' }}</td></tr>
            <tr><td>Status</td><td><span class="status">{{ str_replace('_', ' ', $order->status) }}</span></td></tr>
        </table>

        <div class="section-title">Delivery address</div>
        <div class="address">{{ implode(', ', array_filter([$order->buyer?->house_no, $order->buyer?->street, $order->buyer?->barangay, $order->buyer?->municipality, $order->buyer?->province])) ?: 'Address unavailable' }}</div>

        <div class="section-title">Item summary</div>
        <table class="item">
            <thead><tr><th>Product</th><th>Qty</th></tr></thead>
            <tbody><tr><td><span class="product-name">{{ $order->product_name }}</span><br><span class="muted">Order item</span></td><td>{{ $order->quantity }}</td></tr></tbody>
        </table>

        <div class="rule"></div>
        <div class="footer"><strong>Keep this receipt for tracking.</strong><br>Present the waybill number when handing the parcel to logistics.<br>Thank you for using PickSell.</div>
    </div>
</body>
</html>