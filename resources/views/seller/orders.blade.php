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
                    <option value="all" @selected($status === 'all')>All Orders</option>
                    @foreach($filters as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>
    {{-- Bulk actions: the checkboxes below belong to this form. --}}
    <form method="POST" action="{{ route('seller.orders.bulk') }}" id="bulkOrdersForm" class="seller-orders-bulk" data-no-confirm>
        @csrf
        <span class="seller-orders-muted-text"><span data-bulk-count>0</span> selected</span>
        <button type="submit" name="action" value="prepare" class="btn btn-outline btn-sm" data-bulk-action>Start preparing</button>
        <button type="submit" name="action" value="waybills" class="btn btn-outline btn-sm" data-bulk-action>Generate waybills</button>
        <button type="submit" name="action" value="print" class="btn btn-outline btn-sm" data-bulk-action formtarget="_blank">Print waybills</button>
        @error('order_ids')<span class="seller-orders-bulk-error" role="alert">{{ $message }}</span>@enderror
    </form>
    <div class="seller-orders-scroll">
        <table>
            <thead>
                <tr><th><input type="checkbox" data-bulk-all aria-label="Select all orders on this page"></th><th>Order #</th><th>Buyer</th><th>Product</th><th>Qty</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
            @forelse($orders as $order)
            <tr>
                <td><input type="checkbox" name="order_ids[]" value="{{ $order->id }}" form="bulkOrdersForm" data-bulk-order aria-label="Select order {{ $order->order_number }}"></td>
                <td><a href="/seller/orders/{{ $order->id }}" class="seller-orders-accent-text">{{ $order->order_number }}</a></td>
                <td>{{ $order->buyer->full_name ?? '—' }}</td>
                <td>{{ $order->product_name }}@if($order->variation_label)<div class="seller-orders-muted-text">{{ $order->variation_label }}</div>@endif</td>
                <td>{{ $order->quantity }}</td>
                <td>₱{{ number_format($order->amount, 2) }}</td>
                <td><x-order-status-badge :status="$order->status" /></td>
                <td class="seller-orders-muted-text">{{ $order->created_at->format('M d, Y') }}</td>
                <td>
                    <div class="seller-orders-row">
                        <a href="/seller/orders/{{ $order->id }}" class="btn btn-outline btn-sm">View</a>
                        @if($order->waybill_number)
                        <a href="/seller/orders/{{ $order->id }}/waybill" target="_blank" class="btn btn-outline btn-sm">Waybill</a>
                        @endif
                        @if(in_array($order->status, ['placed', 'confirmed', 'pending'], true))
                        <form method="POST" action="/seller/orders/{{ $order->id }}/pack">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-coral btn-sm">Confirm Order</button>
                        </form>
                        @elseif(in_array($order->status, ['preparing', 'processing'], true) && ! $order->waybill_number)
                        <form method="POST" action="{{ route('seller.orders.waybill.generate', $order) }}">
                            @csrf
                            <button type="submit" class="btn btn-coral btn-sm">Generate Waybill</button>
                        </form>
                        @elseif(in_array($order->status, ['preparing', 'processing'], true))
                        <button class="btn btn-success btn-sm" type="button" data-open-handover="{{ $order->id }}">Hand Over</button>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="9" class="seller-orders-empty">No orders found.</td></tr>
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
        <div class="modal-title">Hand Over to Pickrush</div>
        <form method="POST" id="handoverForm" data-no-confirm>
            @csrf @method('PATCH')
            <div class="waybill-generation-note">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                <span>Is the parcel packed with its waybill attached? Pickrush will send a rider to pick it up, and the buyer and admin are notified.</span>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-handover>Cancel</button>
                <button type="submit" class="btn btn-coral">Hand Over to Pickrush</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
@vite('resources/js/views/seller-orders.js')
@endsection
