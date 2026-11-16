<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Drop-off {{ $batch->batch_no }}</title>
    <style>
        @page { size: A4 portrait; margin: 28pt; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5pt; color: #000; }
        h1 { font-size: 16pt; margin: 0 0 2pt; }
        table.head { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
        table.head td { vertical-align: top; padding: 0; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list th, table.list td { border: .75pt solid #000; padding: 4pt 5pt; text-align: left; vertical-align: middle; }
        table.list th { background: #eee; font-size: 7.5pt; text-transform: uppercase; }
        .box { width: 12pt; height: 12pt; border: 1pt solid #000; display: inline-block; }
        .sign td { padding-top: 22pt; }
        .muted { color: #444; }
    </style>
</head>
<body>
<table class="head"><tr>
    <td>
        <h1>Seller drop-off manifest</h1>
        <div><strong>{{ $batch->seller?->business_name ?: $batch->seller?->full_name }}</strong> → <strong>{{ $batch->originHub?->name }}</strong></div>
        <div class="muted">{{ $batch->parcel_count }} parcels · created {{ $batch->created_at->format('M d, Y h:i A') }}</div>
        @if($batch->originHub?->address)<div class="muted">{{ $batch->originHub->address }}</div>@endif
    </td>
    <td style="text-align:right;width:240pt">
        @include('pickrush.partials.qr', ['value' => $batch->batch_no, 'module' => 3])
        <div style="font-weight:bold;letter-spacing:1pt;text-align:right">{{ $batch->batch_no }}</div>
    </td>
</tr></table>

<table class="list">
    <thead><tr><th>#</th><th>Tracking no.</th><th>Order</th><th>Buyer</th><th>Destination</th><th>Received</th></tr></thead>
    <tbody>
    @foreach($batch->parcels as $p)
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td><strong>{{ $p->tracking_no }}</strong></td>
            <td>{{ $orderNumbers[$p->id] ?? '—' }}</td>
            <td>{{ $p->consignee_name }}</td>
            <td>{{ $p->delivery_address }}</td>
            <td><span class="box"></span></td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="head sign"><tr>
    <td>Dropped off by (seller): ______________________</td>
    <td>Received by (station): ______________________</td>
</tr></table>
<p class="muted">The station scans this code, then every parcel. You get a receipt listing what was received. Parcels not dropped off within {{ \App\Models\Order::DROPOFF_WITHIN_DAYS }} days of hand-over are cancelled.</p>
</body>
</html>
