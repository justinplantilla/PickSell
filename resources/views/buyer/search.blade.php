@extends('buyer.layout')
@section('title', $query ? 'Results for "' . $query . '"' : 'Search')
@section('styles')
@vite('resources/css/views/buyer-home.css')
<style>
    .search-results-header {
        background: #fff;
        border: 1px solid #e8e2d8;
        border-radius: 12px;
        padding: 1.25rem 1.4rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 4px 14px rgba(31, 35, 40, 0.04);
    }
    .search-results-header h1 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--charcoal);
    }
    .search-results-header h1 span {
        color: var(--coral);
    }
    .search-results-meta {
        font-size: 0.82rem;
        color: #888;
        margin-top: 0.4rem;
    }
    .search-results-meta a {
        color: var(--coral);
        font-weight: 600;
        margin-left: 0.5rem;
    }
    @media (max-width: 560px) {
        .search-results-header { padding: 1rem; }
    }
    .search-empty {
        text-align: center;
        padding: 5rem 1rem;
        color: #aaa;
    }
    .search-empty svg { margin-bottom: 1rem; opacity: 0.25; }
    .search-empty p { font-size: 1rem; font-weight: 600; color: #666; margin-bottom: 0.4rem; }
    .search-empty small { font-size: 0.82rem; }
    .search-back {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.82rem;
        color: var(--coral);
        font-weight: 600;
        margin-bottom: 1rem;
    }
    .search-back:hover { text-decoration: underline; }
</style>
@endsection

@section('content')
<a href="/buyer/shop" class="search-back">
    ← Back to Shop
</a>

<div class="search-results-header">
    @if($query)
        <h1>Results for "<span>{{ $query }}</span>"</h1>
        <div class="search-results-meta">
            {{ $products->total() }} product{{ $products->total() !== 1 ? 's' : '' }} found
            @if($category) in <strong>{{ $category }}</strong>@endif
            <a href="/buyer/search?search={{ urlencode($query) }}">Clear filters</a>
        </div>
    @else
        <h1>All Products</h1>
        <div class="search-results-meta">{{ $products->total() }} products available</div>
    @endif
</div>

@if($products->isEmpty())
    <div class="search-empty">
        <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" fill="currentColor" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
        <p>No products found for "{{ $query }}"</p>
        <small>Try a different keyword or browse our categories.</small>
        <br><br>
        <a href="/buyer/shop" class="btn btn-coral">Browse All Products</a>
    </div>
@else
    <div class="product-grid buyer-home">
        @foreach($products as $product)
        <a href="/buyer/product/{{ $product->id }}" class="product-card">
            <div class="product-img-wrap">
                @if($product->primary_image ?? $product->image)
                    <img src="{{ Storage::url($product->primary_image ?? $product->image) }}" alt="{{ $product->name }}">
                @else
                    <div class="product-img-placeholder">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                    </div>
                @endif
                @if($product->discount > 0)
                <span class="product-discount-badge">-{{ $product->discount }}%</span>
                @endif
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
@endsection
