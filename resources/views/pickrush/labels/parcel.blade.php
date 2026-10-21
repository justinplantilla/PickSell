<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Label {{ $parcel->tracking_no }}</title>
    {{-- 4 × 6 in thermal label. Rendered by dompdf for the PDF and by browsers for on-screen printing. --}}
    <style>
        @page { size: 288pt 432pt; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 10pt; font-family: DejaVu Sans, Arial, sans-serif; font-size: 8pt; color: #000; background: #fff; }
        .label { border: 1.5pt solid #000; }
        .row { border-bottom: 1pt solid #000; padding: 5pt 7pt; }
        .row:last-child { border-bottom: 0; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid td { vertical-align: top; padding: 0; }
        .brand { font-size: 15pt; font-weight: bold; letter-spacing: -.3pt; }
        .brand-sub { font-size: 6.5pt; text-transform: uppercase; letter-spacing: 1pt; }
        .cod { text-align: right; }
        .cod-label { font-size: 7pt; font-weight: 700; text-transform: uppercase; }
        .cod-amount { font-size: 16pt; font-weight: bold; }
        .tracking { text-align: center; font-size: 12pt; font-weight: bold; letter-spacing: 1.5pt; margin-top: 2pt; }
        .bin { font-size: 18pt; font-weight: bold; line-height: 1; }
        .small { font-size: 6.5pt; text-transform: uppercase; letter-spacing: .6pt; color: #333; }
        .name { font-size: 10pt; font-weight: bold; }
        .addr { font-size: 8pt; line-height: 1.3; }
        .return { display: inline-block; border: 1pt solid #000; padding: 1pt 4pt; font-weight: bold; }
    </style>
</head>
<body>
@php
    $destHub = $parcel->is_return ? $parcel->originHub : $parcel->destHub;
    $bin = $destHub ? \App\Pickrush\Services\Province\Sorting::binFor($destHub) : 'ADDRESS CHECK';
@endphp
<div class="label">
    <div class="row">
        <table class="grid"><tr>
            <td><div class="brand">Pickrush</div><div class="brand-sub">Logistics</div></td>
            <td class="cod"><div class="cod-label">Collect on delivery</div><div class="cod-amount">₱{{ number_format((float) $parcel->cod_amount, 2) }}</div></td>
        </tr></table>
    </div>

    <div class="row" style="text-align:center">
        @include('pickrush.partials.barcode', ['value' => $parcel->tracking_no, 'module' => 0.95, 'height' => 46])
        <div class="tracking">{{ $parcel->tracking_no }}</div>
    </div>

    <div class="row">
        <table class="grid"><tr>
            <td>
                <div class="small">Deliver to station</div>
                <div class="bin">{{ $bin }}</div>
                <div class="addr">{{ $destHub?->name ?? 'Address to confirm' }}@if($destHub) · {{ $destHub->province_name }}@endif</div>
                <div class="addr">From: {{ $parcel->originHub?->name ?? '—' }}</div>
                @if($parcel->is_return)<div class="return">RETURN TO SELLER</div>@endif
            </td>
            <td style="width:92pt;text-align:right">@include('pickrush.partials.qr', ['value' => $parcel->tracking_no, 'module' => 3])</td>
        </tr></table>
    </div>

    <div class="row">
        <div class="small">Recipient</div>
        <div class="name">{{ $parcel->is_return ? $parcel->shipper_name : $parcel->consignee_name }}</div>
        <div class="addr">{{ $parcel->is_return ? $parcel->shipper_mobile : $parcel->consignee_mobile }}</div>
        <div class="addr">{{ $parcel->is_return ? $parcel->pickup_address : $parcel->delivery_address }}</div>
    </div>

    <div class="row">
        <table class="grid"><tr>
            <td>
                <div class="small">Sender</div>
                <div><strong>{{ $parcel->is_return ? $parcel->consignee_name : $parcel->shipper_name }}</strong></div>
                <div class="addr">{{ $parcel->is_return ? $parcel->delivery_address : $parcel->pickup_address }}</div>
            </td>
            <td style="width:110pt">
                <div class="small">Contents</div>
                <div class="addr">{{ $parcel->item_description ?? '—' }}</div>
                @if($parcel->weight_kg)<div class="addr">{{ $parcel->weight_kg }} kg</div>@endif
                @if($orderNumber)<div class="addr">Order {{ $orderNumber }}</div>@endif
            </td>
        </tr></table>
    </div>

    <div class="row" style="font-size:6.5pt">
        Booked {{ $parcel->created_at->format('M d, Y') }} · Track at {{ config('pickrush.domain') }}/track · Rider: collect exactly the COD amount; do not open the parcel.
    </div>
</div>
</body>
</html>
