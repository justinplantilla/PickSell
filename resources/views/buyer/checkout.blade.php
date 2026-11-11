@extends('buyer.layout')
@section('styles')
@vite('resources/css/views/buyer-cart.css')
@endsection
@section('title', 'Checkout')

@section('content')
<h2 class="cart-heading">Checkout</h2>

@php
    // Every checkout link keeps the items, address and vouchers picked so far, changing only what it says.
    $keep = fn ($v) => $v !== null && $v !== [];
    $checkoutQuery = array_filter(['item_ids' => $itemIds, 'address_id' => $addressId, 'store_voucher' => $vouchers['picked']['stores'], 'platform_voucher' => $vouchers['picked']['platform'], 'carrier' => $carriers], $keep);
    $checkoutLink = fn (array $changes) => route('buyer.checkout.start', array_filter(array_replace($checkoutQuery, $changes), $keep));
@endphp

<div class="checkout-layout">
    {{-- LEFT: Order Items --}}
    <div class="checkout-left">
        {{-- Delivery address: the default (profile) one or another from the address book; shipping is priced on it. --}}
        <div class="card checkout-address-card" id="delivery-address">
            <div class="card-header"><span class="card-title checkout-card-title"><svg class="checkout-title-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg> Delivery Address</span></div>
            <div class="card-body">
                <div class="checkout-address-current">
                    <strong>{{ $deliverTo->recipientName }}</strong> <span class="cart-muted-text-2">{{ $deliverTo->phone }}</span>
                    <span class="checkout-address-tag">{{ $addressId ? ($deliverTo->label ?: 'Saved address') : 'Default' }}</span>
                    <p>{{ $deliverTo->line() ?: 'No address yet' }}</p>
                    @unless($deliverTo->isComplete())
                        <p class="checkout-address-warning" role="alert">This address needs a province, city and barangay before you can order. @if(! $addressId)<a href="{{ route('buyer.account') }}">Complete your profile address</a>@endif</p>
                    @endunless
                </div>

                <details class="checkout-address-choices" @if($errors->hasAny(['recipient_name', 'contact_no', 'province', 'municipality', 'barangay'])) open @endif>
                    <summary>Change or add an address</summary>
                    <ul class="checkout-address-list">
                        @php $checkoutUrl = fn ($id) => $checkoutLink(['address_id' => $id]); @endphp
                        <li class="{{ ! $addressId ? 'is-selected' : '' }}">
                            <a href="{{ $checkoutUrl(null) }}" @if(! $addressId) aria-current="true" @endif>
                                <strong>Default</strong> · {{ auth()->user()->full_name }}
                                <span>{{ \App\Support\DeliveryAddress::fromProfile(auth()->user())->line() ?: 'Not set' }}</span>
                            </a>
                            <a href="{{ route('buyer.account') }}" class="checkout-address-action">Edit in profile</a>
                        </li>
                        @foreach($addresses as $saved)
                        <li class="{{ $addressId === $saved->id ? 'is-selected' : '' }}">
                            <a href="{{ $checkoutUrl($saved->id) }}" @if($addressId === $saved->id) aria-current="true" @endif>
                                <strong>{{ $saved->label ?: 'Saved address' }}</strong> · {{ $saved->recipient_name }}
                                <span>{{ \App\Support\DeliveryAddress::fromBook($saved)->line() }}</span>
                            </a>
                            <details class="checkout-address-edit">
                                <summary class="checkout-address-action">Edit</summary>
                                @include('buyer.partials.address-form', ['address' => $saved])
                                <div class="address-extra-actions">
                                    <form method="POST" action="{{ route('buyer.addresses.default', $saved) }}">
                                        @csrf
                                        @foreach($itemIds as $id)<input type="hidden" name="item_ids[]" value="{{ $id }}">@endforeach
                                        <button type="submit" class="btn btn-outline btn-sm">Set as default</button>
                                    </form>
                                    <form method="POST" action="{{ route('buyer.addresses.destroy', $saved) }}">
                                        @csrf @method('DELETE')
                                        @foreach($itemIds as $id)<input type="hidden" name="item_ids[]" value="{{ $id }}">@endforeach
                                        <button type="submit" class="btn btn-outline btn-sm checkout-address-delete">Delete this address</button>
                                    </form>
                                </div>
                            </details>
                        </li>
                        @endforeach
                    </ul>
                    @if($addresses->count() < \App\Http\Controllers\BuyerAddressController::MAX_ADDRESSES)
                    <details class="checkout-address-new" @if($errors->hasAny(['recipient_name', 'contact_no', 'province', 'municipality', 'barangay'])) open @endif>
                        <summary class="btn btn-outline btn-sm">+ Add new address</summary>
                        @include('buyer.partials.address-form', ['address' => null])
                    </details>
                    @endif
                </details>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title checkout-card-title">Order Items</span></div>
            <div class="cart-scroll">
                <table class="checkout-table table-cards">
                    <thead><tr><th>Product</th><th>Variation</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
                    @foreach($items->groupBy(fn ($item) => $item->product->seller_id) as $sellerId => $storeItems)
                    @php $store = $storeItems->first()->product->seller; @endphp
                    <tbody class="cart-store">
                    <tr class="cart-store-head">
                        <td colspan="5"><a href="{{ route('buyer.seller', $store) }}" class="cart-store-name">{{ $store->business_name ?? $store->full_name }}</a></td>
                    </tr>
                    @foreach($storeItems as $item)
                    <tr>
                        <td class="cell-main">
                            <div class="cart-row-2">
                                @if($item->product->primary_image)
                                    <img src="{{ Storage::url($item->product->primary_image) }}" class="cart-thumb">
                                @else
                                    <div class="cart-thumb-placeholder"></div>
                                @endif
                                <div>
                                    <div class="cart-title">{{ $item->product->name }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="cart-muted-text-2" data-label="Option">{{ $item->variation?->label ?? '—' }}</td>
                        <td class="cart-accent-text" data-label="Price">₱{{ number_format($item->unit_price, 2) }}</td>
                        <td data-label="Qty">{{ $item->quantity }}</td>
                        <td class="cart-strong" data-label="Subtotal">₱{{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                    @endforeach
                    <tr class="checkout-note-row">
                        <td colspan="5">
                            <label class="form-label" for="note-{{ $sellerId }}">Note to {{ $store->business_name ?? $store->full_name }} <span class="cart-muted-text">(optional)</span></label>
                            <textarea id="note-{{ $sellerId }}" name="notes[{{ $sellerId }}]" form="checkoutPlaceForm" class="form-control" rows="2" maxlength="300" placeholder="e.g. Please wrap it well, it's a gift.">{{ old('notes.'.$sellerId) }}</textarea>
                        </td>
                    </tr>
                    </tbody>
                    @endforeach
                </table>
            </div>
        </div>

        {{-- Shipping and Vouchers sit side by side (stacked on narrow screens). --}}
        <div class="checkout-pair">
        {{-- Shipping: one parcel and one delivery fee per seller, with the logistics company the buyer picks (Pickrush pre-selected). --}}
        <div class="card checkout-voucher-card checkout-ship-card" id="shipping">
            <div class="card-header"><span class="card-title checkout-card-title"><svg class="checkout-title-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm13.5-9 1.96 2.5H17V9.5h2.5zM18 18.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/></svg> Shipping</span></div>
            <div class="card-body checkout-ship-body">
                @foreach($shipping as $sellerId => $parcel)
                    <div class="checkout-ship-parcel {{ $parcel['ok'] ? '' : 'is-error' }}">
                        <div class="checkout-ship-head">
                            <span class="checkout-ship-store">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/></svg>
                                {{ $parcel['seller']->business_name ?? $parcel['seller']->full_name }}
                            </span>
                            @if($parcel['ok'])
                                <span class="checkout-ship-fee">₱{{ number_format((float) $parcel['delivery_fee'], 2) }}</span>
                            @endif
                        </div>
                        @if($parcel['ok'])
                            <div class="checkout-ship-meta">
                                <span class="checkout-ship-chip">{{ $parcel['carrier_name'] }}</span>
                                @if($parcel['estimated_delivery'])<span class="checkout-ship-chip is-eta">Arrives {{ $parcel['estimated_delivery']['label'] }}</span>@endif
                                <span class="checkout-ship-chip">{{ $parcel['chargeable_weight_kg'] }} kg</span>
                                @if($parcel['parcel_class'] !== 'STANDARD')<span class="checkout-ship-chip">{{ ucfirst(strtolower($parcel['parcel_class'])) }} parcel</span>@endif
                            </div>
                        @endif
                        @if(count($parcel['carriers']) > 1)
                            <p class="checkout-ship-label">Ship with</p>
                            <div class="checkout-ship-carriers" role="radiogroup" aria-label="Logistics company for {{ $parcel['seller']->business_name ?? $parcel['seller']->full_name }}">
                                @foreach($parcel['carriers'] as $option)
                                    <a class="checkout-ship-carrier {{ $option['selected'] ? 'selected' : '' }} {{ $option['ok'] ? '' : 'is-unavailable' }}"
                                       href="{{ $option['ok'] && ! $option['selected'] ? $checkoutLink(['carrier' => array_replace($carriers, [$sellerId => (int) $option['id']])]).'#shipping' : '#shipping' }}"
                                       role="radio" aria-checked="{{ $option['selected'] ? 'true' : 'false' }}">
                                        <span class="checkout-ship-carrier-name">{{ $option['name'] }}@if($option['is_default']) <small>recommended</small>@endif</span>
                                        <span class="checkout-ship-carrier-desc">{{ $option['ok'] ? ($option['estimated_delivery'] ? 'Arrives '.$option['estimated_delivery']['label'] : 'Date shown after ordering') : $option['message'] }}</span>
                                        @if($option['ok'])<span class="checkout-ship-carrier-fee">₱{{ number_format((float) $option['delivery_fee'], 2) }}</span>@endif
                                    </a>
                                @endforeach
                            </div>
                        @endif
                        @unless($parcel['ok'])
                            <p class="checkout-ship-error" role="alert">{{ $parcel['message'] }}</p>
                        @endunless
                    </div>
                @endforeach
                <div class="checkout-ship-total">
                    <span>Shipping total</span>
                    <strong>₱{{ number_format($shippingTotal, 2) }}</strong>
                </div>
            </div>
        </div>

        {{-- Voucher Picker --}}
        @php
            $voucherProducts = $items->filter(fn($i) => $i->product->voucher_code && $i->product->voucher_discount > 0);
        @endphp
        <div class="card checkout-voucher-card" id="vouchers">
            <div class="card-header">
                <span class="card-title checkout-card-title"><svg class="checkout-title-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 10V6c0-1.11-.9-2-2-2H4c-1.1 0-1.99.89-1.99 2v4c1.1 0 1.99.9 1.99 2s-.89 2-2 2v4c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2v-4c-1.1 0-2-.9-2-2s.9-2 2-2zm-9 7.5h-2v-2h2v2zm0-4.5h-2v-2h2v2zm0-4.5h-2v-2h2v2z"/></svg> Vouchers</span>
            </div>
            <div class="card-body">
                {{-- A typed code picks the store or PickSell voucher it belongs to. --}}
                <form method="GET" action="{{ route('buyer.checkout.start') }}" class="checkout-voucher-code">
                    @foreach(collect($checkoutQuery)->except('code') as $key => $value)
                        @foreach((array) $value as $sub => $v)
                            <input type="hidden" name="{{ is_array($value) ? $key.'['.$sub.']' : $key }}" value="{{ $v }}">
                        @endforeach
                    @endforeach
                    <label for="voucherCode" class="checkout-voucher-hint">Have a voucher code?</label>
                    <div class="checkout-voucher-code-row">
                        <input type="text" id="voucherCode" name="code" class="form-control" maxlength="30" placeholder="e.g. SAVE50" value="{{ request('code') }}">
                        <button type="submit" class="btn btn-outline btn-sm">Apply</button>
                    </div>
                    @if($vouchers['error'])<p class="checkout-voucher-error" role="alert">{{ $vouchers['error'] }}</p>@endif
                </form>

                {{-- Store vouchers: one per store, paid by the store. --}}
                @foreach($vouchers['stores'] as $sellerId => $options)
                    @continue($options->isEmpty())
                    @php $store = $items->firstWhere('product.seller_id', $sellerId)->product->seller; $pickedId = $vouchers['picked']['stores'][$sellerId] ?? null; @endphp
                    <p class="checkout-voucher-hint">{{ $store->business_name ?? $store->full_name }} vouchers</p>
                    <div class="checkout-voucher-list">
                        @foreach($options as $option)
                        @php $isPicked = $pickedId === $option['voucher']->id; @endphp
                        <a class="checkout-voucher-option {{ $isPicked ? 'selected' : '' }} {{ $option['problem'] ? 'is-unavailable' : '' }}"
                           href="{{ $isPicked ? $checkoutLink(['store_voucher' => array_diff_key($vouchers['picked']['stores'], [$sellerId => 0])]) : ($option['problem'] ? '#vouchers' : $checkoutLink(['store_voucher' => array_replace($vouchers['picked']['stores'], [$sellerId => $option['voucher']->id])])) }}"
                           @if($isPicked) aria-current="true" @endif @if($option['problem']) aria-disabled="true" @endif>
                            <div class="checkout-voucher-badge">{{ $option['voucher']->code }}</div>
                            <div class="checkout-voucher-info">
                                <span class="checkout-voucher-label">{{ $option['voucher']->title }}</span>
                                <span class="checkout-voucher-desc">{{ $option['problem'] ?? $option['voucher']->summary }}</span>
                            </div>
                            <span class="checkout-voucher-savings">{{ $isPicked ? 'Remove' : ($option['problem'] ? '' : '−₱'.number_format($option['discount'], 2)) }}</span>
                        </a>
                        @endforeach
                    </div>
                @endforeach

                {{-- PickSell vouchers: one per checkout, paid by PickSell. --}}
                @if($vouchers['platform']->isNotEmpty())
                    <p class="checkout-voucher-hint">PickSell vouchers</p>
                    <div class="checkout-voucher-list">
                        @foreach($vouchers['platform'] as $option)
                        @php $isPicked = $vouchers['picked']['platform'] === $option['voucher']->id; @endphp
                        <a class="checkout-voucher-option {{ $isPicked ? 'selected' : '' }} {{ $option['problem'] ? 'is-unavailable' : '' }}"
                           href="{{ $isPicked ? $checkoutLink(['platform_voucher' => null]) : ($option['problem'] ? '#vouchers' : $checkoutLink(['platform_voucher' => $option['voucher']->id])) }}"
                           @if($isPicked) aria-current="true" @endif @if($option['problem']) aria-disabled="true" @endif>
                            <div class="checkout-voucher-badge checkout-voucher-badge--platform">{{ $option['voucher']->code }}</div>
                            <div class="checkout-voucher-info">
                                <span class="checkout-voucher-label">{{ $option['voucher']->title }}</span>
                                <span class="checkout-voucher-desc">{{ $option['problem'] ?? $option['voucher']->summary }}</span>
                            </div>
                            <span class="checkout-voucher-savings">{{ $isPicked ? 'Remove' : ($option['problem'] ? '' : '−₱'.number_format($option['discount'], 2)) }}</span>
                        </a>
                        @endforeach
                    </div>
                @endif

                @if($voucherProducts->isEmpty())
                    @if($vouchers['stores']->every->isEmpty() && $vouchers['platform']->isEmpty())
                    <p class="checkout-voucher-empty">No vouchers available for your selected items.</p>
                    @endif
                @else
                    <p class="checkout-voucher-hint">Product vouchers</p>
                    <div class="checkout-voucher-list" id="voucherList">
                        <label class="checkout-voucher-option checkout-voucher-none">
                            <input type="radio" name="voucher_pick" value="" checked data-discount="0" data-base-total="{{ $total + $shippingTotal - $vouchers['total'] }}">
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
                                data-item-subtotal="{{ $item->subtotal }}"
                                data-base-total="{{ $total + $shippingTotal - $vouchers['total'] }}">
                            <div class="checkout-voucher-badge">{{ $item->product->voucher_discount }}% OFF</div>
                            <div class="checkout-voucher-info">
                                <span class="checkout-voucher-label">{{ $item->product->voucher_code }}</span>
                                <span class="checkout-voucher-desc">{{ $item->product->voucher_discount }}% off on <strong>{{ $item->product->name }}</strong></span>
                            </div>
                            <span class="checkout-voucher-savings">−₱{{ number_format($item->subtotal * $item->product->voucher_discount / 100, 2) }}</span>
                        </label>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        </div>
    </div>

    {{-- RIGHT: Order Summary --}}
    <div class="checkout-right" id="checkoutSummary">
        <div class="card">
            <div class="card-header"><span class="card-title checkout-card-title">Order Summary</span></div>
            <div class="card-body">
                <form method="POST" action="/buyer/cart/checkout" id="checkoutPlaceForm">
                    @csrf
                    @foreach($itemIds as $id)
                        <input type="hidden" name="item_ids[]" value="{{ $id }}">
                    @endforeach
                    <input type="hidden" name="voucher_code" id="voucherCodeInput" value="">
                    @if($addressId)<input type="hidden" name="address_id" value="{{ $addressId }}">@endif
                    @foreach($vouchers['picked']['stores'] as $sellerId => $voucherId)
                        <input type="hidden" name="store_vouchers[{{ $sellerId }}]" value="{{ $voucherId }}">
                    @endforeach
                    @if($vouchers['picked']['platform'])<input type="hidden" name="platform_voucher" value="{{ $vouchers['picked']['platform'] }}">@endif
                    @foreach($carriers as $sellerId => $companyId)
                        <input type="hidden" name="carriers[{{ $sellerId }}]" value="{{ $companyId }}">
                    @endforeach

                    <div class="checkout-defaults">
                        <div><strong>Payment</strong><span>Cash on Delivery</span></div>
                        <div><strong>Delivery</strong><span>{{ $shipping->pluck('carrier_name')->unique()->join(', ') ?: 'Pickrush' }}</span></div>
                        <div><strong>Ship to</strong><span><a href="#delivery-address">{{ $deliverTo->municipality ?: 'Set an address' }}</a></span></div>
                    </div>

                    <hr class="cart-divider">

                    <div class="checkout-summary-row">
                        <span>Subtotal</span>
                        <span id="summarySubtotal">₱{{ number_format($total, 2) }}</span>
                    </div>
                    <div class="checkout-summary-row">
                        <span>Shipping</span>
                        <span id="summaryShipping">₱{{ number_format($shippingTotal, 2) }}</span>
                    </div>
                    @if(array_sum($vouchers['storeSavings']) > 0)
                    <div class="checkout-summary-row checkout-discount-row">
                        <span>Store vouchers</span>
                        <span class="checkout-discount-val">−₱{{ number_format(array_sum($vouchers['storeSavings']), 2) }}</span>
                    </div>
                    @endif
                    @if($vouchers['platformSaving'] > 0)
                    <div class="checkout-summary-row checkout-discount-row">
                        <span>PickSell voucher</span>
                        <span class="checkout-discount-val">−₱{{ number_format($vouchers['platformSaving'], 2) }}</span>
                    </div>
                    @endif
                    <div class="checkout-summary-row checkout-discount-row" id="discountRow" style="display:none">
                        <span>Product voucher</span>
                        <span class="checkout-discount-val" id="summaryDiscount"></span>
                    </div>
                    <hr class="cart-divider">
                    <div class="cart-row-5">
                        <span>Total</span>
                        <span class="cart-accent" id="summaryTotal">₱{{ number_format($total + $shippingTotal - $vouchers['total'], 2) }}</span>
                    </div>
                    <button type="submit" class="btn btn-coral cart-block" @disabled(! $canPlace)>
                        Place Order
                    </button>
                    @unless($canPlace)
                        <small class="form-hint">{{ $deliverTo->isComplete() ? 'Shipping could not be priced for some items; see Shipping on the left.' : 'Complete the delivery address first.' }}</small>
                    @endunless
                </form>
                <a href="/buyer/cart" class="btn btn-outline" style="width:100%;justify-content:center;margin-top:0.5rem;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg> Back to Cart</a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@vite('resources/js/components/address-picker.js')
<script>
// Keeps the Order Summary in view while scrolling. When it is taller than the screen it scrolls
// through with the page and sticks at its top or bottom edge, so no part of the card is cut off.
(function () {
    const box = document.getElementById('checkoutSummary');
    if (!box) return;
    const gap = 76; // 60px navbar + 16px
    let top = gap, lastY = window.scrollY;
    function update() {
        const overflow = box.offsetHeight - (window.innerHeight - gap - 16);
        const y = window.scrollY;
        top = overflow <= 0 ? gap : Math.min(gap, Math.max(gap - overflow, top - (y - lastY)));
        lastY = y;
        box.style.top = top + 'px';
    }
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
})();

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
