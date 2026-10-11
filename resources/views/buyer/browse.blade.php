@extends('buyer.layout')
@section('styles')
@vite('resources/css/views/buyer-browse.css')
@endsection
@section('title', 'Browse Products')

@section('catbar')
<div class="cat-bar">
    <a href="{{ route('buyer.browse') }}" class="cat-pill {{ !$category && !$dealsOnly ? 'active' : '' }}">All</a>
    <a href="{{ route('buyer.browse', ['deals' => 1]) }}" class="cat-pill {{ $dealsOnly && !$category ? 'active' : '' }}">🔥 Deals</a>
    @foreach($categories as $cat)
    <a href="{{ route('buyer.browse', ['category' => $cat] + ($dealsOnly ? ['deals' => 1] : [])) }}" class="cat-pill {{ $category === $cat ? 'active' : '' }}">{{ $cat }}</a>
    @endforeach
</div>
@endsection

@section('content')
<div class="browse-page">
    <div class="browse-toolbar">
        <div class="browse-toolbar-left">
            <h1 class="browse-title">
                @if($dealsOnly) 🔥 Deals & Promos
                @elseif($category) {{ $category }}
                @elseif($search) Results for "{{ $search }}"
                @else All Products
                @endif
            </h1>
            <span class="browse-count">{{ $products->total() }} product{{ $products->total() !== 1 ? 's' : '' }}</span>
        </div>
        <form class="browse-sort-form" method="GET" action="{{ route('buyer.browse') }}">
            @if($search) <input type="hidden" name="search" value="{{ $search }}"> @endif
            @if($category) <input type="hidden" name="category" value="{{ $category }}"> @endif
            @if($dealsOnly) <input type="hidden" name="deals" value="1"> @endif
            <select name="sort" class="browse-sort-select" onchange="this.form.submit()">
                <option value="latest" {{ $sort === 'latest' ? 'selected' : '' }}>Latest</option>
                <option value="popular" {{ $sort === 'popular' ? 'selected' : '' }}>Most Popular</option>
                <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
            </select>
        </form>
    </div>

    @if($products->isEmpty())
    <div class="browse-empty">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM5.2 5H2V3H0v2h2l3.6 7.59L4.25 15A2 2 0 0 0 6 18h14v-2H6.42a.25.25 0 0 1-.25-.25l.03-.12L7.1 14h9.45c.75 0 1.41-.41 1.75-1.03L21.7 6.5A1 1 0 0 0 20.83 5H5.2z"/></svg>
        <p>No products found.</p>
        <a href="{{ route('buyer.browse') }}" class="btn btn-coral btn-sm">Clear filters</a>
    </div>
    @else
    <div class="product-grid browse-grid">
        @foreach($products as $product)
        <article class="product-card">
            <a href="{{ route('buyer.product', $product) }}" class="product-card-link">
            <div class="product-img-wrap">
                @if($product->primary_image)
                    <img src="{{ Storage::url($product->primary_image) }}" alt="{{ $product->name }}">
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
</div>
@endsection

@section('scripts')
@vite('resources/js/views/buyer-layout.js')
@endsection
