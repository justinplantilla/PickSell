@extends('buyer.layout')
@section('styles')
@vite(['resources/css/views/buyer-cart.css', 'resources/css/components/vouchers.css'])
@endsection
@section('title', 'Checkout')

@section('content')
<h2 class="cart-heading">Checkout</h2>

@php
    // Every checkout link keeps the items, address and vouchers picked so far, changing only what it says.
    $keep = fn ($v) => $v !== null && $v !== [];
    $checkoutQuery = array_filter(['item_ids' => $itemIds, 'address_id' => $addressId, 'store_voucher' => $vouchers['picked']['stores'], 'platform_voucher' => $vouchers['picked']['platform'], 'carrier' => $carriers, 'product_voucher' => $vouchers['products']], $keep);
    $checkoutLink = fn (array $changes) => route('buyer.checkout.start', array_filter(array_replace($checkoutQuery, $changes), $keep));
    // A store takes one voucher: its own store voucher or one of its items' product vouchers.
    $storeLink = fn ($sellerId, ?int $voucherId, ?string $productCode) => $checkoutLink([
        'store_voucher' => array_filter(array_replace($vouchers['picked']['stores'], [$sellerId => $voucherId])),
        'product_voucher' => array_filter(array_replace($vouchers['products'], [$sellerId => $productCode])),
    ]).'#store-vouchers-'.$sellerId;
    // A typed code form; code_for says whose vouchers it was typed into, so its error shows there.
    $codeTypedFor = fn ($codeFor) => (string) request('code_for') === (string) $codeFor;
    $codeQuery = collect($checkoutQuery)->except('code')->all();
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
                                {{-- An archived product has no page to open, so it is not a link. --}}
                                @php $productUrl = $item->product->status === 'active' ? route('buyer.product', array_filter([$item->product, 'option' => $item->variation_id])) : null; @endphp
                                @if($productUrl)<a href="{{ $productUrl }}" class="cart-thumb-link" tabindex="-1" aria-hidden="true">@endif
                                @if($thumb = $item->product->imageFor($item->variation))
                                    <img src="{{ Storage::url($thumb) }}" class="cart-thumb" alt="">
                                @else
                                    <div class="cart-thumb-placeholder"></div>
                                @endif
                                @if($productUrl)</a>@endif
                                <div>
                                    @if($productUrl)<a href="{{ $productUrl }}" class="cart-title cart-title-link">{{ $item->product->name }}</a>@else<div class="cart-title">{{ $item->product->name }}</div>@endif
                                </div>
                            </div>
                        </td>
                        <td class="cart-muted-text-2" data-label="Option">{{ $item->variation?->label ?? '—' }}</td>
                        <td class="cart-accent-text" data-label="Price">₱{{ number_format($item->unit_price, 2) }}</td>
                        <td data-label="Qty">{{ $item->quantity }}</td>
                        <td class="cart-strong" data-label="Subtotal">₱{{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                    @endforeach
                    @php $storeTotal = $storeTotals[$sellerId]; @endphp
                    <tr class="checkout-store-summary-row"><td colspan="5">
                        {{-- The store's money: subtotal, its voucher (opens its vouchers), shipping, PickSell savings, then what the rider collects. --}}
                        <div class="checkout-store-summary">
                        <div class="checkout-store-line"><span>Store subtotal</span><span>&#8369;{{ number_format($storeTotal['subtotal'], 2) }}</span></div>
                        @php
                            $storeOptions = $vouchers['stores'][$sellerId] ?? collect();
                            $productOptions = $vouchers['productOptions'][$sellerId] ?? collect();
                            $pickedStore = $vouchers['picked']['stores'][$sellerId] ?? null;
                            $pickedProduct = $vouchers['products'][$sellerId] ?? null;
                            $storeName = $store->business_name ?? $store->full_name;
                        @endphp
                        {{-- This store's vouchers: its store vouchers and its items' product vouchers, one of them at a time. --}}
                        <details class="checkout-store-vouchers" id="store-vouchers-{{ $sellerId }}" @if($codeTypedFor($sellerId)) open @endif>
                            <summary>
                                <span class="checkout-store-voucher-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M21.41 11.58l-9-9A2 2 0 0 0 11 2H4a2 2 0 0 0-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58s1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7A1.5 1.5 0 1 1 7 5.5 1.5 1.5 0 0 1 5.5 7z"/></svg>
                                    @if($pickedProduct) Product voucher <code>{{ $pickedProduct }}</code>
                                    @elseif($pickedStore) Store voucher <code>{{ $storeOptions->first(fn ($o) => $o['voucher']->id === $pickedStore)['voucher']->code ?? '' }}</code>
                                    @else Store voucher @endif
                                </span>
                                @if($pickedProduct)
                                    <span class="checkout-store-voucher-picked">-&#8369;{{ number_format($storeTotal['product_discount'], 2) }}</span>
                                @elseif($pickedStore)
                                    <span class="checkout-store-voucher-picked">-&#8369;{{ number_format($storeTotal['store_discount'], 2) }}</span>
                                @else
                                    <span class="checkout-store-voucher-none">{{ $storeOptions->isEmpty() && $productOptions->isEmpty() ? 'Enter code' : 'Choose voucher' }}</span>
                                @endif
                                <svg class="checkout-store-voucher-chevron" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></svg>
                            </summary>
                            <div class="checkout-store-vouchers-body">
                                @include('buyer.partials.checkout-voucher-code', ['query' => $codeQuery, 'codeFor' => $sellerId, 'label' => 'Have a '.$storeName.' voucher code?', 'placeholder' => 'Store or product code',
                                    'error' => $codeTypedFor($sellerId) ? $vouchers['error'] : null, 'typed' => $codeTypedFor($sellerId) ? request('code') : null])
                                <p class="checkout-voucher-hint">One voucher per store: a store voucher or a product voucher.</p>
                                <div class="checkout-voucher-list">
                                    @foreach($storeOptions as $option)
                                    @php $isPicked = $pickedStore === $option['voucher']->id; @endphp
                                    @include('buyer.partials.voucher-option', ['option' => $option, 'isPicked' => $isPicked, 'scope' => 'for '.$storeName.' items only',
                                        'href' => $isPicked ? $storeLink($sellerId, null, null) : ($option['problem'] ? '#store-vouchers-'.$sellerId : $storeLink($sellerId, $option['voucher']->id, null))])
                                    @endforeach
                                    @foreach($productOptions as $product)
                                    @php $isPicked = $pickedProduct === $product['code']; @endphp
                                    <a class="checkout-voucher-option voucher-ticket {{ $isPicked ? 'selected' : '' }}" href="{{ $isPicked ? $storeLink($sellerId, null, null) : $storeLink($sellerId, null, $product['code']) }}" @if($isPicked) aria-current="true" @endif>
                                        <div class="checkout-voucher-info">
                                            <span class="voucher-ticket-amount">{{ rtrim(rtrim(number_format($product['percent'], 2), '0'), '.') }}% off · {{ $product['code'] }}</span>
                                            <span class="checkout-voucher-desc">Product voucher for <strong>{{ $product['product'] }}</strong></span>
                                        </div>
                                        <div class="voucher-ticket-side"><span class="checkout-voucher-savings">{{ $isPicked ? 'Remove' : '−₱'.number_format($product['discount'], 2) }}</span></div>
                                    </a>
                                    @endforeach
                                </div>
                                @if($storeOptions->isEmpty() && $productOptions->isEmpty())<p class="checkout-voucher-empty">No vouchers from this store for these items.</p>@endif
                            </div>
                        </details>
                        <div class="checkout-store-line"><span>Shipping</span><span>&#8369;{{ number_format($storeTotal['shipping_fee'], 2) }}</span></div>
                        @if($storeTotal['platform_discount'] + $storeTotal['shipping_discount'] > 0)<div class="checkout-store-line is-saving"><span>PickSell savings</span><span>-&#8369;{{ number_format($storeTotal['platform_discount'] + $storeTotal['shipping_discount'], 2) }}</span></div>@endif
                        <div class="checkout-store-line is-total"><span>Store total <small>Cash on Delivery</small></span><strong>&#8369;{{ number_format($storeTotal['total'], 2) }}</strong></div>
                        </div>
                    </td></tr>
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
                                {{-- With one courier the chip names it; with a choice, the cards below do. --}}
                                @if(count($parcel['carriers']) <= 1)<span class="checkout-ship-chip">{{ $parcel['carrier_name'] }}</span>@endif
                                @if($parcel['estimated_delivery'])
                                    <span class="checkout-ship-chip is-eta"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>Arrives {{ $parcel['estimated_delivery']['label'] }}</span>
                                @endif
                                <span class="checkout-ship-chip"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg>{{ $parcel['chargeable_weight_kg'] }} kg</span>
                                @if($parcel['parcel_class'] !== 'STANDARD')<span class="checkout-ship-chip">{{ ucfirst(strtolower($parcel['parcel_class'])) }} parcel</span>@endif
                            </div>
                        @endif
                        @if(count($parcel['carriers']) > 1)
                            <p class="checkout-ship-label">Choose a courier</p>
                            <div class="checkout-ship-carriers" role="radiogroup" aria-label="Logistics company for {{ $parcel['seller']->business_name ?? $parcel['seller']->full_name }}">
                                @foreach($parcel['carriers'] as $option)
                                    <a class="checkout-ship-carrier {{ $option['selected'] ? 'selected' : '' }} {{ $option['ok'] ? '' : 'is-unavailable' }}"
                                       href="{{ $option['ok'] && ! $option['selected'] ? $checkoutLink(['carrier' => array_replace($carriers, [$sellerId => (int) $option['id']])]).'#shipping' : '#shipping' }}"
                                       role="radio" aria-checked="{{ $option['selected'] ? 'true' : 'false' }}">
                                        <span class="checkout-ship-radio" aria-hidden="true"></span>
                                        <span class="checkout-ship-carrier-info">
                                            <span class="checkout-ship-carrier-top">
                                                <span class="checkout-ship-carrier-name">{{ $option['name'] }}</span>
                                                @if($option['ok'])<span class="checkout-ship-carrier-fee">₱{{ number_format((float) $option['delivery_fee'], 2) }}</span>@endif
                                            </span>
                                            <span class="checkout-ship-carrier-desc">{{ $option['ok'] ? ($option['estimated_delivery'] ? 'Arrives '.$option['estimated_delivery']['label'] : 'Date shown after ordering') : $option['message'] }}</span>
                                            @if($option['is_default'])<span class="checkout-ship-badge">Recommended</span>@endif
                                        </span>
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

        {{-- PickSell vouchers: one per checkout, paid by PickSell (store and product vouchers are on each store above). --}}
        <div class="card checkout-voucher-card" id="vouchers">
            <div class="card-header">
                <span class="card-title checkout-card-title"><svg class="checkout-title-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 10V6c0-1.11-.9-2-2-2H4c-1.1 0-1.99.89-1.99 2v4c1.1 0 1.99.9 1.99 2s-.89 2-2 2v4c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2v-4c-1.1 0-2-.9-2-2s.9-2 2-2zm-9 7.5h-2v-2h2v2zm0-4.5h-2v-2h2v2zm0-4.5h-2v-2h2v2z"/></svg> PickSell Vouchers</span>
            </div>
            <div class="card-body">
                @include('buyer.partials.checkout-voucher-code', ['query' => $codeQuery, 'codeFor' => 'platform', 'label' => 'Have a PickSell voucher code?', 'placeholder' => 'e.g. SAVE50',
                    'error' => $codeTypedFor('platform') ? $vouchers['error'] : null, 'typed' => $codeTypedFor('platform') ? request('code') : null])
                {{-- Any other voucher problem (e.g. a picked voucher no longer usable). --}}
                @if($vouchers['error'] && ! request('code_for'))<p class="checkout-voucher-error" role="alert">{{ $vouchers['error'] }}</p>@endif
                @if($vouchers['platform']->isNotEmpty())
                    <div class="checkout-voucher-list">
                        @foreach($vouchers['platform'] as $option)
                        @php $isPicked = $vouchers['picked']['platform'] === $option['voucher']->id; @endphp
                        @include('buyer.partials.voucher-option', ['option' => $option, 'isPicked' => $isPicked, 'scope' => 'counts your whole order',
                            'href' => $isPicked ? $checkoutLink(['platform_voucher' => null]) : ($option['problem'] ? '#vouchers' : $checkoutLink(['platform_voucher' => $option['voucher']->id]))])
                        @endforeach
                    </div>
                @else
                    <p class="checkout-voucher-empty">No PickSell vouchers right now.</p>
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
                    <input type="hidden" name="checkout_key" value="{{ (string) Str::uuid() }}">
                    @foreach($itemIds as $id)
                        <input type="hidden" name="item_ids[]" value="{{ $id }}">
                    @endforeach
                    @foreach($vouchers['products'] as $sellerId => $code)
                        <input type="hidden" name="product_vouchers[{{ $sellerId }}]" value="{{ $code }}">
                    @endforeach
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
                    @if($productSaving > 0)
                    <div class="checkout-summary-row checkout-discount-row">
                        <span>Product vouchers</span>
                        <span class="checkout-discount-val">−₱{{ number_format($productSaving, 2) }}</span>
                    </div>
                    @endif
                    <hr class="cart-divider">
                    <p class="form-hint checkout-cod-note">Each store has a separate order and COD amount. Pay only for parcels delivered.</p>
                    <div class="cart-row-5">
                        <span>Total</span>
                        <span class="cart-accent" id="summaryTotal">₱{{ number_format($total + $shippingTotal - $vouchers['total'] - $productSaving, 2) }}</span>
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
// A voucher that can't be used for these items answers a tap with why, instead of doing nothing.
document.addEventListener('click', (event) => {
    const ticket = event.target.closest('[data-voucher-blocked]');
    if (!ticket) return;
    event.preventDefault();
    ticket.classList.remove('is-shaking');
    void ticket.offsetWidth;
    ticket.classList.add('is-shaking');
    const message = "Can't use this voucher. " + ticket.dataset.voucherBlocked;
    window.notify ? window.notify.warning(message) : alert(message);
});
</script>
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

// A store's vouchers stay open after picking one there (its links end in #store-vouchers-{seller}).
(function () {
    const open = () => {
        const panel = location.hash.startsWith('#store-vouchers-') && document.querySelector(location.hash);
        if (panel) { panel.open = true; panel.scrollIntoView({ block: 'center' }); }
    };
    window.addEventListener('hashchange', open);
    open();
})();
</script>
@endsection
