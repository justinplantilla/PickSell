@extends('buyer.layout')
@section('title', $product->name)

@section('styles')
@vite('resources/css/views/buyer-product-detail.css')
@endsection

@section('content')
<div class="blade-inline-1">
    <a href="/buyer/shop" class="blade-inline-2">Shop</a> / {{ $product->category ?? 'Products' }} / {{ $product->name }}
</div>

<div class="product-detail">
    <div class="product-detail-img">
        @if($product->image)
            <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}">
        @else
            <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" fill="#ccc" viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.36C18 2.06 15.94 0 13.36 0c-1.3 0-2.48.52-3.36 1.36C9.12.52 7.94 0 6.64 0 4.06 0 2 2.06 2 4.64c0 .48.11.92.18 1.36H0v14c0 1.1.9 2 2 2h20c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm0 14H4V8h16v10z"/></svg>
        @endif
    </div>

    <div class="product-detail-info">
        <div>
            <div class="blade-inline-3">{{ $product->category }}</div>
            <h1 class="blade-inline-4">{{ $product->name }}</h1>
            <div class="blade-inline-5">Sold by <strong>{{ $product->seller->business_name ?? $product->seller->full_name }}</strong></div>
        </div>

        <div>
            <span class="detail-price">₱{{ number_format($product->effective_price, 2) }}</span>
            @if($product->discount > 0)
                <span class="detail-original">₱{{ number_format($product->price, 2) }}</span>
                <span class="detail-discount">-{{ $product->discount }}%</span>
            @endif
        </div>

        @if($product->description)
        <p class="blade-inline-6">{{ $product->description }}</p>
        @endif

        <form method="POST" action="/buyer/cart/{{ $product->id }}" id="addCartForm">
            @csrf

            {{-- Variations grouped by type --}}
            @php $grouped = $product->variations->groupBy('type'); @endphp
            @foreach($grouped as $type => $options)
            <div class="variation-group">
                <div class="variation-label">{{ ucfirst($type) }}: <span id="selected_{{ Str::slug($type) }}" class="blade-inline-7">—</span></div>
                <div class="variation-options">
                    @foreach($options as $opt)
                    <button type="button" class="var-btn" data-type="{{ $type }}" data-id="{{ $opt->id }}" data-value="{{ $opt->value }}" onclick="selectVariation(this, '{{ Str::slug($type) }}')">
                        {{ $opt->value }}
                    </button>
                    @endforeach
                </div>
            </div>
            @endforeach
            <input type="hidden" name="variation_id" id="variation_id">

            <div>
                <div class="variation-label">Quantity</div>
                <div class="qty-control">
                    <button type="button" class="qty-btn" onclick="changeQty(-1)">−</button>
                    <input type="number" name="quantity" id="qtyInput" class="qty-input" value="1" min="1" max="{{ $product->stock }}">
                    <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
                </div>
                <div class="blade-inline-8">{{ $product->stock }} available</div>
            </div>

            @if($product->voucher_code)
            <div class="blade-inline-9">
                Use voucher <strong>{{ $product->voucher_code }}</strong> at checkout for {{ $product->voucher_discount }}% off
            </div>
            @endif

            <div class="blade-inline-10">
                <button type="submit" class="btn btn-coral blade-inline-11">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM5.2 5H2V3H0v2h2l3.6 7.59L4.25 15A2 2 0 0 0 6 18h14v-2H6.42a.25.25 0 0 1-.25-.25l.03-.12L7.1 14h9.45c.75 0 1.41-.41 1.75-1.03L21.7 6.5A1 1 0 0 0 20.83 5H5.2z"/></svg>
                    Add to Cart
                </button>
                <a href="/buyer/chat?user={{ $product->seller_id }}&product={{ $product->id }}" class="btn btn-outline">Chat Seller</a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
@vite('resources/js/views/buyer-product-detail.js')
@endsection
