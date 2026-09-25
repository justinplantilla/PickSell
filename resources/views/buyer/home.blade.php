@extends('buyer.layout')
@section('styles')
@vite('resources/css/views/buyer-home.css')
@endsection
@section('title', 'Shop')

@section('catbar')
<div class="cat-bar">
    <a href="/buyer/shop" class="cat-pill {{ !$category && !$deals ? 'active' : '' }}">All</a>
    @foreach($categories as $cat)
    <a href="/buyer/shop?category={{ urlencode($cat) }}{{ $search ? '&search='.urlencode($search) : '' }}" class="cat-pill {{ $category === $cat ? 'active' : '' }}">{{ $cat }}</a>
    @endforeach
</div>
@endsection

@section('content')
<div class="buyer-home">
    <section class="buyer-hero">
        <div class="buyer-hero-copy">
            <span class="buyer-kicker">Limited time offer</span>
            <h1>Upgrade Your<br> Lifestyle Up to 70% OFF</h1>
            <p>Shop trusted finds from local sellers and discover something new for less.</p>
            <a href="{{ route('buyer.deals') }}" class="btn">Shop deals</a>
        </div>
    </section>

    <section class="buyer-section">
        <div class="buyer-section-head">
            <h2 class="buyer-section-title">Browse Categories</h2>
            <a href="{{ route('buyer.home') }}" class="buyer-section-link">View all categories</a>
        </div>
        <div class="buyer-categories">
            @php
                $categoryIcons = [
                    'Beauty' => 'M12 3a9 9 0 1 0 9 9A9 9 0 0 0 12 3Zm0 2a7 7 0 1 1-7 7 7 7 0 0 1 7-7Zm0 2a5 5 0 1 0 5 5 5 5 0 0 0-5-5Z',
                    'Books' => 'M5 4h11a3 3 0 0 1 3 3v13H7a2 2 0 0 1-2-2Zm2 2v12h10V7a1 1 0 0 0-1-1Zm2 2h6v2H9Zm0 4h6v2H9Z',
                    'Clothing' => 'M8 4h8l2 3 3 2-2 4-2-1v8H7v-8l-2 1-2-4 3-2Zm1.2 2L8 8.5l-2.4 1.6.8 1.5 2.6-1.3V18h6v-7.7l2.6 1.3.8-1.5L16 8.5 14.8 6Z',
                    'Electronics' => 'M4 5h16v14H4Zm2 2v10h12V7Zm3 3h6v4H9Z',
                    'Fashion' => 'M7 4h10l3 4-3 2v9H7v-9L4 8Zm1.2 2L6.5 8.5 9 10v7h6v-7l2.5-1.5L15.8 6Z',
                    'Food & Grocery' => 'M5 4h14v16H5Zm2 2v12h10V6Zm2 2h6v2H9Zm0 4h6v2H9Z',
                    'Home & Living' => 'M3 11 12 4l9 7v9h-6v-6H9v6H3Zm3 1v6h1v-6h10v6h1v-7l-6-4.7Z',
                    'Pet Supplies' => 'M7 10a2 2 0 1 0-2-2 2 2 0 0 0 2 2Zm10 0a2 2 0 1 0-2-2 2 2 0 0 0 2 2Zm-8-3a2 2 0 1 0-2-2 2 2 0 0 0 2 2Zm6 0a2 2 0 1 0-2-2 2 2 0 0 0 2 2Zm-3 3c-3 0-5 2-5 4 0 2 1 3 3 3 1 0 1.5-.5 2-.5s1 .5 2 .5c2 0 3-1 3-3 0-2-2-4-5-4Z',
                    'Sports' => 'M12 3a9 9 0 1 0 9 9 9 9 0 0 0-9-9Zm0 2a7 7 0 0 1 6.7 5H14V7h-4v3H5.3A7 7 0 0 1 12 5ZM5.3 12H8v3h4v4a7 7 0 0 1-6.7-7ZM14 19v-4h4v-3h.7A7 7 0 0 1 14 19Z',
                    'Toys' => 'M4 5h16v14H4Zm2 2v10h12V7Zm2 2h2v2H8Zm4 0h4v2h-4Zm-4 4h8v2H8Z',
                ];
            @endphp
            @foreach($categories as $cat)
            <a class="buyer-category" href="{{ route('buyer.home', ['category' => $cat]) }}">
                <span class="buyer-category-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="{{ $categoryIcons[$cat] ?? 'M4 4h16v16H4Z' }}" fill="currentColor"/></svg></span>
                <span>{{ $cat }}</span>
            </a>
            @endforeach
            @if($categories->isEmpty())
                <span class="buyer-category">No categories yet</span>
            @endif
        </div>
    </section>

    <div class="buyer-sale-strip">
        <span><strong>FLASH SALE</strong> &nbsp; Fresh finds at prices worth grabbing.</span>
        <a href="{{ route('buyer.deals') }}" class="btn">Shop all deals</a>
    </div>

    <section class="buyer-section" id="deals">
        <div class="buyer-section-head">
            <h2 class="buyer-section-title">{{ $search || $category ? 'Search Results' : ($deals ? 'Deals & Promos' : 'Deals of the Day') }}</h2>
            @if(!$search && !$category && !$deals)
            <a href="{{ route('buyer.deals') }}" class="buyer-section-link">See all deals</a>
            @endif
        </div>
@if($search || $category || $deals)
<div class="blade-inline-1">
    Showing results
    @if($search) for "<strong>{{ $search }}</strong>"@endif
    @if($category) in <strong>{{ $category }}</strong>@endif
    — {{ $products->total() }} product(s) found
    <a href="/buyer/shop" class="blade-inline-2">Clear</a>
</div>
@endif

@if($products->isEmpty())
<div class="blade-inline-3">
    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" viewBox="0 0 24 24" class="blade-inline-4"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM5.2 5H2V3H0v2h2l3.6 7.59L4.25 15A2 2 0 0 0 6 18h14v-2H6.42a.25.25 0 0 1-.25-.25l.03-.12L7.1 14h9.45c.75 0 1.41-.41 1.75-1.03L21.7 6.5A1 1 0 0 0 20.83 5H5.2z"/></svg>
    <div>No products found.</div>
</div>
@else
<div class="product-grid">
    @foreach($products as $product)
    <article class="product-card">
        <a href="/buyer/product/{{ $product->id }}" class="product-card-link">
        <div class="product-img-wrap">
            @if($product->image)
                <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}">
            @else
                <div class="product-img-placeholder">
                    <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                </div>
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
<div class="buyer-pagination dashboard-pagination">{{ $products->withQueryString()->links() }}</div>
@endif
    </section>

    @if(!$search && !$category && !$deals)
    <section class="buyer-section">
        <div class="buyer-section-head">
            <h2 class="buyer-section-title">Recommended For You</h2>
            <a href="{{ route('buyer.home') }}" class="buyer-section-link">View more</a>
        </div>
        <div class="product-grid">
            @foreach($recommendedProducts as $product)
            <article class="product-card">
                <a href="{{ route('buyer.product', $product) }}" class="product-card-link">
                <div class="product-img-wrap">
                    @if($product->image)
                        <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}">
                    @else
                        <div class="product-img-placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 2-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-2 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                        </div>
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
                    <span class="product-price">₱{{ number_format($product->effective_price, 2) }}</span>
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
</div>
@endsection
