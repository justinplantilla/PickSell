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
                        @if($order->waybill_number)
                        <a href="/seller/orders/{{ $order->id }}/waybill" target="_blank" class="btn btn-outline btn-sm">Waybill</a>
                        @endif
                        @if(in_array($order->status, ['placed', 'confirmed', 'pending'], true))
                        <form method="POST" action="/seller/orders/{{ $order->id }}/pack">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-coral btn-sm">Prepare</button>
                        </form>
                        @elseif(in_array($order->status, ['preparing', 'processing'], true))
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
        <div class="modal-title">Hand Over to Logistics</div>
        <form method="POST" id="handoverForm">
            @csrf @method('PATCH')
            <div class="waybill-generation-note">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                <span>PickSell will generate the waybill automatically and notify the buyer, logistics, and admin.</span>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('handoverModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-coral">Generate Waybill &amp; Confirm</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
@vite('resources/js/views/seller-orders.js')
@endsection
