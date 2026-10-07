@extends('admin.layout')
@section('title', $returnRequest->dispute_status === 'not_open' ? 'Return Request' : 'Return Dispute')
@section('styles')
@vite('resources/css/views/admin-complaints.css')
@endsection

@section('content')
<p class="return-back-link">
    @if($returnRequest->dispute_status === 'not_open')
        <a class="btn btn-outline btn-sm" href="{{ route('admin.returns') }}">Back to returns</a>
    @else
        <a class="btn btn-outline btn-sm" href="{{ route('admin.disputes') }}">Back to disputes</a>
    @endif
</p>
<div class="return-detail-grid">
    <section class="card">
        <div class="card-header">
            <span class="card-title">{{ $returnRequest->dispute_status === 'not_open' ? 'Return details' : 'Dispute details' }}</span>
            @if($returnRequest->dispute_status === 'not_open')
                <x-return-request-status-badge :status="$returnRequest->status" />
            @else
                <span class="badge {{ $returnRequest->dispute_status === 'open' ? 'badge-pending' : 'badge-approved' }}">Dispute {{ $returnRequest->dispute_status }}</span>
            @endif
        </div>
        <div class="card-body">
            <dl class="return-detail-list">
                <div><dt>Order</dt><dd>{{ $returnRequest->order->order_number }}</dd></div>
                <div><dt>Product</dt><dd>{{ $returnRequest->order->product_name }} × {{ $returnRequest->order->quantity }}</dd></div>
                <div><dt>Buyer</dt><dd>{{ $returnRequest->buyer->full_name }} · {{ $returnRequest->buyer->email }}</dd></div>
                <div><dt>Seller</dt><dd>{{ $returnRequest->seller->business_name ?? $returnRequest->seller->full_name }}</dd></div>
                <div><dt>Reason</dt><dd>{{ ucfirst(str_replace('_', ' ', $returnRequest->reason)) }}</dd></div>
                <div><dt>Quantity requested</dt><dd>{{ $returnRequest->quantity }} of {{ $returnRequest->order->quantity }}</dd></div>
                <div><dt>Requested refund</dt><dd>₱{{ number_format($returnRequest->refund_amount ?? 0, 2) }}</dd></div>
                <div><dt>Return tracking</dt><dd>{{ ucfirst(str_replace('_', ' ', $returnRequest->tracking_status)) }}{{ $returnRequest->tracking_number ? ' · '.$returnRequest->tracking_number : '' }}</dd></div>
                <div><dt>Request status</dt><dd><x-return-request-status-badge :status="$returnRequest->status" /></dd></div>
                @if($returnRequest->reviewed_at)
                    <div><dt>Inspected by</dt><dd>{{ $returnRequest->reviewer->full_name ?? 'Admin' }} · {{ $returnRequest->reviewed_at->format('M d, Y h:i A') }}</dd></div>
                @endif
            </dl>
            <div class="return-detail-copy"><strong>Buyer explanation</strong><p>{{ $returnRequest->details }}</p></div>
            @if($returnRequest->attachments)
                <div class="return-detail-copy">
                    <strong>Buyer photo / video evidence</strong>
                    <x-return-attachment-gallery :attachments="$returnRequest->attachments" gallery-id="admin-return-attachments-{{ $returnRequest->id }}" />
                </div>
            @else
                <div class="return-detail-copy"><strong>Buyer photo / video evidence</strong><p>No evidence was attached.</p></div>
            @endif
            <div class="return-detail-copy"><strong>Seller rejection</strong><p>{{ $returnRequest->seller_note }}</p></div>
            @if($returnRequest->events->isNotEmpty())
                <div class="return-detail-copy">
                    <strong>Return history</strong>
                    <ol class="return-audit-timeline">
                        @foreach($returnRequest->events as $event)
                            <li>
                                <span class="return-audit-dot"></span>
                                <div>
                                    <strong>{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</strong>
                                    <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('M d, Y h:i:s A') }}</time>
                                    @if($event->actor)<span class="return-audit-actor">by {{ $event->actor->full_name }}</span>@endif
                                    @if($event->notes)<p>{{ $event->notes }}</p>@endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
            @if($returnRequest->admin_notes)
                <div class="return-detail-copy"><strong>Admin resolution</strong><p>{{ $returnRequest->admin_notes }}</p></div>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card-header"><span class="card-title">Admin resolution</span></div>
        <div class="card-body return-workflow-body">
            @if($returnRequest->dispute_status === 'open')
                @can('resolveDispute', $returnRequest)
                <p>Decide whether the return should proceed or the seller's rejection should stand.</p>
                <form method="POST" action="{{ route('admin.returns.resolve', $returnRequest) }}" class="return-transition-form">
                    @csrf @method('PATCH')
                    <label class="form-label" for="decision">Decision</label>
                    <select id="decision" name="decision" class="form-control" required>
                        <option value="approve_return">Approve return and move to Awaiting item</option>
                        <option value="uphold_rejection">Uphold seller rejection and close request</option>
                    </select>
                    <label class="form-label" for="admin-notes">Resolution notes</label>
                    <textarea class="form-control" id="admin-notes" name="admin_notes" rows="4" maxlength="2000" required></textarea>
                    <button class="btn btn-coral" type="submit">Resolve dispute</button>
                </form>
                @else
                <p>This dispute is open. Resolving it requires the returns management permission.</p>
                @endcan
            @else
                <p>This return has not been escalated.</p>
                @if($returnRequest->status === 'requested')
                @can('approve', $returnRequest)
                    <p>Review the buyer's evidence, then approve or reject the request. The decision is audited and sent to both participants.</p>
                    <form method="POST" action="{{ route('admin.returns.approve', $returnRequest) }}" class="return-transition-form">
                        @csrf @method('PATCH')
                        <label class="form-label" for="approve-notes">Approval notes</label>
                        <textarea class="form-control" id="approve-notes" name="admin_notes" rows="3" maxlength="2000" required></textarea>
                        <button class="btn btn-coral" type="submit">Approve Return</button>
                    </form>
                    <form method="POST" action="{{ route('admin.returns.reject', $returnRequest) }}" class="return-transition-form">
                        @csrf @method('PATCH')
                        <label class="form-label" for="reject-notes">Rejection reason</label>
                        <textarea class="form-control" id="reject-notes" name="admin_notes" rows="3" maxlength="2000" required></textarea>
                        <button class="btn btn-danger" type="submit">Reject Return</button>
                    </form>
                @else
                    <p>Approving or rejecting this request requires the returns management permission.</p>
                @endcan
                @elseif($returnRequest->status === 'received')
                @can('inspect', $returnRequest)
                    <p>Record the returned item's condition before authorizing a refund.</p>
                    <form method="POST" action="{{ route('admin.returns.inspect', $returnRequest) }}" class="return-transition-form">
                        @csrf @method('PATCH')
                        <label class="form-label" for="inspection-notes">Inspection findings</label>
                        <textarea class="form-control" id="inspection-notes" name="admin_notes" rows="4" maxlength="2000" required></textarea>
                        <button class="btn btn-coral" type="submit">Complete Inspection</button>
                    </form>
                @else
                    <p>Inspection requires the returns management permission.</p>
                @endcan
                @elseif($returnRequest->status === 'inspected')
                @can('approveRefund', $returnRequest)
                    <p>Inspection is complete. Approve the refund amount to make it available for seller payout.</p>
                    <form method="POST" action="{{ route('admin.returns.approve-refund', $returnRequest) }}" class="return-transition-form">
                        @csrf @method('PATCH')
                        <label class="form-label" for="approved-refund-amount">Approved refund amount (PHP)</label>
                        <input class="form-control" type="number" id="approved-refund-amount" name="refund_amount" min="0.01" max="{{ $returnRequest->order->amount }}" step="0.01" value="{{ old('refund_amount', $returnRequest->refund_amount ?? $returnRequest->order->amount) }}" required>
                        <label class="form-label" for="refund-approval-notes">Refund approval notes</label>
                        <textarea class="form-control" id="refund-approval-notes" name="admin_notes" rows="3" maxlength="2000" required></textarea>
                        <button class="btn btn-coral" type="submit">Approve Refund</button>
                    </form>
                @else
                    <p>Refund approval requires the returns management permission.</p>
                @endcan
                @elseif($returnRequest->status === 'awaiting_item')
                <p>The return is approved and is awaiting the buyer's return parcel.</p>
                @elseif($returnRequest->status === 'approved_for_refund')
                    <p>The refund request is awaiting Finance approval before the seller can issue payment.</p>
                @elseif($returnRequest->status === 'refund_due')
                <p>The approved refund is due. The seller must issue the payment and confirm completion.</p>
                @elseif($returnRequest->status === 'rejected')
                <p>This return request is rejected and closed.</p>
                @else
                <p>This return is closed{{ $returnRequest->completed_at ? ' on '.$returnRequest->completed_at->format('M d, Y h:i A') : '' }}.</p>
                @if($returnRequest->admin_decision)
                    <p class="return-help">Decision: {{ ucfirst(str_replace('_', ' ', $returnRequest->admin_decision)) }}</p>
                    @endif
                @endif
                @if($returnRequest->admin_notes)<p>{{ $returnRequest->admin_notes }}</p>@endif
            @endif
        </div>
    </section>
</div>
@endsection

@section('scripts')
@vite('resources/js/components/return-attachment-lightbox.js')
@endsection