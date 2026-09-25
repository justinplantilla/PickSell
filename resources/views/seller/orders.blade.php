@extends('seller.layout')
@section('styles')
@vite('resources/css/views/seller-orders.css')
@endsection
@section('title', 'Orders')

@section('content')
<div class="card">
    <div class="card-header">
        <span class="card-title">Order Management</span>
        <div class="filters">
            <form method="GET">
                <select name="status" class="filter-select" data-submit-on-change>
                    <option value="all" {{ $status==='all'?'selected':'' }}>All Orders</option>
                    <option value="pending" {{ $status==='pending'?'selected':'' }}>Pending</option>
                    <option value="processing" {{ $status==='processing'?'selected':'' }}>Processing</option>
                    <option value="shipped" {{ $status==='shipped'?'selected':'' }}>Shipped</option>
                    <option value="completed" {{ $status==='completed'?'selected':'' }}>Completed</option>
                    <option value="cancelled" {{ $status==='cancelled'?'selected':'' }}>Cancelled</option>
                </select>
            </form>
        </div>
    </div>
    <div class="blade-inline-1">
        <table>
            <thead>
                <tr><th>Order #</th><th>Buyer</th><th>Product</th><th>Qty</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
            @forelse($orders as $order)
            <tr>
                <td><a href="/seller/orders/{{ $order->id }}" class="blade-inline-2">{{ $order->order_number }}</a></td>
                <td>{{ $order->buyer->full_name ?? '—' }}</td>
                <td>{{ $order->product_name }}</td>
                <td>{{ $order->quantity }}</td>
                <td>₱{{ number_format($order->amount, 2) }}</td>
                <td><span class="badge badge-{{ $order->status }}">{{ $order->status }}</span></td>
                <td class="blade-inline-3">{{ $order->created_at->format('M d, Y') }}</td>
                <td>
                    <div class="blade-inline-4">
                        <a href="/seller/orders/{{ $order->id }}" class="btn btn-outline btn-sm">View</a>
                        @if(in_array($order->status, ['shipped', 'completed'], true))
                        <a href="/seller/orders/{{ $order->id }}/waybill" target="_blank" class="btn btn-outline btn-sm">Waybill</a>
                        @endif
                        @if($order->status === 'pending')
                        <form method="POST" action="/seller/orders/{{ $order->id }}/pack">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-coral btn-sm">Pack</button>
                        </form>
                        @elseif($order->status === 'processing')
                        <button class="btn btn-success btn-sm" onclick="openHandover({{ $order->id }})">Hand Over</button>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="blade-inline-5">No orders found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
    <div class="dashboard-pagination">{{ $orders->withQueryString()->links() }}</div>
    @endif
</div>

<!-- Handover Modal -->
<div class="modal-overlay" id="handoverModal">
    <div class="modal">
        <div class="modal-title">Hand Over to Courier</div>
        <form method="POST" id="handoverForm">
            @csrf @method('PATCH')
            <div class="form-group">
                <label class="form-label">Waybill / Tracking Number *</label>
                <input type="text" name="waybill_number" class="form-control" required placeholder="Enter waybill number">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('handoverModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-coral">Confirm Handover</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
@vite('resources/js/views/seller-orders.js')
@endsection
