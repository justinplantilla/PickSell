@extends('seller.layout')
@section('title', 'Return Request')
@section('styles')
@vite('resources/css/views/seller-orders.css')
@endsection

@section('content')
<div class="return-detail-shell">
    <a class="btn btn-outline btn-sm return-back-link" href="{{ route('seller.returns') }}">Back to return requests</a>

    <header class="return-detail-header">
        <div>
            <span class="return-detail-kicker">Return request #{{ $returnRequest->id }}</span>
            <h1>Return for <a href="{{ route('seller.orders.show', $returnRequest->order) }}">{{ $returnRequest->order->order_number }}</a></h1>
            <time datetime="{{ $returnRequest->created_at->toIso8601String() }}">Submitted {{ $returnRequest->created_at->format('M d, Y · h:i A') }}</time>
        </div>
        <x-return-request-status-badge :status="$returnRequest->status" />
    </header>

    <div class="return-detail-grid">
        <section class="card return-context-card">
            <div class="card-header"><span class="card-title">Item context</span></div>
            <div class="card-body return-item-context">
                @if($returnRequest->order->product?->primary_image)
                    <img class="return-product-image" src="{{ Storage::disk('public')->url($returnRequest->order->product->primary_image) }}" alt="{{ $returnRequest->order->product_name }}">
                @else
                    <div class="return-product-placeholder" aria-hidden="true">{{ strtoupper(substr($returnRequest->order->product_name, 0, 1)) }}</div>
                @endif
                <div class="return-item-details">
                    <h2>{{ $returnRequest->order->product_name }}</h2>
                    <dl class="return-detail-list">
                        <div><dt>SKU</dt><dd>{{ $returnRequest->order->product?->sku ?? 'Not assigned' }}</dd></div>
                        <div><dt>Purchase price</dt><dd>₱{{ number_format($returnRequest->order->amount / max((int) $returnRequest->order->quantity, 1), 2) }} / unit</dd></div>
                        <div><dt>Quantity requested</dt><dd>{{ $returnRequest->quantity }} of {{ $returnRequest->order->quantity }} units</dd></div>
                        <div><dt>Requested refund</dt><dd>₱{{ number_format($returnRequest->refund_amount ?? 0, 2) }}</dd></div>
                    </dl>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><span class="card-title">Buyer submission</span></div>
            <div class="card-body">
                @php
                    $reasonLabels = [
                        'damaged' => 'Defective / damaged',
                        'wrong_item' => 'Wrong item',
                        'wrong_size' => 'Wrong size',
                        'not_as_described' => 'Item not as described',
                        'other' => 'Other',
                    ];
                @endphp
                <dl class="return-detail-list">
                    <div><dt>Buyer</dt><dd>{{ $returnRequest->buyer->full_name }} · {{ $returnRequest->buyer->email }}</dd></div>
                    <div><dt>Reason</dt><dd>{{ $reasonLabels[$returnRequest->reason] ?? ucfirst(str_replace('_', ' ', $returnRequest->reason)) }}</dd></div>
                </dl>
                <div class="return-detail-copy"><strong>Buyer notes</strong><p>{{ $returnRequest->details }}</p></div>
                @if($returnRequest->attachments)
                    <div class="return-detail-copy">
                        <strong>Photo / video evidence</strong>
                        <x-return-attachment-gallery :attachments="$returnRequest->attachments" gallery-id="seller-return-attachments-{{ $returnRequest->id }}" />
                    </div>
                @else
                    <p class="return-help">No photo or video evidence attached.</p>
                @endif
            </div>
        </section>

        <section class="card">
            <div class="card-header"><span class="card-title">Return logistics</span></div>
            <div class="card-body">
                @php
                    $trackingStates = ['not_started' => 'Not started', 'label_generated' => 'Label generated', 'in_transit' => 'In transit', 'delivered_to_seller' => 'Delivered to seller'];
                    $trackingOrder = ['not_started', 'label_generated', 'in_transit', 'delivered_to_seller'];
                    $trackingIndex = array_search($returnRequest->tracking_status, $trackingOrder, true);
                @endphp
                <ol class="return-logistics-track">
                    @foreach($trackingOrder as $trackingState)
                        <li class="{{ $trackingIndex !== false && $loop->index <= $trackingIndex ? 'is-complete' : '' }} {{ $returnRequest->tracking_status === $trackingState ? 'is-current' : '' }}">
                            <span class="return-track-dot" aria-hidden="true"></span>
                            <span>{{ $trackingStates[$trackingState] }}</span>
                        </li>
                    @endforeach
                </ol>
                <dl class="return-detail-list return-tracking-meta">
                    <div><dt>Carrier</dt><dd>{{ $returnRequest->carrier ?? 'Not provided' }}</dd></div>
                    <div><dt>Tracking number</dt><dd>{{ $returnRequest->tracking_number ?? 'Not provided' }}</dd></div>
                    <div><dt>Last updated</dt><dd>{{ $returnRequest->tracking_updated_at?->format('M d, Y h:i A') ?? 'No tracking update' }}</dd></div>
                </dl>
                <p class="return-help">Tracking is seller-updated; live carrier synchronization is not connected.</p>
                @if($returnRequest->status === 'awaiting_item')
                    <form method="POST" action="{{ route('seller.returns.tracking', $returnRequest) }}" class="return-transition-form">
                        @csrf @method('PATCH')
                        <label class="form-label" for="return-tracking-status">Tracking status</label>
                        <select class="form-control" name="tracking_status" id="return-tracking-status" required>
                            @foreach(['label_generated' => 'Label generated', 'in_transit' => 'In transit', 'delivered_to_seller' => 'Delivered to seller'] as $value => $label)
                                <option value="{{ $value }}" {{ $returnRequest->tracking_status === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="form-label" for="return-carrier">Carrier</label>
                        <input class="form-control" id="return-carrier" name="carrier" value="{{ $returnRequest->carrier }}" maxlength="100">
                        <label class="form-label" for="return-tracking-number">Tracking number</label>
                        <input class="form-control" id="return-tracking-number" name="tracking_number" value="{{ $returnRequest->tracking_number }}" maxlength="120">
                        <button class="btn btn-outline" type="submit">Save tracking update</button>
                    </form>
                @endif
            </div>
        </section>

        <section class="card return-decision-card">
            <div class="card-header"><span class="card-title">Seller decision</span></div>
            <div class="card-body return-workflow-body">
                @if($returnRequest->status === 'requested')
                    <p>Review the evidence and respond to the buyer. This decision is recorded in the audit timeline.</p>
                    <form method="POST" action="{{ route('seller.returns.approve', $returnRequest) }}" class="return-transition-form">
                        @csrf @method('PATCH')
                        <label class="form-label" for="seller-approve-response">Seller response to buyer</label>
                        <textarea class="form-control" id="seller-approve-response" name="seller_note" rows="3" maxlength="2000" required>{{ old('seller_note') }}</textarea>
                        <label class="form-label" for="refund-amount">Refund amount (PHP)</label>
                        <div class="return-currency-input"><span aria-hidden="true">₱</span><input class="form-control" type="number" id="refund-amount" name="refund_amount" min="0.01" max="{{ $returnRequest->order->amount }}" step="0.01" value="{{ old('refund_amount', $returnRequest->refund_amount ?? $returnRequest->order->amount) }}" required></div>
                        <label class="form-label" for="approve-carrier">Return carrier (optional)</label>
                        <input class="form-control" id="approve-carrier" name="carrier" maxlength="100">
                        <label class="form-label" for="approve-tracking-number">Return tracking number (optional)</label>
                        <input class="form-control" id="approve-tracking-number" name="tracking_number" maxlength="120">
                        <button type="submit" class="btn btn-success">Approve Return</button>
                    </form>
                    <form method="POST" action="{{ route('seller.returns.reject', $returnRequest) }}" class="return-transition-form return-reject-form">
                        @csrf @method('PATCH')
                        <label class="form-label" for="seller-reject-response">Seller response to buyer</label>
                        <textarea class="form-control" id="seller-reject-response" name="seller_note" rows="3" maxlength="2000" required>{{ old('seller_note') }}</textarea>
                        <p class="return-help">Rejecting automatically escalates this request to Admin dispute resolution.</p>
                        <button type="submit" class="btn btn-danger">Reject Return</button>
                    </form>
                @elseif($returnRequest->status === 'awaiting_item')
                    <p>Waiting for the return parcel. Confirm receipt only after the item arrives.</p>
                    <form method="POST" action="{{ route('seller.returns.receive', $returnRequest) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-coral">Confirm item received</button>
                    </form>
                @elseif($returnRequest->status === 'received')
                    <p>The returned item is awaiting Admin inspection before a refund can be approved.</p>
                @elseif(in_array($returnRequest->status, ['approved_for_refund', 'refund_due'], true))
                    @if($returnRequest->latestRefund?->status === 'approved')
                        <p>Finance approved this refund. Send the payment through the agreed method; this app does not transfer funds automatically.</p>
                        <p class="return-refund-summary">Refund to issue: <strong>₱{{ number_format($returnRequest->latestRefund->amount, 2) }}</strong></p>
                        <form method="POST" action="{{ route('seller.returns.complete', $returnRequest) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-success">Confirm refund sent</button>
                        </form>
                    @else
                        <p>The refund is awaiting Finance review. Do not issue payment until it is approved.</p>
                        <p class="return-refund-summary">Requested refund: <strong>₱{{ number_format($returnRequest->refund_amount ?? 0, 2) }}</strong></p>
                    @endif
                @elseif($returnRequest->status === 'rejected' && $returnRequest->dispute_status === 'open')
                    <div class="alert alert-warning" role="status">This return request was rejected. The buyer may escalate this decision to Platform Admin for final dispute resolution.</div>
                    <p>Seller decision controls are now read-only while Admin reviews the case.</p>
                @elseif($returnRequest->status === 'rejected')
                    <p>Admin upheld the rejection. This request is closed.</p>
                @else
                    <p>Refund completed on {{ $returnRequest->completed_at?->format('M d, Y h:i A') }}.</p>
                @endif
            </div>
        </section>

        <section class="card return-timeline-card">
            <div class="card-header"><span class="card-title">Return history</span></div>
            <div class="card-body">
                @if($returnRequest->events->isEmpty())
                    <ol class="return-audit-timeline">
                        <li class="is-complete"><span class="return-audit-dot"></span><div><strong>Request submitted</strong><time datetime="{{ $returnRequest->created_at->toIso8601String() }}">{{ $returnRequest->created_at->format('M d, Y h:i:s A') }}</time><p>{{ $returnRequest->details }}</p></div></li>
                    </ol>
                @else
                    <ol class="return-audit-timeline">
                        @foreach($returnRequest->events as $event)
                            @php
                                $eventLabels = [
                                    'request_submitted' => 'Buyer submitted request',
                                    'seller_approved' => 'Seller approved return',
                                    'seller_rejected' => 'Seller rejected request',
                                    'item_received' => 'Seller received returned item',
                                    'admin_approved' => 'Admin approved return',
                                    'admin_rejected' => 'Admin rejected return',
                                    'admin_inspected' => 'Admin inspected returned item',
                                    'admin_refund_approved' => 'Admin approved refund',
                                    'refund_due' => 'Refund marked due',
                                    'refund_completed' => 'Refund completed',
                                    'tracking_updated' => 'Return tracking updated',
                                    'admin_dispute_resolved' => 'Admin resolved dispute',
                                ];
                            @endphp
                            <li class="{{ $loop->last ? 'is-latest' : '' }}">
                                <span class="return-audit-dot"></span>
                                <div>
                                    <strong>{{ $eventLabels[$event->event_type] ?? ucfirst(str_replace('_', ' ', $event->event_type)) }}</strong>
                                    <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('M d, Y h:i:s A') }}</time>
                                    @if($event->actor)<span class="return-audit-actor">by {{ $event->actor->full_name }}</span>@endif
                                    @if($event->notes)<p>{{ $event->notes }}</p>@endif
                                    @if(isset($event->metadata['refund_amount']))<p>Refund amount: ₱{{ number_format($event->metadata['refund_amount'], 2) }}</p>@endif
                                    @if(isset($event->metadata['tracking_status']))<p>Tracking: {{ ucfirst(str_replace('_', ' ', $event->metadata['tracking_status'])) }}</p>@endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </section>
    </div>
</div>

@vite('resources/js/components/return-attachment-lightbox.js')
@endsection