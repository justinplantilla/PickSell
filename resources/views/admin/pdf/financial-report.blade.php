<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #2d2d2d; font-size: 11px; }
        h1 { color: #e8472a; font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin: 22px 0 8px; }
        .meta { color: #666; margin-bottom: 18px; }
        table.summary, table.data { width: 100%; border-collapse: collapse; }
        .summary { margin-bottom: 16px; }
        .summary td, .data th, .data td { border: 1px solid #ddd; padding: 7px; text-align: left; }
        .summary td { width: 33%; }
        .data th { background: #f2eee8; }
        .reconcile { background: #f5f0e8; padding: 10px; }
    </style>
</head>
<body>
    <h1>PickSell — Financial Report</h1>
    <div class="meta">Period: {{ \Carbon\Carbon::parse($filters['from'])->format('M d, Y') }} – {{ \Carbon\Carbon::parse($filters['to'])->format('M d, Y') }} · Transaction status: {{ \Illuminate\Support\Str::headline($filters['status']) }} · Seller: {{ \App\Models\User::find($filters['seller_id'])?->full_name ?? 'All sellers' }} · Generated: {{ now()->format('M d, Y H:i') }}</div>
    <table class="summary">
        <tr>
            <td>Orders posted<br><strong>{{ number_format($data['completed_orders']) }}</strong></td>
            <td>Gross sales<br><strong>₱{{ number_format($data['gross_sales'], 2) }}</strong></td>
            <td>Commission, net of reversals<br><strong>₱{{ number_format($data['commission'], 2) }}</strong></td>
            <td>Seller net after adjustments<br><strong>₱{{ number_format($data['seller_net'], 2) }}</strong></td>
        </tr>
        <tr>
            <td>Refunds<br><strong>₱{{ number_format($data['refunds'], 2) }}</strong></td>
            <td>Refund adjustments<br><strong>₱{{ number_format($data['adjustments'], 2) }}</strong></td>
            <td>Sales less refunds<br><strong>₱{{ number_format($data['financial_total'], 2) }}</strong></td>
            <td>New buyers / sellers<br><strong>{{ number_format($data['new_buyers']) }} / {{ number_format($data['new_sellers']) }}</strong></td>
        </tr>
    </table>
    <div class="reconcile">Reconciliation: commission + seller net = sales less refunds · ₱{{ number_format($data['commission'] + $data['seller_net'], 2) }} = ₱{{ number_format($data['financial_total'], 2) }}</div>

    <h2>Weekly Sales Performance</h2>
    <table class="data">
        <thead><tr><th>Week</th><th>Sales</th></tr></thead>
        <tbody>
            @foreach($data['weeks'] as $index => $week)
                <tr><td>{{ $week }}</td><td>₱{{ number_format($data['weekly_sales'][$index], 2) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Financial Totals by Seller</h2>
    <table class="data">
        <thead><tr><th>Seller</th><th>Gross sales</th><th>Commission</th><th>Seller net</th><th>Refunds</th><th>Sales less refunds</th></tr></thead>
        <tbody>
            @forelse($data['seller_totals'] as $seller)
                <tr>
                    <td>{{ $seller['name'] }}</td><td>₱{{ number_format($seller['gross_sales'], 2) }}</td>
                    <td>₱{{ number_format($seller['commission'], 2) }}</td><td>₱{{ number_format($seller['seller_net'], 2) }}</td>
                    <td>₱{{ number_format($seller['refunds'], 2) }}</td><td>₱{{ number_format($seller['financial_total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No ledger transactions match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
