@extends('admin.layout')
@section('title', 'Refunds')
@section('styles')
@vite('resources/css/views/admin-oversight.css')
@endsection

@section('content')
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-card-num oversight-number">{{ number_format($totals['requested']->total ?? 0) }}</div>
        <div class="stat-card-label">Awaiting approval · ₱{{ number_format((float) ($totals['requested']->amount ?? 0), 2) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-num oversight-number">₱{{ number_format((float) ($totals['approved']->amount ?? 0), 2) }}</div>
        <div class="stat-card-label">Approved · awaiting seller payment</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-num oversight-number">₱{{ number_format((float) ($totals['processed']->amount ?? 0), 2) }}</div>
        <div class="stat-card-label">Processed · seller-confirmed</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Refunds &amp; Financial Adjustments</span>
            <p class="oversight-description">Approving posts a balanced refund, commission-reversal, and seller-adjustment ledger. Payment is not transferred automatically.</p>
        </div>
        <form method="GET" class="oversight-filters">
            <select name="status" class="filter-select" data-submit-on-change aria-label="Filter refunds">
                <option value="all" @selected($status === 'all')>All refunds</option>
                @foreach(\App\Models\Refund::STATUSES as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                @endforeach
            </select>
        </form>
    </div>
    @if($refunds->isEmpty())
        <div class="oversight-empty"><strong>No refunds</strong>Return refunds awaiting financial review will appear here.</div>
    @else
        <div class="oversight-table-wrap">
            <table>
                <thead>
                    <tr><th>Order</th><th>Buyer</th><th>Seller</th><th>Gross refund</th><th>Commission reversal</th><th>Seller adjustment</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                @foreach($refunds as $refund)
                    <tr>
                        <td>
                            @if($refund->returnRequest)
                                <a href="{{ route('admin.returns.show', $refund->returnRequest) }}"><strong>{{ $refund->order?->order_number ?? '#'.$refund->order_id }}</strong></a>
                            @else
                                <strong>{{ $refund->order?->order_number ?? '#'.$refund->order_id }}</strong>
                            @endif
                            <span class="oversight-meta">Refund #{{ $refund->id }}</span>
                        </td>
                        <td>{{ $refund->order?->buyer?->full_name ?? '—' }}</td>
                        <td>{{ $refund->order?->seller?->business_name ?? $refund->order?->seller?->full_name ?? '—' }}</td>
                        <td class="oversight-number">₱{{ number_format((float) $refund->amount, 2) }}</td>
                        <td class="oversight-number">₱{{ number_format((float) $refund->commission_reversal, 2) }}</td>
                        <td class="oversight-number">₱{{ number_format((float) $refund->seller_adjustment, 2) }}</td>
                        <td><span class="badge {{ $refund->status === 'processed' ? 'badge-approved' : ($refund->status === 'rejected' ? 'badge-cancelled' : 'badge-pending') }}">{{ ucfirst($refund->status) }}</span></td>
                        <td>
                            @if($refund->status === 'requested')
                                @can('approve', $refund)
                                    <form method="POST" action="{{ route('admin.refunds.approve', $refund) }}" class="refund-review-form">
                                        @csrf @method('PATCH')
                                        <label class="sr-only" for="approve-refund-{{ $refund->id }}">Approval reason</label>
                                        <textarea id="approve-refund-{{ $refund->id }}" name="reason" rows="2" maxlength="2000" placeholder="Approval reason" required></textarea>
                                        <button type="submit" class="btn btn-coral btn-sm">Approve &amp; post ledger</button>
                                    </form>
                                @endcan
                                @can('reject', $refund)
                                    <form method="POST" action="{{ route('admin.refunds.reject', $refund) }}" class="refund-review-form">
                                        @csrf @method('PATCH')
                                        <label class="sr-only" for="reject-refund-{{ $refund->id }}">Rejection reason</label>
                                        <textarea id="reject-refund-{{ $refund->id }}" name="reason" rows="2" maxlength="2000" placeholder="Rejection reason" required></textarea>
                                        <button type="submit" class="btn btn-outline btn-sm">Reject</button>
                                    </form>
                                @endcan
                            @elseif($refund->decision_notes)
                                <span class="oversight-meta">{{ $refund->decision_notes }}</span>
                            @else
                                <span class="oversight-meta">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($refunds->hasPages())<div class="dashboard-pagination">{{ $refunds->links() }}</div>@endif
    @endif
</div>
@endsection
