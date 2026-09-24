<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Waybill {{ $order->order_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 18px; color: #1f2937; }
        .box { border: 2px solid #e8472a; border-radius: 10px; padding: 14px; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; }
        .value { font-size: 16px; font-weight: 700; margin-top: 4px; margin-bottom: 12px; }
        .row { display: flex; justify-content: space-between; }
        .small { font-size: 12px; }
    </style>
</head>
<body>
    <div class="box">
        <div class="label">PickSell Waybill</div>
        <div class="value">{{ $order->order_number }}</div>

        <div class="row">
            <div>
                <div class="label">Waybill</div>
                <div class="value">{{ $order->waybill_number ?? 'N/A' }}</div>
            </div>
            <div>
                <div class="label">Status</div>
                <div class="value">{{ ucfirst($order->status) }}</div>
            </div>
        </div>

        <div class="label">Seller</div>
        <div class="small">{{ $order->seller?->full_name ?? '—' }}</div>

        <div class="label">Buyer</div>
        <div class="small">{{ $order->buyer?->full_name ?? '—' }}</div>

        <div class="label">Destination</div>
        <div class="small">{{ implode(', ', array_filter([$order->buyer?->house_no, $order->buyer?->street, $order->buyer?->barangay, $order->buyer?->municipality, $order->buyer?->province])) ?: 'N/A' }}</div>

        <div class="label">Items</div>
        <div class="small">{{ $order->product_name }} x {{ $order->quantity }}</div>
    </div>
</body>
</html>