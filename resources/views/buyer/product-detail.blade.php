@extends('buyer.layout')
@section('title', $product->name)

@section('styles')
@vite('resources/css/views/buyer-product-detail.css')
@endsection

@section('content')
<div class="product-breadcrumb"><a href="/buyer/shop">Shop</a><span>/</span><a href="/buyer/shop?category={{ urlencode($product->category ?? '') }}">{{ $product->category ?? 'Products' }}</a><span>/</span><strong>{{ $product->name }}</strong></div>

<div class="product-detail">
    <section class="product-gallery" aria-label="Product images">
        <div class="product-detail-img">
            @if($product->image)
                <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}">
            @else
                <svg class="product-image-fallback" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4Zm2 2v10h12V7Zm2 2h2v2H8Zm4 0h4v2h-4Z" fill="currentColor"/></svg>
            @endif
            @if($product->discount > 0)<span class="product-hero-badge">-{{ $product->discount }}%</span>@endif
        </div>
        <div class="product-thumbnails" aria-label="Product thumbnails">
            @for($thumbnail = 0; $thumbnail < 4; $thumbnail++)
                <div class="product-thumbnail {{ $thumbnail === 0 ? 'is-active' : '' }}">@if($product->image)<img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }} thumbnail">@endif</div>
            @endfor
        </div>
        <div class="product-assurance"><span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 3v5c0 5-3.4 8.8-8 10-4.6-1.2-8-5-8-10V6Z" fill="none" stroke="currentColor" stroke-width="1.7"/></svg> Buyer protection</span><span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11v10H3zM14 10h4l3 3v3h-7zM7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" fill="none" stroke="currentColor" stroke-width="1.6"/></svg> Tracked delivery</span></div>
    </section>

    <section class="product-detail-info">
        <div class="product-heading">
            <span class="product-category-label">{{ $product->category ?? 'Product' }}</span>
            <h1>{{ $product->name }}</h1>
            <div class="product-seller-line">Sold by <a href="{{ route('buyer.seller', $product->seller) }}"><strong>{{ $product->seller->business_name ?? $product->seller->full_name }}</strong></a><span class="seller-verified">Verified seller</span></div>
            <a href="#reviews" class="product-rating-link"><span aria-hidden="true">★★★★★</span> {{ $reviewAverage ?: 'New' }} · {{ $reviewCount }} {{ $reviewCount === 1 ? 'review' : 'reviews' }}</a>
        </div>

        <div class="product-price-block"><span class="detail-price">₱{{ number_format($product->effective_price, 2) }}</span>@if($product->discount > 0)<span class="detail-original">₱{{ number_format($product->price, 2) }}</span><span class="detail-discount">-{{ $product->discount }}%</span>@endif</div>
        @if($product->description)<p class="product-description">{{ $product->description }}</p>@endif

        <form method="POST" action="/buyer/cart/{{ $product->id }}" id="addCartForm">
            @csrf
            @php $grouped = $product->variations->groupBy('type'); @endphp
            @foreach($grouped as $type => $options)
            <div class="variation-group">
                <div class="variation-label">{{ ucfirst($type) }} <span id="selected_{{ Str::slug($type) }}">—</span></div>
                <div class="variation-options">@foreach($options as $opt)<button type="button" class="var-btn" data-type="{{ $type }}" data-id="{{ $opt->id }}" data-value="{{ $opt->value }}" onclick="selectVariation(this, '{{ Str::slug($type) }}')">{{ $opt->value }}</button>@endforeach</div>
            </div>
            @endforeach
            <input type="hidden" name="variation_id" id="variation_id">
            <div class="purchase-row"><div><div class="variation-label">Quantity</div><div class="qty-control"><button type="button" class="qty-btn" onclick="changeQty(-1)" aria-label="Decrease quantity">-</button><input type="number" name="quantity" id="qtyInput" class="qty-input" value="1" min="1" max="{{ $product->stock }}" aria-label="Quantity"><button type="button" class="qty-btn" onclick="changeQty(1)" aria-label="Increase quantity">+</button></div><small>{{ $product->stock }} available</small></div></div>
            @if($product->voucher_code)<div class="product-voucher">Use voucher <strong>{{ $product->voucher_code }}</strong> at checkout for {{ $product->voucher_discount }}% off</div>@endif
            <div class="product-actions"><button type="button" id="addToCartBtn" class="btn btn-outline"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 6h16l-2 9H7Zm0 0L4 3H2m6 17a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm9 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg> Add to Cart</button><button type="button" id="buyNowBtn" class="btn btn-coral">Buy Now</button><a href="/buyer/chat?user={{ $product->seller_id }}&product={{ $product->id }}" class="btn btn-outline">Chat Seller</a></div>
        </form>
        <div class="seller-panel"><div class="seller-avatar">{{ strtoupper(substr($product->seller->first_name, 0, 1)) }}</div><div><strong>{{ $product->seller->business_name ?? $product->seller->full_name }}</strong><span>Trusted PickSell seller</span></div><a href="{{ route('buyer.seller', $product->seller) }}">Visit store</a><a href="/buyer/chat?user={{ $product->seller_id }}&product={{ $product->id }}">Message</a></div>
    </section>
</div>

<section class="product-information-grid"><article><span class="detail-eyebrow">Product details</span><h2>Made for everyday use.</h2><p>{{ $product->description ?: 'A carefully selected product from a verified local seller. Review the details above and message the seller if you need help before ordering.' }}</p></article><div class="detail-list"><div><strong>Category</strong><span>{{ $product->category ?? 'Product' }}</span></div><div><strong>Availability</strong><span>{{ $product->stock }} in stock</span></div><div><strong>Delivery</strong><span>Tracked delivery available</span></div></div></section>

<section class="product-reviews" id="reviews"><div class="reviews-header"><div><span class="detail-eyebrow">Customer feedback</span><h2>Product reviews</h2></div><div class="review-summary"><strong>{{ $reviewAverage ?: '—' }}</strong><span>{{ $reviewCount }} {{ $reviewCount === 1 ? 'review' : 'reviews' }}</span></div></div>@if($product->reviews->isEmpty())<div class="reviews-empty"><svg class="reviews-empty-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-2.9-5.6 2.9 1.1-6.2L3 9.6l6.2-.9Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg><strong>No reviews yet</strong><span>Completed buyers will be able to share their experience here.</span></div>@else<div class="review-list">@foreach($product->reviews as $review)<article class="review-item"><div class="review-item-head"><strong>{{ $review->buyer->first_name ?? 'Buyer' }}</strong><span>{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span></div>@if($review->body)<p>{{ $review->body }}</p>@endif<small>{{ $review->created_at->format('M d, Y') }}</small></article>@endforeach</div>@endif</section>

@php $recommendations = $product->seller->products->where('id', '!=', $product->id)->where('status', 'active')->take(5); @endphp
@if($recommendations->isNotEmpty())<section class="related-products"><div class="related-heading"><div><span class="detail-eyebrow">Keep exploring</span><h2>You might also like</h2></div><a href="/buyer/shop?category={{ urlencode($product->category ?? '') }}">View more</a></div><div class="related-grid">@foreach($recommendations as $recommendation)<a href="{{ route('buyer.product', $recommendation) }}" class="related-card">@if($recommendation->image)<img src="{{ Storage::url($recommendation->image) }}" alt="{{ $recommendation->name }}">@endif<div><strong>{{ $recommendation->name }}</strong><span>₱{{ number_format($recommendation->effective_price, 2) }}</span></div></a>@endforeach</div></section>@endif
@endsection

@section('scripts')
@vite('resources/js/views/buyer-product-detail.js')
@endsection
