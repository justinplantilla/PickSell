@extends('buyer.layout')
@section('styles')
@vite('resources/css/views/buyer-cart.css')
@endsection
@section('title', 'Checkout')

@section('content')
<h2 class="blade-inline-1">Checkout</h2>

<div class="blade-inline-6">
    <div>
        <div class="card">
            <div class="card-header"><span class="card-title">Order Items</span></div>
            <div class="blade-inline-8">
                <table>
                    <thead><tr><th>Product</th><th>Variation</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
                    <tbody>
                    @foreach($items as $item)
                    <tr>
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
                        <td>{{ $item->quantity }}</td>
                        <td class="blade-inline-18">₱{{ number_format($item->product->effective_price * $item->quantity, 2) }}</td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><span class="card-title">Payment & Delivery</span></div>
            <div class="card-body">
                <form method="POST" action="/buyer/cart/checkout">
                    @csrf
                    @foreach($itemIds as $id)
                        <input type="hidden" name="item_ids[]" value="{{ $id }}">
                    @endforeach
                    <div class="form-group">
                        <label class="form-label">Voucher Code</label>
                        <input type="text" name="voucher_code" class="form-control" placeholder="Enter voucher code" value="{{ old('voucher_code') }}">
                    </div>
                    <div class="checkout-defaults">
                        <div><strong>Payment</strong><span>Cash on Delivery</span></div>
                        <div><strong>Delivery</strong><span>{{ $logisticsProvider?->business_name ?: $logisticsProvider?->full_name ?: 'PickSell Logistics' }}</span></div>
                    </div>
                    @if(!$logisticsProvider)
                        <small class="blade-inline-20">Checkout is temporarily unavailable while logistics is being configured.</small>
                    @endif
                    <hr class="blade-inline-21">
                    <div class="blade-inline-24">
                        <span>Total</span>
                        <span class="blade-inline-25">₱{{ number_format($total, 2) }}</span>
                    </div>
                    <button type="submit" class="btn btn-coral blade-inline-26" {{ !$logisticsProvider ? 'disabled' : '' }}>
                        Place Order
                    </button>
                </form>
                <a href="/buyer/cart" class="btn btn-outline" style="width:100%;justify-content:center;margin-top:0.5rem;">← Back to Cart</a>
            </div>
        </div>
    </div>
</div>
@endsection
