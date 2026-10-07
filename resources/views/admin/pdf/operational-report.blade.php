<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #2d2d2d; font-size: 11px; }
        h1 { color: #e8472a; font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin: 22px 0 8px; }
        .meta { color: #666; margin-bottom: 18px; }
        .cards { width: 100%; margin-bottom: 18px; }
        .cards td { width: 25%; padding: 8px; border: 1px solid #ddd; }
        table.data { width: 100%; border-collapse: collapse; }
        .data th, .data td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        .data th { background: #f2eee8; }
    </style>
</head>
<body>
    <h1>PickSell — Operational Report</h1>
    <div class="meta">Period: {{ \Carbon\Carbon::parse($filters['from'])->format('M d, Y') }} – {{ \Carbon\Carbon::parse($filters['to'])->format('M d, Y') }} · Status: {{ \Illuminate\Support\Str::headline($filters['status']) }} · Seller: {{ \App\Models\User::find($filters['seller_id'])?->full_name ?? 'All sellers' }} · Generated: {{ now()->format('M d, Y H:i') }}</div>
    <table class="cards">
        <tr>
            <td>Orders<br><strong>{{ number_format($data['order_count']) }}</strong></td>
            <td>Average fulfillment<br><strong>{{ number_format($data['average_fulfillment_hours'], 2) }} hours</strong></td>
            <td>Delivery failures<br><strong>{{ number_format($data['delivery_failures']) }}</strong></td>
            <td>Returns / complaints<br><strong>{{ number_format($data['return_count']) }} / {{ number_format($data['complaint_count']) }}</strong></td>
        </tr>
        <tr><td colspan="4">Sorting backlog: <strong>{{ number_format($data['sorting_backlog']) }}</strong></td></tr>
    </table>

    <h2>Orders by Status</h2>
    <table class="data">
        <thead><tr><th>Status</th><th>Orders</th></tr></thead>
        <tbody>
            @forelse($data['status_totals'] as $status => $total)
                <tr><td>{{ \App\Services\Orders\OrderLifecycleService::label($status) }}</td><td>{{ number_format($total) }}</td></tr>
            @empty
                <tr><td colspan="2">No orders match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
    <h2>Rider Workload</h2>
    <table class="data">
        <thead><tr><th>Rider</th><th>Assigned orders</th></tr></thead>
        <tbody>
            @forelse($data['rider_workload'] as $rider)
                <tr><td>{{ $rider['name'] }}</td><td>{{ number_format($rider['orders']) }}</td></tr>
            @empty
                <tr><td colspan="2">No assigned rider orders match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
