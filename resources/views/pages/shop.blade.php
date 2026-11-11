@extends('layouts.app')
@section('title', 'Shop')

@section('styles')
@vite('resources/css/views/pages-shop.css')
@include('partials.pagination-styles')
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
            @php $cats = $categories; @endphp
            <form method="GET" action="/shop">
            @foreach($cats as $cat => $count)
            <div class="filter-item">
                <input type="checkbox" name="cat" id="cat-{{ $loop->index }}" value="{{ strtolower($cat) }}" {{ request('cat') === strtolower($cat) ? 'checked' : '' }}>
                <label for="cat-{{ $loop->index }}">{{ $cat }} <span class="category-count">({{ $count }})</span></label>
            </div>
            @endforeach

        <div class="filter-box shop-box">
            <h3>Price Range</h3>
            <div class="price-range">
                <input type="number" name="min" placeholder="Min" value="{{ request('min') }}">
                <span>–</span>
                <input type="number" name="max" placeholder="Max" value="{{ request('max') }}">
            </div>
            <button type="submit" class="btn-filter">Apply Filters</button>
        </div>
        </form>
    </aside>

    <!-- Products -->
    <div class="shop-main">
        <div class="shop-toolbar">
            <p class="shop-count">Showing <strong>{{ $products->firstItem() ?? 0 }} to {{ $products->lastItem() ?? 0 }}</strong> of {{ $products->total() }} products</p>
            <form method="GET" action="/shop">
                @foreach(request()->except('sort') as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
            <select class="sort-select" name="sort" data-submit-on-change>
                <option value="featured" {{ $sort === 'featured' ? 'selected' : '' }}>Sort: Featured</option>
                <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
            </select>
            </form>
        </div>

        <div class="products-grid">
            @foreach($products as $p)
            @php $productLink = auth()->check() ? '/buyer/product/' . $p->id : '/login'; @endphp
            <a href="{{ $productLink }}" class="product-card">
                @if($p->discount > 0)<div class="product-badge">-{{ $p->discount }}%</div>@endif
                <div class="product-img">
                    @if($p->primary_image)
                    <img src="{{ Storage::url($p->primary_image) }}" alt="{{ $p->name }}" loading="lazy">
                    @else
                    <span class="product-img-placeholder" aria-hidden="true">{{ strtoupper(mb_substr($p->name, 0, 1)) }}</span>
                    @endif
                    <div class="product-image-details">
                        <small>{{ $p->category }}</small>
                        <strong>{{ $p->name }}</strong>
                    </div>
                </div>
                <div class="product-info">
                    <div class="product-cat">{{ $p->category }}</div>
                    <div class="product-name">{{ $p->name }}</div>
                    <div class="product-rating">@if($p->reviews_count)<x-star-rating :value="$p->reviews_avg_rating" /> <small>{{ number_format($p->reviews_avg_rating, 1) }} ({{ $p->reviews_count }})</small>@else<small>No reviews yet</small>@endif</div>
                    <div class="product-prices">
                        <span class="product-price">₱{{ number_format($p->effective_price, 2) }}</span>
                        @if($p->discount > 0)<span class="product-old">₱{{ number_format($p->price, 2) }}</span><span class="product-discount">-{{ $p->discount }}%</span>@endif
                    </div>
                </div>
            </a>
            @endforeach
        </div>
        @if($products->hasPages())
            <div class="shop-pagination dashboard-pagination">{{ $products->withQueryString()->links() }}</div>
        @endif
    </div>
</div>
@endsection
