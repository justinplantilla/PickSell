@extends('buyer.layout')

@section('styles')
@vite('resources/css/views/buyer-home.css')
@endsection

@section('title', 'Browse Categories')

@section('content')
<main class="buyer-category-browser">
    <aside class="buyer-category-sidebar" aria-label="Product categories">
        <h1>Categories</h1>
        <nav>
            <a href="{{ route('buyer.categories') }}" class="{{ !$category ? 'is-active' : '' }}">
                <span>All products</span>
                <span>{{ $categoryCounts->sum() }}</span>
            </a>
            @foreach($categories as $categoryName)
            <a href="{{ route('buyer.categories', ['category' => $categoryName]) }}" class="{{ $category === $categoryName ? 'is-active' : '' }}" @if($category === $categoryName) aria-current="page" @endif>
                <span>{{ $categoryName }}</span>
                <span>{{ $categoryCounts[$categoryName] ?? 0 }}</span>
            </a>
            @endforeach
        </nav>
    </aside>

    <section class="buyer-category-results" aria-labelledby="category-results-title">
        <div class="buyer-section-head">
            <div>
                <h2 class="buyer-section-title" id="category-results-title">{{ $category ?: 'All Products' }}</h2>
                <p>{{ $products->total() }} products to explore</p>
            </div>
            @if($category)
            <a class="buyer-section-link" href="{{ route('buyer.categories') }}">Clear category</a>
            @endif
        </div>

        @if($products->isEmpty())
        <div class="buyer-category-empty">
            <strong>No products in this category yet.</strong>
            <a href="{{ route('buyer.categories') }}">Browse all products</a>
        </div>
        @else
        <div class="category-browser-products">
            <div class="product-grid">
                @foreach($products as $product)
                <article class="product-card">
                    <a href="{{ route('buyer.product', $product) }}" class="product-card-link">
                        <div class="product-img-wrap">
                            @if($product->primary_image)
                                <img src="{{ Storage::url($product->primary_image) }}" alt="{{ $product->name }}">
                            @else
                                <div class="product-img-placeholder" aria-hidden="true">✦</div>
                            @endif
                            @if($product->discount > 0)
                            <span class="product-discount-badge">-{{ $product->discount }}%</span>
                            @endif
                        </div>
                        <div class="product-info">
                            <div class="product-name">{{ $product->name }}</div>
                            <span class="product-price">₱{{ number_format($product->effective_price, 2) }}</span>
                            <div class="product-seller">{{ $product->seller->business_name ?? $product->seller->full_name }}</div>
                        </div>
                    </a>
                    <div class="product-card-actions">
                        <button type="button" class="card-add-btn" data-card-cart-action="{{ route('buyer.cart.add', $product) }}">Add to Cart</button>
                        <button type="button" class="card-buy-btn" data-card-cart-action="{{ route('buyer.cart.add', $product) }}" data-card-buy-now="{{ route('buyer.checkout.page') }}">Buy Now</button>
                    </div>
                </article>
                @endforeach
            </div>
            <div class="buyer-pagination dashboard-pagination">{{ $products->links() }}</div>
        </div>
        @endif
    </section>
</main>
@endsection
