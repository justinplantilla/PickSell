@foreach($products as $product)
<article class="product-card">
    <a href="{{ route('buyer.product', $product) }}" class="product-card-link">
        <div class="product-img-wrap">
            @if($product->primary_image)
                <img src="{{ Storage::url($product->primary_image) }}" alt="{{ $product->name }}">
            @else
                <div class="product-img-placeholder"><svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg></div>
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
