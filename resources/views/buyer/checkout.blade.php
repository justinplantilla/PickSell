@extends('buyer.layout')
@section('styles')
@vite('resources/css/views/buyer-cart.css')
@endsection
@section('title', 'Checkout')

@section('content')
<h2 class="blade-inline-1">Checkout</h2>

<div class="checkout-layout">
    {{-- LEFT: Order Items --}}
    <div class="checkout-left">
        <div class="card">
            <div class="card-header"><span class="card-title">Order Items</span></div>
            <div class="blade-inline-8">
                <table class="checkout-table">
                    <thead><tr><th>Product</th><th>Variation</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
                    <tbody>
                    @foreach($items as $item)
                    <tr>
                        <td>
                            <div class="blade-inline-9">
                                @if($item->product->primary_image)
                                    <img src="{{ Storage::url($item->product->primary_image) }}" class="blade-inline-10">
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

        {{-- Voucher Picker --}}
        @php
            $voucherProducts = $items->filter(fn($i) => $i->product->voucher_code && $i->product->voucher_discount > 0);
        @endphp
        <div class="card checkout-voucher-card">
            <div class="card-header">
                <span class="card-title">🎟️ Vouchers</span>
            </div>
            <div class="card-body">
                @if($voucherProducts->isEmpty())
                    <p class="checkout-voucher-empty">No vouchers available for your selected items.</p>
                @else
                    <p class="checkout-voucher-hint">Select a voucher to apply a discount.</p>
                    <div class="checkout-voucher-list" id="voucherList">
                        <label class="checkout-voucher-option checkout-voucher-none">
                            <input type="radio" name="voucher_pick" value="" checked data-discount="0" data-base-total="{{ $total }}">
                            <div class="checkout-voucher-info">
                                <span class="checkout-voucher-label">No voucher</span>
                                <span class="checkout-voucher-desc">Use full price</span>
                            </div>
                        </label>
                        @foreach($voucherProducts as $item)
                        <label class="checkout-voucher-option">
                            <input type="radio" name="voucher_pick"
                                value="{{ $item->product->voucher_code }}"
                                data-discount="{{ $item->product->voucher_discount }}"
                                data-item-subtotal="{{ $item->product->effective_price * $item->quantity }}"
                                data-base-total="{{ $total }}">
                            <div class="checkout-voucher-badge">{{ $item->product->voucher_discount }}% OFF</div>
                            <div class="checkout-voucher-info">
                                <span class="checkout-voucher-label">{{ $item->product->voucher_code }}</span>
                                <span class="checkout-voucher-desc">{{ $item->product->voucher_discount }}% off on <strong>{{ $item->product->name }}</strong></span>
                            </div>
                            <span class="checkout-voucher-savings">−₱{{ number_format($item->product->effective_price * $item->quantity * $item->product->voucher_discount / 100, 2) }}</span>
                        </label>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- RIGHT: Order Summary --}}
    <div class="checkout-right">
        <div class="card">
            <div class="card-header"><span class="card-title">Order Summary</span></div>
            <div class="card-body">
                <form method="POST" action="/buyer/cart/checkout" id="checkoutPlaceForm">
                    @csrf
                    @foreach($itemIds as $id)
                        <input type="hidden" name="item_ids[]" value="{{ $id }}">
                    @endforeach
                    <input type="hidden" name="voucher_code" id="voucherCodeInput" value="">

                    <div class="checkout-defaults">
                        <div><strong>Payment</strong><span>Cash on Delivery</span></div>
                        <div><strong>Delivery</strong><span>{{ $logisticsProvider?->business_name ?: $logisticsProvider?->full_name ?: 'PickSell Logistics' }}</span></div>
                    </div>

                    @if(!$logisticsProvider)
                        <small class="blade-inline-20">Checkout is temporarily unavailable while logistics is being configured.</small>
                    @endif

                    <hr class="blade-inline-21">

                    <div class="checkout-summary-row">
                        <span>Subtotal</span>
                        <span id="summarySubtotal">₱{{ number_format($total, 2) }}</span>
                    </div>
                    <div class="checkout-summary-row checkout-discount-row" id="discountRow" style="display:none">
                        <span>Voucher Discount</span>
                        <span class="checkout-discount-val" id="summaryDiscount"></span>
                    </div>
                    <hr class="blade-inline-21">
                    <div class="blade-inline-24">
                        <span>Total</span>
                        <span class="blade-inline-25" id="summaryTotal">₱{{ number_format($total, 2) }}</span>
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

@section('scripts')
<script>
document.querySelectorAll('input[name="voucher_pick"]').forEach(radio => {
    radio.addEventListener('change', function () {
        const discount = parseFloat(this.dataset.discount) || 0;
        const itemSubtotal = parseFloat(this.dataset.itemSubtotal) || 0;
        const baseTotal = parseFloat(this.dataset.baseTotal) || 0;
        const saving = itemSubtotal * discount / 100;
        const finalTotal = baseTotal - saving;

        document.getElementById('voucherCodeInput').value = this.value;
        document.getElementById('summaryTotal').textContent = '₱' + finalTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 });

        const discountRow = document.getElementById('discountRow');
        if (saving > 0) {
            discountRow.style.display = 'flex';
            document.getElementById('summaryDiscount').textContent = '−₱' + saving.toLocaleString('en-PH', { minimumFractionDigits: 2 });
        } else {
            discountRow.style.display = 'none';
        }

        document.querySelectorAll('.checkout-voucher-option').forEach(el => el.classList.remove('selected'));
        this.closest('.checkout-voucher-option').classList.add('selected');
    });
});
</script>
@endsection
