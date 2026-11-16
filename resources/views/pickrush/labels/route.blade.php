<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Route {{ $route->route_no }}</title>
    <style>
        @page { size: A4 portrait; margin: 28pt; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; color: #000; }
        h1 { font-size: 16pt; margin: 0 0 2pt; }
        table.head { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
        table.head td { vertical-align: top; padding: 0; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list th, table.list td { border: .75pt solid #000; padding: 5pt; text-align: left; vertical-align: middle; }
        table.list th { background: #eee; font-size: 7.5pt; text-transform: uppercase; }
        .num { text-align: right; }
        .sign td { padding-top: 22pt; }
        .muted { color: #444; }
    </style>
</head>
<body>
<table class="head"><tr>
    <td>
        <h1>{{ $portalCompanyName }} route sheet</h1>
        <div><strong>{{ $route->hub->name }}</strong> → {{ $route->stops->map(fn ($s) => $s->toHub->name)->join(' → ') }}</div>
        <div class="muted">{{ $route->stops->count() }} stops · {{ $route->stops->sum('parcel_count') }} parcels
            @if($route->vehicle_plate) · {{ $route->vehicle_plate }} · {{ $route->driver_name }}@endif
            @if($route->dispatched_at) · dispatched {{ $route->dispatched_at->format('M d, Y h:i A') }}@endif</div>
    </td>
    <td style="text-align:right;width:240pt">
        @include('pickrush.partials.barcode', ['value' => $route->route_no, 'module' => 1, 'height' => 34])
        <div style="font-weight:bold;letter-spacing:1pt;text-align:center">{{ $route->route_no }}</div>
    </td>
</tr></table>

<table class="list">
    <thead><tr><th>Stop</th><th>Station</th><th class="num">Leg</th><th class="num">Parcels</th><th>Trip (scan at the station)</th><th>Due by</th><th>Received by / time</th></tr></thead>
    <tbody>
    @foreach($route->stops as $stop)
        <tr>
            <td><strong>{{ $stop->stop_order }}</strong></td>
            <td><strong>{{ $stop->toHub->name }}</strong>@if($stop->toHub->address)<div class="muted">{{ $stop->toHub->address }}</div>@endif</td>
            <td class="num">{{ isset($legs[$stop->id]) ? number_format($legs[$stop->id], 1).' km' : '—' }}</td>
            <td class="num">{{ $stop->parcel_count }}</td>
            <td>
                @include('pickrush.partials.barcode', ['value' => $stop->manifest_no, 'module' => 1, 'height' => 22])
                <div style="text-align:center">{{ $stop->manifest_no }}</div>
            </td>
            <td>{{ $stop->due_at?->format('M d, h:i A') ?? '—' }}</td>
            <td></td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="head sign"><tr>
    <td>Released by (province hub): ______________________</td>
    <td>Driver: ______________________</td>
</tr></table>
<p class="muted">Drop each stop's parcels only at that station, in this order. Unload only the parcels on that stop's trip; the station scans each one against it. Distances are straight-line, not road distance.</p>
</body>
</html>
