@extends('buyer.layout')
@section('title', ($seller->business_name ?? $seller->full_name) . ' Store')

@section('styles')
@vite('resources/css/views/buyer-home.css')
@vite('resources/css/views/buyer-storefront.css')
@endsection

@section('content')
<div class="seller-storefront">
    <a href="/buyer/shop" class="storefront-back">← Back to shop</a>
    @if($seller->store_banner_url)
    <div class="seller-storefront-banner" style="background-image:url('{{ $seller->store_banner_url }}')" role="img" aria-label="{{ $seller->business_name ?? $seller->full_name }} banner"></div>
    @endif
    <section class="seller-storefront-hero {{ $seller->store_banner_url ? 'has-banner' : '' }}">
        <div class="storefront-profile">
        <div class="seller-storefront-avatar">@if($seller->store_logo_url)<img src="{{ $seller->store_logo_url }}" alt="{{ $seller->business_name ?? $seller->full_name }} logo">@else{{ strtoupper(substr($seller->business_name ?: $seller->first_name, 0, 1)) }}@endif</div>
        <div class="seller-storefront-copy">
            <span class="seller-storefront-kicker">PickSell seller store</span>
            <h1>{{ $seller->business_name ?? $seller->full_name }}</h1>
            <p>{{ $seller->store_description ?: "Browse this seller's available products and find something made for you." }}</p>
            <span class="seller-storefront-status"><x-store-icon name="check" /> Approved PickSell seller</span>
            @if($storeStats['preferred'])<span class="seller-storefront-status"><x-store-icon name="star" /> Preferred Seller</span>@endif
            @if($seller->municipality || $seller->province)<span class="storefront-location"><x-store-icon name="pin" /> {{ collect([$seller->municipality, $seller->province])->filter()->join(', ') }}</span>@endif
            <div class="seller-storefront-actions">
                <x-follow-seller-button :seller="$seller" />
                <a href="/buyer/chat?user={{ $seller->id }}" class="btn btn-outline btn-sm"><x-store-icon name="chat" /> Message</a>
            </div>
            @if($seller->isOnVacation())<div class="alert alert-warning" role="note">On vacation until {{ $seller->vacation_ends_at?->format('M j') }}: browse freely, but orders are paused until the seller is back.</div>@endif
            @if($seller->isSellingHeld())<div class="alert alert-warning" role="note">This shop is not taking orders right now. You can still browse its products.</div>@endif
        </div>
        </div>
        <div class="storefront-standing">
            <h2>Store at a glance</h2>
            <dl class="storefront-metrics">
                <div><dt><x-store-icon name="star" /> Seller rating</dt><dd>{{ $storeStats['rating']['count'] ? number_format($storeStats['rating']['average'], 1).' / 5' : 'No ratings yet' }}<small>{{ number_format($storeStats['rating']['count']) }} buyer ratings</small></dd></div>
                <div><dt><x-store-icon name="people" /> Followers</dt><dd>{{ number_format($storeStats['followers']) }}<small>Following this store</small></dd></div>
                <div><dt><x-store-icon name="calendar" /> Joined PickSell</dt><dd>{{ $seller->created_at?->format('M Y') ?? 'Not available' }}</dd></div>
                <div><dt><x-store-icon name="box" /> Products</dt><dd>{{ number_format($storeStats['products']) }}<small>Active listings</small></dd></div>
                <div><dt><x-store-icon name="check" /> Completed orders</dt><dd>{{ number_format($storeStats['completed']) }}</dd></div>
                <div><dt><x-store-icon name="chat" /> Replies within 24h</dt><dd>{{ $storeStats['chat']['rate'] !== null ? $storeStats['chat']['rate'].'%' : 'Not enough data' }}<small>Last 30 days · auto-replies excluded</small></dd></div>
            </dl>
            <p class="storefront-trust-note"><x-store-icon name="check" /> Seller account approved by PickSell. Ratings and activity come from platform records.</p>
        </div>
    </section>

    <section class="seller-storefront-products">
        @if($search !== '' || request()->filled('category'))
        <p class="storefront-search-summary">Results in this store for <strong>{{ $search ?: request('category') }}</strong> · <a href="{{ route('buyer.seller', $seller) }}">Clear search</a></p>
        @endif
        <div class="seller-storefront-heading"><div><span class="detail-eyebrow">Seller catalog</span><h2>Available products</h2></div><span class="seller-storefront-range">{{ $products->firstItem() ?? 0 }}-{{ $products->lastItem() ?? 0 }} of {{ $products->total() }}</span></div>
        @if($products->isEmpty())
            <div class="storefront-empty"><strong>{{ $search !== '' || request()->filled('category') ? 'No matching products in this store' : 'No products available yet' }}</strong><span>{{ $search !== '' || request()->filled('category') ? 'Try another search or choose All PickSell to search other stores.' : 'Check back soon for new listings from this seller.' }}</span><a href="/buyer/shop" class="btn btn-coral">Continue shopping</a></div>
        @else
            <div class="product-grid">
                @foreach($products as $product)
                <article class="product-card">
                    <a href="{{ route('buyer.product', $product) }}" class="product-card-link">
                        <div class="product-img-wrap">
                            @if($product->primary_image)<img src="{{ Storage::url($product->primary_image) }}" alt="{{ $product->name }}">@else<div class="product-img-placeholder"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4Zm2 2v10h12V7Zm2 2h2v2H8Zm4 0h4v2h-4Z" fill="currentColor"/></svg></div>@endif
                            @if($product->discount > 0)<span class="product-discount-badge">-{{ $product->discount }}%</span>@endif
                        </div>
                        <div class="product-info"><div class="product-name">{{ $product->name }}</div><span class="product-price">₱{{ number_format($product->effective_price, 2) }}</span><div class="product-seller">{{ $seller->business_name ?? $seller->full_name }}</div></div>
                    </a>
                    <div class="product-card-actions">@if($seller->isOnVacation())<span class="card-vacation-note">Seller on vacation</span>@else<button type="button" class="card-add-btn" data-card-cart-action="/buyer/cart/{{ $product->id }}">Add to Cart</button><button type="button" class="card-buy-btn" data-card-cart-action="/buyer/cart/{{ $product->id }}" data-card-buy-now="/buyer/checkout">Buy Now</button>@endif</div>
                </article>
                @endforeach
            </div>
            @if($products->hasPages())<div class="buyer-pagination dashboard-pagination">{{ $products->withQueryString()->links() }}</div>@endif
        @endif
    </section>
</div>
@endsection
