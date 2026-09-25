@extends('buyer.layout')
@section('styles')
@vite('resources/css/views/buyer-cart.css')
@endsection
@section('title', 'My Cart')

@section('content')
<h2 class="blade-inline-1">My Cart ({{ $items->count() }} item{{ $items->count() !== 1 ? 's' : '' }})</h2>

@if($items->isEmpty())
<div class="blade-inline-2">
    <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" fill="currentColor" viewBox="0 0 24 24" class="blade-inline-3"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM5.2 5H2V3H0v2h2l3.6 7.59L4.25 15A2 2 0 0 0 6 18h14v-2H6.42a.25.25 0 0 1-.25-.25l.03-.12L7.1 14h9.45c.75 0 1.41-.41 1.75-1.03L21.7 6.5A1 1 0 0 0 20.83 5H5.2z"/></svg>
    <div class="blade-inline-4">Your cart is empty</div>
    <a href="/buyer/shop" class="btn btn-coral blade-inline-5">Start Shopping</a>
</div>
@else
<form method="POST" action="/buyer/cart/checkout" id="checkoutForm">
    @csrf
    <div class="blade-inline-6">
        <div>
            <div class="card">
                <div class="card-header">
                    <label class="blade-inline-7">
                        <input type="checkbox" id="selectAll" data-cart-select-all> Select All
                    </label>
                </div>
                <div class="blade-inline-8">
                    <table>
                        <thead><tr><th></th><th>Product</th><th>Variation</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>
                        <tbody>
                        @foreach($items as $item)
                        <tr>
                            <td><input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="item-check"></td>
                            <td>
                                <div class="blade-inline-9">
                                    @if($item->product->image)
                                        <img src="{{ Storage::url($item->product->image) }}" class="blade-inline-10">
                                    @else
                                        <div class="blade-inline-11"></div>
                                    @endif
                                    <div>
                                        <div class="blade-inline-12">{{ $item->product->name }}</div>
                                        <div class="blade-inline-13">{{ $item->product->seller->business_name ?? $item->product->seller->full_name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="blade-inline-14">{{ $item->variation ? $item->variation->type.': '.$item->variation->value : '—' }}</td>
                            <td class="blade-inline-15">₱{{ number_format($item->product->effective_price, 2) }}</td>
                            <td>
                                <div class="blade-inline-16">
                                    <input type="number"
                                           name="quantity"
                                           value="{{ $item->quantity }}"
                                           min="1"
                                           max="{{ $item->product->stock }}"
                                           data-item-id="{{ $item->id }}"
                                           class="qty-input blade-inline-17">
                                </div>
                            </td>
                            <td class="blade-inline-18" data-subtotal="{{ $item->product->effective_price * $item->quantity }}">₱{{ number_format($item->product->effective_price * $item->quantity, 2) }}</td>
                            <td>
                                <button type="button" class="btn btn-sm remove-item-btn" data-item-id="{{ $item->id }}" class="blade-inline-19" title="Remove">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-header"><span class="card-title">Order Summary</span></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Voucher Code</label>
                        <input type="text" name="voucher_code" class="form-control" placeholder="Enter voucher code">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Method *</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="cod">Cash on Delivery</option>
                            <option value="gcash">GCash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">PickSell Logistics *</label>
                        <select name="logistics_id" class="form-control" required>
                            <option value="">Select logistics provider</option>
                            @foreach($logisticsProviders as $provider)
                                <option value="{{ $provider->id }}">{{ $provider->business_name ?: $provider->full_name }}</option>
                            @endforeach
                        </select>
                        @if($logisticsProviders->isEmpty())
                            <small class="blade-inline-20">No approved logistics providers are available yet.</small>
                        @endif
                    </div>
                    <hr class="blade-inline-21">
                    <div class="blade-inline-22">
                        <span class="blade-inline-23">Selected Items</span>
                        <span id="selectedCount">0</span>
                    </div>
                    <div class="blade-inline-24">
                        <span>Total</span>
                        <span class="blade-inline-25" id="totalAmount">₱0.00</span>
                    </div>
                    <button type="submit" class="btn btn-coral blade-inline-26" id="placeOrderBtn" disabled>
                        Place Order
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
@endif
@endsection

@section('scripts')
@vite('resources/js/views/buyer-cart.js')
@endsection
