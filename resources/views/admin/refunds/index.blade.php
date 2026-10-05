@extends('admin.layout')
@section('title', 'Refunds')
@section('styles')
@vite('resources/css/views/admin-oversight.css')
@endsection

@section('content')
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-card-num oversight-number">₱{{ number_format((float) ($totals['refund_due']->amount ?? 0), 2) }}</div>
        <div class="stat-card-label">Refunds due · {{ number_format($totals['refund_due']->total ?? 0) }} {{ ($totals['refund_due']->total ?? 0) == 1 ? 'request' : 'requests' }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-num oversight-number">₱{{ number_format((float) ($totals['completed']->amount ?? 0), 2) }}</div>
        <div class="stat-card-label">Refunded · {{ number_format($totals['completed']->total ?? 0) }} {{ ($totals['completed']->total ?? 0) == 1 ? 'request' : 'requests' }}</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Refunds</span>
            <p class="oversight-description">Refunds owed and paid for accepted returns. Sellers issue refunds; approval overrides are reserved (refunds.approve).</p>
        </div>
        <form method="GET" class="oversight-filters">
            <select name="status" class="filter-select" data-submit-on-change aria-label="Filter refunds">
                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>Due and refunded</option>
                <option value="refund_due" {{ $status === 'refund_due' ? 'selected' : '' }}>Refund due</option>
                <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Refunded</option>
            </select>
        </form>
    </div>
    @if($refunds->isEmpty())
        <div class="oversight-empty"><strong>No refunds</strong>Returns that reach Refund due appear here.</div>
    @else
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>Order</th><th>Buyer</th><th>Seller</th><th>Amount</th><th>Status</th><th>Due since</th><th>Completed</th></tr></thead>
                <tbody>
                @foreach($refunds as $refund)
                    <tr>
                        <td><a href="{{ route('admin.returns.show', $refund) }}"><strong>{{ $refund->order?->order_number ?? '#' . $refund->order_id }}</strong></a></td>
                        <td>{{ $refund->buyer?->full_name ?? '—' }}</td>
                        <td>{{ $refund->seller?->business_name ?? $refund->seller?->full_name ?? '—' }}</td>
                        <td class="oversight-number">{{ $refund->refund_amount !== null ? '₱' . number_format((float) $refund->refund_amount, 2) : '—' }}</td>
                        <td><x-return-request-status-badge :status="$refund->status" /></td>
                        <td>{{ $refund->refund_due_at?->format('M d, Y') ?? '—' }}</td>
                        <td>{{ $refund->completed_at?->format('M d, Y') ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($refunds->hasPages())<div class="dashboard-pagination">{{ $refunds->links() }}</div>@endif
    @endif
</div>
@endsection
