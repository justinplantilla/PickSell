@extends('buyer.layout')
@section('title', ($seller->business_name ?? $seller->full_name) . ' Store')

@section('styles')
@vite('resources/css/views/buyer-home.css')
@vite('resources/css/views/buyer-storefront.css')
@endsection

@section('content')
<div class="seller-storefront">
    <a href="/buyer/shop" class="storefront-back">← Back to shop</a>
    <section class="seller-storefront-hero">
        <div class="seller-storefront-avatar">{{ strtoupper(substr($seller->first_name, 0, 1)) }}</div>
        <div class="seller-storefront-copy">
            <span class="seller-storefront-kicker">PickSell seller store</span>
            <h1>{{ $seller->business_name ?? $seller->full_name }}</h1>
            <p>Browse this seller's available products and find something made for you.</p>
            <span class="seller-storefront-status"><i></i> Verified seller</span>
        </div>
        <div class="seller-storefront-count"><strong>{{ $products->total() }}</strong><span>products</span></div>
    </section>

    <section class="seller-storefront-products">
        <div class="seller-storefront-heading"><div><span class="detail-eyebrow">Seller catalog</span><h2>Available products</h2></div><span class="seller-storefront-range">{{ $products->firstItem() ?? 0 }}-{{ $products->lastItem() ?? 0 }} of {{ $products->total() }}</span></div>
        @if($products->isEmpty())
            <div class="storefront-empty"><strong>No products available yet</strong><span>Check back soon for new listings from this seller.</span><a href="/buyer/shop" class="btn btn-coral">Continue shopping</a></div>
        @else
            <div class="product-grid">
                @foreach($products as $product)
                <article class="product-card">
                    <a href="{{ route('buyer.product', $product) }}" class="product-card-link">
                        <div class="product-img-wrap">
                            @if($product->image)<img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}">@else<div class="product-img-placeholder"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4Zm2 2v10h12V7Zm2 2h2v2H8Zm4 0h4v2h-4Z" fill="currentColor"/></svg></div>@endif
                            @if($product->discount > 0)<span class="product-discount-badge">-{{ $product->discount }}%</span>@endif
                        </div>
                        <div class="product-info"><div class="product-name">{{ $product->name }}</div><span class="product-price">₱{{ number_format($product->effective_price, 2) }}</span><div class="product-seller">{{ $seller->business_name ?? $seller->full_name }}</div></div>
                    </a>
                    <div class="product-card-actions"><button type="button" class="card-add-btn" data-card-cart-action="/buyer/cart/{{ $product->id }}">Add to Cart</button><button type="button" class="card-buy-btn" data-card-cart-action="/buyer/cart/{{ $product->id }}" data-card-buy-now="/buyer/checkout">Buy Now</button></div>
                </article>
                @endforeach
            </div>
            @if($products->hasPages())<div class="buyer-pagination dashboard-pagination">{{ $products->withQueryString()->links() }}</div>@endif
        @endif
    </section>
</div>
@endsection
