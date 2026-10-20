<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manifest {{ $manifest->manifest_no }}</title>
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
        <h1>Pickrush trip manifest</h1>
        <div><strong>{{ $manifest->fromHub->name }}</strong> → <strong>{{ $manifest->toHub->name }}</strong></div>
        <div class="muted">{{ $manifest->parcel_count }} parcels · {{ ucfirst($manifest->status) }}
            @if($manifest->vehicle_plate) · {{ $manifest->vehicle_plate }} · {{ $manifest->driver_name }}@endif
            @if($manifest->dispatched_at) · dispatched {{ $manifest->dispatched_at->format('M d, Y h:i A') }}@endif</div>
    </td>
    <td style="text-align:right;width:240pt">
        @include('pickrush.partials.barcode', ['value' => $manifest->manifest_no, 'module' => 1, 'height' => 34])
        <div style="font-weight:bold;letter-spacing:1pt;text-align:center">{{ $manifest->manifest_no }}</div>
    </td>
</tr></table>

<table class="list">
    <thead><tr><th>#</th><th>Tracking no.</th><th>Recipient</th><th>Destination</th><th style="text-align:right">COD</th><th>Loaded</th><th>Received</th></tr></thead>
    <tbody>
    @foreach($manifest->items as $item)
        @php $p = $item->parcel; $to = $p->is_return ? $p->originHub : $p->destHub; @endphp
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td><strong>{{ $p->tracking_no }}</strong>@if($p->is_return) <span class="muted">(return)</span>@endif</td>
            <td>{{ $p->is_return ? $p->shipper_name : $p->consignee_name }}</td>
            <td>{{ $to?->name ?? '—' }}</td>
            <td style="text-align:right">₱{{ number_format((float) $p->cod_amount, 2) }}</td>
            <td><span class="box"></span></td>
            <td><span class="box"></span></td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="head sign"><tr>
    <td>Released by (sending hub): ______________________</td>
    <td>Driver: ______________________</td>
    <td>Received by: ______________________</td>
</tr></table>
<p class="muted">Every parcel is scanned at loading and again on arrival. A parcel on this list that is not scanned in is reported missing against the sending hub.</p>
</body>
</html>
