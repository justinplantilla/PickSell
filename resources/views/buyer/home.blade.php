@extends('buyer.layout')
@section('styles')
@vite('resources/css/views/buyer-home.css')
@endsection
@section('title', 'Shop')

@section('catbar')
<div class="cat-bar">
    <a href="/buyer/shop" class="cat-pill {{ !$category ? 'active' : '' }}">All</a>
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
            <a href="#deals" class="btn">Shop deals</a>
        </div>
    </section>

    <section class="buyer-section">
        <div class="buyer-section-head">
            <h2 class="buyer-section-title">Browse Categories</h2>
            <a href="{{ route('buyer.home') }}" class="buyer-section-link">View all categories</a>
        </div>
        <div class="buyer-categories">
            @php
                $categoryIcons = ['Electronics' => '&#9889;', 'Fashion' => '&#9733;', 'Beauty' => '&#10024;', 'Home & Living' => '&#8962;', 'Groceries' => '&#9825;', 'Sports' => '&#9673;'];
            @endphp
            @foreach($categories->take(6) as $cat)
            <a class="buyer-category" href="{{ route('buyer.home', ['category' => $cat]) }}">
                <span class="buyer-category-icon">{!! $categoryIcons[$cat] ?? '&#9670;' !!}</span>
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
        <a href="#deals" class="btn">Shop all deals</a>
    </div>

    <section class="buyer-section" id="deals">
        <div class="buyer-section-head">
            <h2 class="buyer-section-title">Deals of the Day</h2>
            <a href="{{ route('buyer.home') }}" class="buyer-section-link">See all deals</a>
        </div>
@if($search || $category)
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
    <a href="/buyer/product/{{ $product->id }}" class="product-card">
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
    @endforeach
</div>
<div class="buyer-pagination dashboard-pagination">{{ $products->withQueryString()->links() }}</div>
@endif
    </section>

    <section class="buyer-section">
        <div class="buyer-section-head">
            <h2 class="buyer-section-title">Recommended For You</h2>
            <a href="{{ route('buyer.home') }}" class="buyer-section-link">View more</a>
        </div>
        <div class="product-grid">
            @foreach($recommendedProducts as $product)
            <a href="{{ route('buyer.product', $product) }}" class="product-card">
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
            @endforeach
        </div>
    </section>
</div>
@endsection
