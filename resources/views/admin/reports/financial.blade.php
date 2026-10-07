@extends('admin.layout')
@section('styles')
@vite('resources/css/views/admin-reports.css')
@endsection
@section('title', 'Reports')

@section('content')
<nav class="report-tabs" aria-label="Report type">
    <a href="{{ route('admin.reports', array_merge($filters, ['tab' => 'operational'])) }}">Operational</a>
    <a href="{{ route('admin.reports', array_merge($filters, ['tab' => 'financial'])) }}" class="active" aria-current="page">Financial</a>
</nav>

@include('admin.reports._filters', ['tab' => 'financial'])

<div class="stat-grid">
    <div class="stat-card blue"><div class="stat-card-num">{{ number_format($data['completed_orders']) }}</div><div class="stat-card-label">Orders posted</div></div>
    <div class="stat-card green"><div class="stat-card-num">₱{{ number_format($data['gross_sales'], 2) }}</div><div class="stat-card-label">Gross sales</div></div>
    <div class="stat-card coral"><div class="stat-card-num">₱{{ number_format($data['commission'], 2) }}</div><div class="stat-card-label">Commission net of reversals</div><small>Current new-order rate: {{ number_format($data['current_commission_rate'], 2) }}%</small></div>
    <div class="stat-card blue"><div class="stat-card-num">₱{{ number_format($data['seller_net'], 2) }}</div><div class="stat-card-label">Seller net after adjustments</div></div>
    <div class="stat-card"><div class="stat-card-num">₱{{ number_format($data['refunds'], 2) }}</div><div class="stat-card-label">Refunds</div></div>
    <div class="stat-card"><div class="stat-card-num">₱{{ number_format($data['adjustments'], 2) }}</div><div class="stat-card-label">Refund adjustments</div></div>
    <div class="stat-card"><div class="stat-card-num">₱{{ number_format($data['financial_total'], 2) }}</div><div class="stat-card-label">Sales less refunds</div></div>
    <div class="stat-card"><div class="stat-card-num">{{ number_format($data['new_buyers']) }}</div><div class="stat-card-label">New buyers</div></div>
    <div class="stat-card"><div class="stat-card-num">{{ number_format($data['new_sellers']) }}</div><div class="stat-card-label">New sellers</div></div>
</div>

<div class="report-reconciliation">
    Commission plus seller net reconciles to sales less refunds:
    <strong>₱{{ number_format($data['commission'] + $data['seller_net'], 2) }}</strong>
    <span aria-hidden="true">=</span>
    <strong>₱{{ number_format($data['financial_total'], 2) }}</strong>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card-header"><span class="card-title">Weekly Sales Performance</span></div>
        <div class="card-body"><div id="salesChart"></div></div>
        <div data-sales-chart data-sales='@json($data["weekly_sales"])' data-months='@json($data["weeks"])' hidden></div>
    </section>
    <section class="card">
        <div class="card-header"><span class="card-title">Top Sellers</span></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Seller</th><th>Gross sales</th><th>Orders net</th><th>Commission</th><th>Seller net</th></tr></thead>
                <tbody>
                    @forelse($data['top_sellers'] as $seller)
                        <tr>
                            <td>{{ $seller['name'] }}</td>
                            <td>₱{{ number_format($seller['gross_sales'], 2) }}</td>
                            <td>₱{{ number_format($seller['financial_total'], 2) }}</td>
                            <td>₱{{ number_format($seller['commission'], 2) }}</td>
                            <td>₱{{ number_format($seller['seller_net'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No seller totals available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<section class="card">
    <div class="card-header"><span class="card-title">Financial Totals by Seller</span></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Seller</th><th>Gross sales</th><th>Commission</th><th>Seller net</th><th>Refunds</th><th>Sales less refunds</th></tr></thead>
            <tbody>
                @forelse($data['seller_totals'] as $seller)
                    <tr>
                        <td>{{ $seller['name'] }}</td>
                        <td>₱{{ number_format($seller['gross_sales'], 2) }}</td>
                        <td>₱{{ number_format($seller['commission'], 2) }}</td>
                        <td>₱{{ number_format($seller['seller_net'], 2) }}</td>
                        <td>₱{{ number_format($seller['refunds'], 2) }}</td>
                        <td>₱{{ number_format($seller['financial_total'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No ledger transactions match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <div class="card-header"><span class="card-title">Ledger Transactions ({{ number_format($data['transaction_count']) }})</span></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Date</th><th>Order</th><th>Seller</th><th>Type</th><th>Debit</th><th>Credit</th><th>Reference</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($data['transactions'] as $transaction)
                    <tr>
                        <td>{{ $transaction->created_at?->format('M d, Y H:i') ?? '—' }}</td>
                        <td>{{ $transaction->order?->order_number ?? '—' }}</td>
                        <td>{{ $transaction->seller?->full_name ?? '—' }}</td>
                        <td>{{ \Illuminate\Support\Str::headline($transaction->type) }}</td>
                        <td>₱{{ number_format((float) $transaction->debit, 2) }}</td>
                        <td>₱{{ number_format((float) $transaction->credit, 2) }}</td>
                        <td>{{ $transaction->reference_type ? ucfirst($transaction->reference_type) . ' #' . $transaction->reference_id : '—' }}</td>
                        <td>{{ ucfirst($transaction->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8">No ledger transactions match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="report-pagination">{{ $data['transactions']->links() }}</div>
</section>
@endsection
@section('scripts')
@vite('resources/js/views/admin-reports.js')
@endsection
