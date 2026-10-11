@extends('buyer.layout')
@section('styles')
@vite('resources/css/views/buyer-cart.css')
@endsection
@section('title', 'My Cart')

@section('content')
<h2 class="blade-inline-1">My Cart ({{ $items->total() }} item{{ $items->total() !== 1 ? 's' : '' }})</h2>

@if($items->isEmpty())
<div class="blade-inline-2">
    <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" fill="currentColor" viewBox="0 0 24 24" class="blade-inline-3"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM5.2 5H2V3H0v2h2l3.6 7.59L4.25 15A2 2 0 0 0 6 18h14v-2H6.42a.25.25 0 0 1-.25-.25l.03-.12L7.1 14h9.45c.75 0 1.41-.41 1.75-1.03L21.7 6.5A1 1 0 0 0 20.83 5H5.2z"/></svg>
    <div class="blade-inline-4">Your cart is empty</div>
    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;justify-content:center;">
        <a href="/buyer/shop" class="btn btn-coral blade-inline-5">Start Shopping</a>
        <a href="{{ route('buyer.browse', ['deals' => 1]) }}" class="btn btn-outline">Browse Deals</a>
    </div>
</div>
@else
<div class="blade-inline-6">
    {{-- LEFT: cart table --}}
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
                                @if($item->product->primary_image)
                                    <img src="{{ Storage::url($item->product->primary_image) }}" class="blade-inline-10">
                                @else
                                    <div class="blade-inline-11"></div>
                                @endif
                                <div>
                                    <div class="blade-inline-12">{{ $item->product->name }}</div>
                                    @unless($item->product->isPurchasable())<div class="cart-unavailable" role="note">No longer available — remove it to check out</div>@endunless
                                    <div class="blade-inline-13">{{ $item->product->seller->business_name ?? $item->product->seller->full_name }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="blade-inline-14">{{ $item->variation ? $item->variation->type.': '.$item->variation->value : '—' }}</td>
                        <td class="blade-inline-15">₱{{ number_format($item->product->effective_price, 2) }}</td>
                        <td>
                            <div class="qty-stepper">
                                <button type="button" class="qty-btn qty-dec" data-item-id="{{ $item->id }}" aria-label="Decrease">−</button>
                                <input type="number"
                                       value="{{ $item->quantity }}"
                                       min="1"
                                       max="{{ $item->product->stock }}"
                                       data-item-id="{{ $item->id }}"
                                       class="qty-input">
                                <button type="button" class="qty-btn qty-inc" data-item-id="{{ $item->id }}" aria-label="Increase">+</button>
                            </div>
                        </td>
                        <td class="blade-inline-18" data-subtotal="{{ $item->product->effective_price * $item->quantity }}">₱{{ number_format($item->product->effective_price * $item->quantity, 2) }}</td>
                        <td>
                            <button type="button" class="cart-remove-btn remove-item-btn" data-item-id="{{ $item->id }}" title="Remove">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @if($items->hasPages())
            <div class="buyer-pagination dashboard-pagination">{{ $items->withQueryString()->links() }}</div>
        @endif
    </div>

    {{-- RIGHT: Order Summary first, then You May Also Like --}}
    <div>
        <div class="card cart-summary-card" id="cartSummaryCard">
            <div class="card-header"><span class="card-title">Order Summary</span></div>
            <div class="card-body">
                <div class="blade-inline-22">
                    <span class="blade-inline-23">Selected Items</span>
                    <span id="selectedCount">0</span>
                </div>
                <div class="blade-inline-24">
                    <span>Total</span>
                    <span class="blade-inline-25" id="totalAmount">₱0.00</span>
                </div>
                <form method="GET" action="/buyer/checkout" id="checkoutForm">
                    <div id="selectedItemsInputs"></div>
                    <button type="submit" class="btn btn-coral blade-inline-26" id="placeOrderBtn" disabled>
                        Proceed to Checkout
                    </button>
                </form>
            </div>
        </div>
        <div class="card cart-recommendations">
            <div class="card-header"><span class="card-title">You May Also Like</span></div>
            <div class="card-body">
                @foreach($recommendations as $recommendation)
                <a href="{{ route('buyer.product', $recommendation) }}" class="cart-recommendation">
                    @if($recommendation->primary_image)<img src="{{ Storage::url($recommendation->primary_image) }}" alt="{{ $recommendation->name }}">@endif
                    <span><strong>{{ $recommendation->name }}</strong><small>₱{{ number_format($recommendation->effective_price, 2) }}</small></span>
                </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

{{-- Recommended For You --}}
@if($recommendedProducts->isNotEmpty())
<section class="buyer-section cart-recommended-section">
    <div class="buyer-section-head">
        <h2 class="buyer-section-title">Recommended For You</h2>
        <a href="{{ route('buyer.recommended') }}" class="buyer-section-link">See all</a>
    </div>
    <div class="product-grid">
        @foreach($recommendedProducts as $product)
        <article class="product-card">
            <a href="{{ route('buyer.product', $product) }}" class="product-card-link">
                <div class="product-img-wrap">
                    @if($product->primary_image)
                        <img src="{{ Storage::url($product->primary_image) }}" alt="{{ $product->name }}">
                    @else
                        <div class="product-img-placeholder"><svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg></div>
                    @endif
                    @if($product->discount > 0)
                    <span class="product-discount-badge">-{{ $product->discount }}%</span>
                    @endif
                    <div class="product-hover-details">
                        <span class="product-hover-category">{{ $product->category ?? 'Product' }}</span>
                        <span class="product-hover-name">{{ $product->name }}</span>
                        <span class="product-hover-stock">{{ $product->stock }} available · View details</span>
                    </div>
                </div>
                <div class="product-info">
                    <div class="product-name">{{ $product->name }}</div>
                    <div>
                        <span class="product-price">₱{{ number_format($product->effective_price, 2) }}</span>
                        @if($product->discount > 0)
                        <span class="product-original">₱{{ number_format($product->price, 2) }}</span>
                        @endif
                    </div>
                    <div class="product-seller">{{ $product->seller->business_name ?? $product->seller->full_name }}</div>
                </div>
            </a>
            <div class="product-card-actions">
                <button type="button" class="card-add-btn" data-card-cart-action="/buyer/cart/{{ $product->id }}">Add to Cart</button>
                <button type="button" class="card-buy-btn" data-card-cart-action="/buyer/cart/{{ $product->id }}" data-card-buy-now="/buyer/checkout">Buy Now</button>
            </div>
        </article>
        @endforeach
    </div>
</section>
@endif
@endsection

@section('scripts')
@vite('resources/js/views/buyer-cart.js')
@endsection
