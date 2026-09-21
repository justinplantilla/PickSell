@extends('layouts.app')
@section('title', 'Shop')

@section('styles')
@vite('resources/css/views/pages-shop.css')
@endsection

@section('content')
<div class="shop-banner">
    <div>
        <div class="breadcrumb"><a href="/">Home</a> › Shop</div>
        <h1>All <span>Products</span></h1>
    </div>
</div>

<div class="shop-layout">
    <!-- Sidebar Filters -->
    <aside class="shop-sidebar">
        <div class="filter-box">
            <h3>Categories</h3>
            @php $cats = ['Electronics','Fashion','Home & Living','Sports','Beauty','Food & Grocery','Books','Toys']; @endphp
            <form method="GET" action="/shop">
            @foreach($cats as $cat)
            <div class="filter-item">
                <input type="checkbox" name="cat" id="cat-{{ $loop->index }}" value="{{ strtolower($cat) }}" {{ request('cat') === strtolower($cat) ? 'checked' : '' }}>
                <label for="cat-{{ $loop->index }}">{{ $cat }}</label>
            </div>
            @endforeach

        <div class="filter-box blade-inline-1">
            <h3>Price Range</h3>
            <div class="price-range">
                <input type="number" name="min" placeholder="Min" value="{{ request('min') }}">
                <span>–</span>
                <input type="number" name="max" placeholder="Max" value="{{ request('max') }}">
            </div>
        </div>
            <button type="submit" class="btn-filter">Apply Filters</button>
            </form>
        </div>
    </aside>

    <!-- Products -->
    <div class="shop-main">
        <div class="shop-toolbar">
            <p class="shop-count">Showing <strong>{{ count($products) }}</strong> products</p>
            <select class="sort-select">
                <option>Sort: Featured</option>
                <option>Price: Low to High</option>
                <option>Price: High to Low</option>
                <option>Newest First</option>
                <option>Best Rated</option>
            </select>
        </div>

        <div class="products-grid">
            @foreach($products as $p)
            <div class="product-card">
                @if($p->discount > 0)<div class="product-badge">-{{ $p->discount }}%</div>@endif
                <button class="product-wishlist">🤍</button>
                <div class="product-img">
                    <img src="{{ Storage::url($p->image) }}" alt="{{ $p->name }}" loading="lazy">
                    <div class="product-image-details">
                        <small>{{ $p->category }}</small>
                        <strong>{{ $p->name }}</strong>
                    </div>
                </div>
                <div class="product-info">
                    <div class="product-cat">{{ $p->category }}</div>
                    <div class="product-name">{{ $p->name }}</div>
                    <div class="product-rating">★★★★★</div>
                    <div class="product-prices">
                        <span class="product-price">₱{{ number_format($p->effective_price, 2) }}</span>
                        @if($p->discount > 0)<span class="product-old">₱{{ number_format($p->price, 2) }}</span><span class="product-discount">-{{ $p->discount }}%</span>@endif
                    </div>
                    <a href="/login" class="product-btn">View Product</a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
