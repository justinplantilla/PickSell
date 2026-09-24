@extends('seller.layout')
@vite('resources/css/views/seller-order-detail.css')
@section('title', 'Order #' . $order->order_number)

@section('content')
<div class="blade-inline-1">
    <a href="/seller/orders" class="btn btn-outline btn-sm">← Back to Orders</a>
</div>

<div class="grid-2">
    <div>
        <div class="card">
            <div class="card-header">
                <span class="card-title">Order Details</span>
                <span class="badge badge-{{ $order->status }}">{{ $order->status }}</span>
            </div>
            <div class="card-body">
                <table>
                    <tr><td class="blade-inline-2">Order #</td><td><strong>{{ $order->order_number }}</strong></td></tr>
                    <tr><td class="blade-inline-3">Product</td><td>{{ $order->product_name }}</td></tr>
                    <tr><td class="blade-inline-4">Quantity</td><td>{{ $order->quantity }}</td></tr>
                    <tr><td class="blade-inline-5">Amount</td><td><strong>₱{{ number_format($order->amount, 2) }}</strong></td></tr>
                    <tr><td class="blade-inline-6">Commission</td><td>₱{{ number_format($order->commission, 2) }}</td></tr>
                    <tr><td class="blade-inline-7">Net Profit</td><td><strong class="blade-inline-8">₱{{ number_format($order->amount - $order->commission, 2) }}</strong></td></tr>
                    <tr><td class="blade-inline-9">Order Date</td><td>{{ $order->created_at->format('M d, Y h:i A') }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Buyer Information</span></div>
            <div class="card-body">
                @if($order->buyer)
                <table>
                    <tr><td class="blade-inline-10">Name</td><td>{{ $order->buyer->full_name }}</td></tr>
                    <tr><td class="blade-inline-11">Email</td><td>{{ $order->buyer->email }}</td></tr>
                    <tr><td class="blade-inline-12">Contact</td><td>{{ $order->buyer->contact_no }}</td></tr>
                    <tr><td class="blade-inline-13">Address</td><td>{{ implode(', ', array_filter([$order->buyer->house_no, $order->buyer->street, $order->buyer->barangay, $order->buyer->municipality, $order->buyer->province])) }}</td></tr>
                </table>
                @else
                <p class="blade-inline-14">Buyer info unavailable.</p>
                @endif
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><span class="card-title">Shipment & Tracking</span></div>
            <div class="card-body">
                <table>
                    <tr><td class="blade-inline-15">Waybill #</td><td>{{ $order->waybill_number ?? '—' }}</td></tr>
                    <tr><td class="blade-inline-16">Tracking Status</td><td>{{ $order->tracking_status ?? '—' }}</td></tr>
                    <tr><td class="blade-inline-17">Packed At</td><td>{{ $order->packed_at ? $order->packed_at->format('M d, Y h:i A') : '—' }}</td></tr>
                    <tr><td class="blade-inline-18">Handed Over</td><td>{{ $order->handed_over_at ? $order->handed_over_at->format('M d, Y h:i A') : '—' }}</td></tr>
                    <tr><td class="blade-inline-19">Delivered At</td><td>{{ $order->delivered_at ? $order->delivered_at->format('M d, Y h:i A') : '—' }}</td></tr>
                </table>

                <div class="blade-inline-20">
                    @if($order->status === 'pending')
                    <form method="POST" action="/seller/orders/{{ $order->id }}/pack">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-coral">Mark as Packed</button>
                    </form>
                    @elseif($order->status === 'processing')
                    <form method="POST" action="/seller/orders/{{ $order->id }}/handover" class="blade-inline-21">
                        @csrf @method('PATCH')
                        <div class="form-group blade-inline-22">
                            <label class="form-label">Waybill Number *</label>
                            <input type="text" name="waybill_number" class="form-control" required placeholder="Enter waybill number">
                        </div>
                        <button type="submit" class="btn btn-success">Hand Over</button>
                    </form>
                    @elseif($order->status === 'shipped')
                    <div class="alert alert-success blade-inline-23">Order has been handed over to courier. Awaiting delivery confirmation.</div>
                    @elseif($order->status === 'completed')
                    <div class="alert alert-success blade-inline-24">Order delivered and completed.</div>
                    @if(!$order->confirmed_by_seller_at)
                    <form method="POST" action="/seller/orders/{{ $order->id }}/confirm-delivery" class="blade-inline-21">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-success">Confirm Delivery</button>
                    </form>
                    @else
                    <div class="alert alert-info blade-inline-23">Delivery confirmed by seller on {{ $order->confirmed_by_seller_at->format('M d, Y h:i A') }}.</div>
                    @endif
                    @endif
                </div>
            </div>
        </div>

        @if(in_array($order->status, ['shipped','completed'], true))
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
                <div class="blade-inline-25">Customer rating: {{ $order->rating }}/5</div>
                @endif
                @if($order->feedback)
                <p class="blade-inline-26">{{ $order->feedback }}</p>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
