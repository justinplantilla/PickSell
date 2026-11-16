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
                    $trackingStates = ['label_generated' => 'Pickup booked', 'in_transit' => 'Picked up from buyer', 'delivered_to_seller' => 'Delivered to you'];
                    $trackingOrder = array_keys($trackingStates);
                    $trackingIndex = array_search($returnRequest->tracking_status, $trackingOrder, true);
                    $trackingProblem = ['held_for_seller' => 'Held at the station: you did not take it', 'abandoned' => 'Abandoned at the station', 'lost' => 'Reported lost', 'cancelled' => 'Pickup cancelled'][$returnRequest->tracking_status] ?? null;
                @endphp
                <ol class="return-logistics-track">
                    @foreach($trackingOrder as $trackingState)
                        <li class="{{ $trackingIndex !== false && $loop->index <= $trackingIndex ? 'is-complete' : '' }} {{ $returnRequest->tracking_status === $trackingState ? 'is-current' : '' }}">
                            <span class="return-track-dot" aria-hidden="true"></span>
                            <span>{{ $trackingStates[$trackingState] }}</span>
                        </li>
                    @endforeach
                </ol>
                @if($trackingProblem)
                    <div class="alert alert-warning" role="status">{{ $trackingProblem }}.</div>
                @endif
                <dl class="return-detail-list return-tracking-meta">
                    <div><dt>Carrier</dt><dd>{{ $returnRequest->carrier ?? 'Not booked yet' }}</dd></div>
                    <div><dt>Tracking number</dt><dd>{{ $returnRequest->tracking_number ?? 'Not booked yet' }}</dd></div>
                    <div><dt>Return fee you pay the rider</dt><dd>{{ $returnRequest->return_fee !== null ? '₱'.number_format((float) $returnRequest->return_fee, 2) : '—' }}</dd></div>
                    <div><dt>Last updated</dt><dd>{{ $returnRequest->tracking_updated_at?->format('M d, Y h:i A') ?? 'No tracking update' }}</dd></div>
                </dl>
                <p class="return-help">Once you approve, Pickrush picks the item up from the buyer and brings it to you. Pay the return fee to the rider on hand-over. A return you do not take is held at the station for {{ config('pickrush.policies.return_hold_days') }} days, reported to PickSell (a strike), and after {{ config('pickrush.policies.return_abandon_days') }} days kept by the logistics company.</p>
            </div>
        </section>

        <section class="card return-decision-card">
            <div class="card-header"><span class="card-title">Seller decision</span></div>
            <div class="card-body return-workflow-body">
                @if($returnRequest->status === 'requested')
                    <p>Review the evidence and respond to the buyer. This decision is recorded in the audit timeline.</p>
                    <p class="return-refund-summary">Refund if the return goes through: <strong>₱{{ number_format((float) $returnRequest->refund_amount, 2) }}</strong> (item price × {{ $returnRequest->quantity }}), taken from this order's held payment.</p>
                    <form method="POST" action="{{ route('seller.returns.approve', $returnRequest) }}" class="return-transition-form">
                        @csrf @method('PATCH')
                        <label class="form-label" for="seller-approve-response">Seller response to buyer</label>
                        <textarea class="form-control" id="seller-approve-response" name="seller_note" rows="3" maxlength="2000" required>{{ old('seller_note') }}</textarea>
                        <p class="return-help">Approving books the Pickrush pickup from the buyer.</p>
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
                    @if($returnRequest->tracking_number)
                        <p>Pickrush is bringing the item back to you. It is marked received when the rider hands it over.</p>
                    @else
                        <p>PickSell is booking the Pickrush pickup. If the item reaches you another way, confirm it here.</p>
                        <form method="POST" action="{{ route('seller.returns.receive', $returnRequest) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-coral">Confirm item received</button>
                        </form>
                    @endif
                @elseif($returnRequest->status === 'received' && $returnRequest->dispute_status === 'open')
                    <div class="alert alert-warning" role="status">You reported a problem with the returned item. PickSell is reviewing it.</div>
                @elseif($returnRequest->status === 'received')
                    <p>Check the returned item. If it is what the buyer described, PickSell sends the buyer's refund through GCash out of this order's held payment.</p>
                    <form method="POST" action="{{ route('seller.returns.inspect', $returnRequest) }}" class="return-transition-form">
                        @csrf @method('PATCH')
                        <label class="form-label" for="inspection-note">What you found</label>
                        <textarea class="form-control" id="inspection-note" name="inspection_note" rows="3" maxlength="2000" required>{{ old('inspection_note') }}</textarea>
                        <div class="return-inspection-actions">
                            <button type="submit" name="result" value="passed" class="btn btn-success">Item OK, refund the buyer</button>
                            <button type="submit" name="result" value="failed" class="btn btn-danger">Report a problem</button>
                        </div>
                        <p class="return-help">Reporting a problem (wrong, used or damaged item) sends the return to PickSell to decide.</p>
                    </form>
                @elseif(in_array($returnRequest->status, ['inspected', 'approved_for_refund', 'refund_due'], true))
                    <p>PickSell Finance is processing the buyer's refund. There is nothing for you to pay: it comes out of this order's held payment.</p>
                    <p class="return-refund-summary">Refund: <strong>₱{{ number_format((float) $returnRequest->refund_amount, 2) }}</strong></p>
                @elseif($returnRequest->status === 'rejected' && $returnRequest->dispute_status === 'open')
                    <div class="alert alert-warning" role="status">You rejected this return request. PickSell is reviewing it and will decide.</div>
                    <p>Seller decision controls are now read-only while Admin reviews the case.</p>
                @elseif($returnRequest->status === 'rejected')
                    <p>{{ $returnRequest->admin_decision === 'side_with_seller' ? 'PickSell sided with you after the inspection. Hand the item to the Pickrush rider to send it back to the buyer.' : 'The rejection stands. This request is closed and your payment for the order is released.' }}</p>
                @else
                    <p>Refunded on {{ $returnRequest->completed_at?->format('M d, Y h:i A') }}.</p>
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