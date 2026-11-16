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
        <div class="stat-card-label">Approved · to send through GCash</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-num oversight-number">₱{{ number_format((float) ($totals['processed']->amount ?? 0), 2) }}</div>
        <div class="stat-card-label">Refunded · GCash sent</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Refunds &amp; Financial Adjustments</span>
            <p class="oversight-description">Approving completes the order and posts a balanced refund, commission-reversal, and seller-adjustment ledger out of the seller's held payment. Then send the refund to the buyer's GCash yourself and record the reference: the app does not transfer money.</p>
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
                        <td>
                            {{ $refund->order?->buyer?->full_name ?? '—' }}
                            @if($refund->returnRequest?->refund_gcash_number)
                                <span class="oversight-meta">GCash {{ $refund->returnRequest->refund_gcash_number }} · {{ $refund->returnRequest->refund_gcash_name }}</span>
                            @endif
                        </td>
                        <td>{{ $refund->order?->seller?->business_name ?? $refund->order?->seller?->full_name ?? '—' }}</td>
                        <td class="oversight-number">₱{{ number_format((float) $refund->amount, 2) }}</td>
                        <td class="oversight-number">₱{{ number_format((float) $refund->commission_reversal, 2) }}</td>
                        <td class="oversight-number">₱{{ number_format((float) $refund->seller_adjustment, 2) }}</td>
                        <td><span class="badge {{ $refund->status === 'processed' ? 'badge-approved' : ($refund->status === 'rejected' ? 'badge-cancelled' : 'badge-pending') }}">{{ $refund->status === 'processed' ? 'Refunded' : ($refund->status === 'approved' ? 'To send' : ucfirst($refund->status)) }}</span></td>
                        <td>
                            @if($refund->status === 'requested')
                                @if(auth()->user()->can('approve', $refund) || auth()->user()->can('reject', $refund))
                                    {{-- One note for either decision; each button posts it to its own route. --}}
                                    <form method="POST" action="{{ route('admin.refunds.approve', $refund) }}" class="refund-review-form">
                                        @csrf @method('PATCH')
                                        <label class="refund-review-label" for="refund-reason-{{ $refund->id }}">Decision note</label>
                                        <textarea id="refund-reason-{{ $refund->id }}" name="reason" rows="2" maxlength="2000" placeholder="Why are you approving or rejecting?" required></textarea>
                                        <div class="refund-review-actions">
                                            @can('approve', $refund)
                                                <button type="submit" class="btn btn-coral btn-sm">Approve &amp; post ledger</button>
                                            @endcan
                                            @can('reject', $refund)
                                                <button type="submit" formaction="{{ route('admin.refunds.reject', $refund) }}" class="btn btn-outline btn-sm">Reject</button>
                                            @endcan
                                        </div>
                                    </form>
                                @endif
                            @elseif($refund->status === 'approved')
                                @can('send', $refund)
                                    <form method="POST" action="{{ route('admin.refunds.sent', $refund) }}" class="refund-review-form">
                                        @csrf @method('PATCH')
                                        <span class="refund-review-hint">Send <strong>₱{{ number_format((float) $refund->amount, 2) }}</strong> to the buyer's GCash, then record the reference.</span>
                                        <label class="sr-only" for="payout-ref-{{ $refund->id }}">GCash reference no.</label>
                                        <div class="refund-review-inline">
                                            <input id="payout-ref-{{ $refund->id }}" name="payout_reference" maxlength="120" placeholder="GCash reference no." required>
                                            <button type="submit" class="btn btn-coral btn-sm">Mark sent</button>
                                        </div>
                                    </form>
                                @else
                                    <span class="oversight-meta">Approved; waiting for Finance to send it through GCash.</span>
                                @endcan
                            @elseif($refund->status === 'processed' && $refund->payout_reference)
                                <span class="oversight-meta">GCash ref. {{ $refund->payout_reference }} · {{ $refund->processed_at?->format('M d, Y') }}</span>
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
