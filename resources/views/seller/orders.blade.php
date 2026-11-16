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
    {{-- Bulk actions: the checkboxes below belong to this form. Each button shows only while a ticked order is at that step. --}}
    <form method="POST" action="{{ route('seller.orders.bulk') }}" id="bulkOrdersForm" class="seller-orders-bulk" data-no-confirm>
        @csrf
        <span class="seller-orders-muted-text"><span data-bulk-count>0</span> selected</span>
        <button type="submit" name="action" value="confirm" class="btn btn-outline btn-sm" data-bulk-action="confirm" hidden>Confirm orders</button>
        <button type="submit" name="action" value="waybills" class="btn btn-outline btn-sm" data-bulk-action="waybills" hidden>Generate waybills</button>
        <button type="submit" name="action" value="print" class="btn btn-outline btn-sm" data-bulk-action="print" formtarget="_blank" hidden>Print waybills</button>
        <button type="button" class="btn btn-success btn-sm" data-bulk-action="handover" data-open-bulk-handover hidden>Hand over</button>
        @error('order_ids')<span class="seller-orders-bulk-error" role="alert">{{ $message }}</span>@enderror
    </form>
    <div class="seller-orders-scroll">
        <table class="table-cards">
            <thead>
                <tr><th><input type="checkbox" data-bulk-all aria-label="Select all orders on this page"></th><th>Order #</th><th>Buyer</th><th>Product</th><th>Qty</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
            @forelse($orders as $order)
            @php
                $isPreparing = in_array($order->status, ['preparing', 'processing'], true);
                $bulkSteps = implode(' ', array_keys(array_filter([
                    'confirm' => in_array($order->status, ['placed', 'confirmed', 'pending'], true),
                    'waybills' => $isPreparing && ! $order->waybill_number,
                    'print' => (bool) $order->waybill_number,
                    'handover' => $isPreparing && $order->waybill_number,
                ])));
            @endphp
            <tr>
                <td class="cell-check"><input type="checkbox" name="order_ids[]" value="{{ $order->id }}" form="bulkOrdersForm" data-bulk-order="{{ $bulkSteps }}" aria-label="Select order {{ $order->order_number }}"></td>
                <td class="cell-main"><a href="/seller/orders/{{ $order->id }}" class="seller-orders-accent-text">{{ $order->order_number }}</a></td>
                <td data-label="Buyer">{{ $order->buyer->full_name ?? '—' }}</td>
                <td data-label="Product">@if($order->items->isNotEmpty())@include('partials.order-items', ['order' => $order])@else{{ $order->product_name }}@if($order->variation_label)<div class="seller-orders-muted-text">{{ $order->variation_label }}</div>@endif @endif</td>
                <td data-label="Qty">{{ $order->quantity }}</td>
                <td data-label="Amount">₱{{ number_format($order->amount, 2) }}</td>
                <td data-label="Status"><x-order-status-badge :status="$order->status" />@if($order->status === 'ready_for_pickup' && $order->fulfillment === 'dropoff')<div class="seller-orders-muted-text">Drop off by {{ $order->dropoff_by?->format('M d, h:i A') }}</div>@endif</td>
                <td class="seller-orders-muted-text" data-label="Date">{{ $order->created_at->format('M d, Y') }}</td>
                <td class="cell-actions">
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
                        @elseif($isPreparing && ! $order->waybill_number)
                        <form method="POST" action="{{ route('seller.orders.waybill.generate', $order) }}">
                            @csrf
                            <button type="submit" class="btn btn-coral btn-sm">Generate Waybill</button>
                        </form>
                        @elseif($isPreparing)
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

<!-- Bulk Handover Modal -->
<div class="modal-overlay" id="bulkHandoverModal">
    <div class="modal">
        <div class="modal-title">Hand Over the Selected Parcels</div>
        <div class="waybill-generation-note">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
            <span>Are the <strong data-bulk-handover-count>0</strong> selected orders packed with their waybills attached? Choose <strong>Rider Pickup</strong> and their logistics companies send riders, or <strong>Drop Off</strong> and you bring them to your station within {{ \App\Models\Order::DROPOFF_WITHIN_DAYS }} days (no rider is sent; parcels not dropped off in time are cancelled). Orders without a waybill are skipped.</span>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-close-handover>Cancel</button>
            <button type="submit" form="bulkOrdersForm" name="action" value="dropoff" class="btn btn-outline">Drop Off</button>
            <button type="submit" form="bulkOrdersForm" name="action" value="handover" class="btn btn-coral">Rider Pickup</button>
        </div>
    </div>
</div>

<!-- Handover Modal -->
<div class="modal-overlay" id="handoverModal">
    <div class="modal">
        <div class="modal-title">Hand Over to the Courier</div>
        <form method="POST" id="handoverForm" data-no-confirm>
            @csrf @method('PATCH')
            <div class="waybill-generation-note">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                <span>Is the parcel packed with its waybill attached? Choose <strong>Rider Pickup</strong> and the logistics company sends a rider, or <strong>Drop Off</strong> and you bring it to your station within {{ \App\Models\Order::DROPOFF_WITHIN_DAYS }} days (no rider is sent; if it is not dropped off in time, the order is cancelled).</span>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-handover>Cancel</button>
                <button type="submit" name="method" value="dropoff" class="btn btn-outline">Drop Off</button>
                <button type="submit" name="method" value="pickup" class="btn btn-coral">Rider Pickup</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
@vite('resources/js/views/seller-orders.js')
@endsection
