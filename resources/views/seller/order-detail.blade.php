@extends('seller.layout')
@section('styles')
@vite('resources/css/views/seller-order-detail.css')
@endsection
@section('title', 'Order #' . $order->order_number)

@section('content')
<div class="order-detail-spaced">
    <a href="/seller/orders" class="btn btn-outline btn-sm">← Back to Orders</a>
</div>

<div class="grid-2">
    <div>
        <div class="card">
            <div class="card-header">
                <span class="card-title">Order Details</span>
                <x-order-status-badge :status="$order->status" />
            </div>
            <div class="card-body">
                <table>
                    <tr><td class="order-detail-muted-label">Order #</td><td><strong>{{ $order->order_number }}</strong></td></tr>
                    <tr><td class="order-detail-muted">Product</td><td>{{ $order->product_name }}</td></tr>
                    @if($order->variation_label)<tr><td class="order-detail-muted">Option</td><td><strong>{{ $order->variation_label }}</strong></td></tr>@endif
                    <tr><td class="order-detail-muted">Quantity</td><td>{{ $order->quantity }}</td></tr>
                    <tr><td class="order-detail-muted">Amount</td><td><strong>₱{{ number_format($order->amount, 2) }}</strong></td></tr>
                    <tr><td class="order-detail-muted">Commission ({{ number_format($commissionRate, 2) }}%)</td><td>-₱{{ number_format($commission, 2) }}</td></tr>
                    <tr><td class="order-detail-muted">Net Earnings</td><td><strong class="order-detail-positive">₱{{ number_format($netEarnings, 2) }}</strong></td></tr>
                    <tr><td class="order-detail-muted">Order Date</td><td>{{ $order->created_at->format('M d, Y h:i A') }}</td></tr>
                    @if($order->shipment)
                        @php($parcelItems = $order->shipment->activeOrders()->pluck('order_number'))
                        <tr><td class="order-detail-muted">Parcel</td><td>{{ ucfirst(strtolower($order->shipment->parcel_class)) }} · {{ $order->shipment->chargeable_weight_kg }} kg · {{ $parcelItems->count() > 1 ? 'shared with '.$parcelItems->reject(fn ($n) => $n === $order->order_number)->join(', ') : 'this order only' }}</td></tr>
                        <tr><td class="order-detail-muted">Delivery fee</td><td>₱{{ number_format((float) $order->shipment->delivery_fee, 2) }} <small class="order-detail-muted">paid by the buyer; not part of your earnings</small></td></tr>
                        @if($order->shipment->status === 'failed')
                            <tr><td class="order-detail-muted">Delivery</td><td><strong>Attempt failed</strong>{{ $order->shipment->failure_reason ? ': '.$order->shipment->failure_reason : '' }}. Earnings stay unpaid until it is delivered.</td></tr>
                        @endif
                    @endif
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Buyer Information</span></div>
            <div class="card-body">
                @if($order->buyer)
                <table>
                    <tr><td class="order-detail-muted-label">Name</td><td>{{ $order->buyer->full_name }}</td></tr>
                    <tr><td class="order-detail-muted">Email</td><td>{{ $order->buyer->email }}</td></tr>
                    <tr><td class="order-detail-muted">Contact</td><td>{{ $order->buyer->contact_no }}</td></tr>
                    @php($deliverTo = $order->deliverTo())
                    @if($deliverTo['name'] !== $order->buyer->full_name || $deliverTo['phone'] !== $order->buyer->contact_no)
                    <tr><td class="order-detail-muted">Ship to</td><td>{{ $deliverTo['name'] }} · {{ $deliverTo['phone'] }}</td></tr>
                    @endif
                    <tr><td class="order-detail-muted">Address</td><td>{{ $deliverTo['address'] }}</td></tr>
                    @if($order->shipment?->buyer_note)
                    <tr><td class="order-detail-muted">Buyer's note</td><td><strong>{{ $order->shipment->buyer_note }}</strong></td></tr>
                    @endif
                </table>
                @else
                <p class="order-detail-muted-2">Buyer info unavailable.</p>
                @endif
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><span class="card-title">Shipment & Tracking</span></div>
            <div class="card-body">
                <table>
                    <tr><td class="order-detail-muted-label">Waybill #</td><td>{{ $order->waybill_number ?? '—' }}</td></tr>
                    <tr><td class="order-detail-muted">Tracking Status</td><td>{{ $order->tracking_status ?? '—' }}</td></tr>
                    <tr><td class="order-detail-muted">Packed At</td><td>{{ $order->packed_at ? $order->packed_at->format('M d, Y h:i A') : '—' }}</td></tr>
                    <tr><td class="order-detail-muted">Handed Over</td><td>{{ $order->handed_over_at ? $order->handed_over_at->format('M d, Y h:i A') : '—' }}</td></tr>
                    <tr><td class="order-detail-muted">Delivered At</td><td>{{ $order->delivered_at ? $order->delivered_at->format('M d, Y h:i A') : '—' }}</td></tr>
                </table>
                @include('partials.pickrush-tracking', ['tracking' => \App\Pickrush\Bridge\PicksellBridge::tracking($order)])

                @php($canCancel = in_array($order->status, ['placed', 'confirmed', 'preparing'], true))
                @php($canConfirm = in_array($order->status, ['placed', 'confirmed', 'pending'], true))
                @php($isPreparing = in_array($order->status, ['preparing', 'processing'], true))
                <div class="order-detail-spaced-2">
                    @if($isPreparing && $order->ship_by)
                        <div class="alert {{ $order->ship_by->lt(now()->addHours(\App\Services\Orders\ShippingDeadlines::REMINDER_HOURS)) ? 'alert-warning' : 'alert-info' }} order-detail-flush" role="status">
                            <strong>Hand over by {{ $order->ship_by->format('M d, Y h:i A') }}</strong> ({{ $order->ship_by->diffForHumans() }}).
                            If it is not handed to {{ $order->carrierName() }} by then, the order is cancelled automatically and counts against your seller performance.
                        </div>
                    @elseif($canConfirm)
                        <p class="order-detail-muted-label">Once you confirm, you have {{ $order->product?->preorder_days ? $order->product->preorder_days.' days (pre-order)' : \App\Models\Order::SHIP_WITHIN_HOURS.' hours' }} to hand the parcel to the courier.</p>
                    @endif
                    @if($canCancel || $canConfirm || $isPreparing)
                    <div class="order-detail-actions">
                        @if($canCancel)
                        <form method="POST" action="{{ route('seller.orders.cancel', $order) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-outline" data-confirm="Cancel this order before pickup?">Cancel Order</button>
                        </form>
                        @endif
                        <div class="order-detail-actions-main">
                            @if($canConfirm)
                            <form method="POST" action="/seller/orders/{{ $order->id }}/pack">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-coral">Confirm Order</button>
                            </form>
                            @elseif($isPreparing && ! $order->waybill_number)
                            <form method="POST" action="{{ route('seller.orders.waybill.generate', $order) }}">
                                @csrf
                                <button type="submit" class="btn btn-coral">Generate Waybill</button>
                            </form>
                            @elseif($isPreparing)
                            <a href="{{ route('seller.orders.waybill', $order) }}" target="_blank" class="btn btn-outline">Print Waybill</a>
                            <form method="POST" action="{{ route('seller.orders.handover', $order) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-success" data-confirm="Is the parcel packed with the waybill attached? {{ $order->carrierName() }} will send a rider to pick it up.">Hand Over to {{ $order->carrierName() }}</button>
                            </form>
                            @endif
                        </div>
                    </div>
                    @endif
                    @if($isPreparing)
                    <div class="waybill-generation-note order-detail-note">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                        @if(! $order->waybill_number)
                        <span>{{ $order->carrierName() }}'s tracking number becomes your waybill. No rider is called yet, so take your time packing.</span>
                        @else
                        <span>Waybill <strong>{{ $order->waybill_number }}</strong> is ready. Print it, attach it to the packed parcel, then hand over to request a {{ $order->carrierName() }} pickup.</span>
                        @endif
                    </div>
                    @endif
                    @if(in_array($order->status, ['shipped', 'ready_for_pickup'], true))
                    <div class="alert alert-info order-detail-flush">Handed over: <strong>{{ $order->waybill_number }}</strong>. Waiting for {{ $order->carrierName() }} to pick it up.</div>
                    @elseif($order->status === 'delivered')
                    <div class="alert alert-success order-detail-flush">{{ $order->carrierName() }} delivered this order. It completes and your earnings are released once the buyer confirms they received it.</div>
                    @elseif($order->status === 'completed')
                    <div class="alert alert-info order-detail-flush">The buyer confirmed they received this order.</div>
                    @endif
                    @include('partials.delivery-problem', ['order' => $order, 'role' => 'seller'])
                </div>
            </div>
        </div>

        @if($order->waybill_number)
        <div class="card">
            <div class="card-header"><span class="card-title">Shipping Documents</span></div>
            <div class="card-body">
                <a href="/seller/orders/{{ $order->id }}/waybill" target="_blank" class="btn btn-outline">Download Waybill Label</a>
            </div>
        </div>
        @endif

        @if($order->status === 'completed' && ($order->rating || $order->feedback))
        <div class="card">
            <div class="card-header"><span class="card-title">Customer Feedback</span></div>
            <div class="card-body">
                @if($order->rating)
                <div class="order-detail-muted-text">Customer rating: {{ $order->rating }}/5</div>
                @endif
                @if($order->feedback)
                <p class="order-detail-muted-text-2">{{ $order->feedback }}</p>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
