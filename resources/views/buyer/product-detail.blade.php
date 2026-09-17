@extends('buyer.layout')
@section('title', $product->name)

@section('styles')
<style>
    .product-detail { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
    .product-detail-img { background: var(--bone); border-radius: 12px; overflow: hidden; aspect-ratio: 1; display: flex; align-items: center; justify-content: center; }
    .product-detail-img img { width: 100%; height: 100%; object-fit: cover; }
    .product-detail-info { display: flex; flex-direction: column; gap: 1rem; }
    .detail-price { font-size: 1.8rem; font-weight: 800; color: var(--coral); }
    .detail-original { font-size: 1rem; color: #aaa; text-decoration: line-through; margin-left: 0.5rem; }
    .detail-discount { background: var(--coral); color: #fff; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 4px; margin-left: 0.5rem; }
    .variation-group { margin-bottom: 0.5rem; }
    .variation-label { font-size: 0.82rem; font-weight: 700; margin-bottom: 0.5rem; }
    .variation-options { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .var-btn { padding: 0.35rem 0.8rem; border: 1.5px solid #ddd6c8; border-radius: 6px; font-size: 0.82rem; cursor: pointer; background: #fff; transition: all 0.15s; }
    .var-btn:hover, .var-btn.selected { border-color: var(--coral); color: var(--coral); background: #fff3f0; }
    .qty-control { display: flex; align-items: center; gap: 0; border: 1.5px solid #ddd6c8; border-radius: 8px; overflow: hidden; width: fit-content; }
    .qty-btn { width: 36px; height: 36px; background: var(--bone); border: none; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .qty-btn:hover { background: #e8e2d8; }
    .qty-input { width: 50px; height: 36px; border: none; border-left: 1.5px solid #ddd6c8; border-right: 1.5px solid #ddd6c8; text-align: center; font-size: 0.9rem; outline: none; }
    @media(max-width: 768px) { .product-detail { grid-template-columns: 1fr; } }
</style>
@endsection

@section('content')
<div style="margin-bottom:1rem;font-size:0.85rem;color:#888;">
    <a href="/buyer/shop" style="color:var(--coral);">Shop</a> / {{ $product->category ?? 'Products' }} / {{ $product->name }}
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
            <div style="font-size:0.8rem;color:#aaa;margin-bottom:0.3rem;">{{ $product->category }}</div>
            <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:0.5rem;">{{ $product->name }}</h1>
            <div style="font-size:0.85rem;color:#888;">Sold by <strong>{{ $product->seller->business_name ?? $product->seller->full_name }}</strong></div>
        </div>

        <div>
            <span class="detail-price">₱{{ number_format($product->effective_price, 2) }}</span>
            @if($product->discount > 0)
                <span class="detail-original">₱{{ number_format($product->price, 2) }}</span>
                <span class="detail-discount">-{{ $product->discount }}%</span>
            @endif
        </div>

        @if($product->description)
        <p style="font-size:0.88rem;color:#555;line-height:1.6;">{{ $product->description }}</p>
        @endif

        <form method="POST" action="/buyer/cart/{{ $product->id }}" id="addCartForm">
            @csrf

            {{-- Variations grouped by type --}}
            @php $grouped = $product->variations->groupBy('type'); @endphp
            @foreach($grouped as $type => $options)
            <div class="variation-group">
                <div class="variation-label">{{ ucfirst($type) }}: <span id="selected_{{ Str::slug($type) }}" style="color:var(--coral);font-weight:400;">—</span></div>
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
                <div style="font-size:0.78rem;color:#888;margin-top:0.3rem;">{{ $product->stock }} available</div>
            </div>

            @if($product->voucher_code)
            <div style="background:#fff8e1;border:1px solid #fde68a;border-radius:8px;padding:0.6rem 0.9rem;font-size:0.82rem;">
                Use voucher <strong>{{ $product->voucher_code }}</strong> at checkout for {{ $product->voucher_discount }}% off
            </div>
            @endif

            <div style="display:flex;gap:0.8rem;flex-wrap:wrap;">
                <button type="submit" class="btn btn-coral" style="flex:1;">
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
<script>
function selectVariation(btn, typeSlug) {
    document.querySelectorAll(`.var-btn[data-type="${btn.dataset.type}"]`).forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    document.getElementById('selected_' + typeSlug).textContent = btn.dataset.value;
    // Use last selected variation id (simplified — single variation support)
    document.getElementById('variation_id').value = btn.dataset.id;
}
function changeQty(delta) {
    const input = document.getElementById('qtyInput');
    const max   = parseInt(input.max);
    let val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > max) val = max;
    input.value = val;
}
</script>
@endsection
