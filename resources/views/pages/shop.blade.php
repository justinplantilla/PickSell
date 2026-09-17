@extends('layouts.app')
@section('title', 'Shop')

@section('styles')
<style>
    .shop-banner {
        background: var(--charcoal);
        color: #fff;
        padding: 1.5rem 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .shop-banner h1 { font-size: 1.2rem; font-weight: 700; }
    .shop-banner h1 span { color: var(--coral); }
    .breadcrumb { font-size: 0.82rem; color: #aaa; }
    .breadcrumb a { color: #aaa; }
    .breadcrumb a:hover { color: var(--coral); }

    .shop-layout { display: flex; max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem; gap: 2rem; align-items: flex-start; }

    /* Sidebar */
    .shop-sidebar { width: 220px; flex-shrink: 0; }
    .filter-box { background: #fff; border: 1px solid #e8e2d8; border-radius: 12px; padding: 1.2rem; margin-bottom: 1rem; animation: filter-box-in .7s cubic-bezier(.22,1,.36,1) both; transition: box-shadow .3s ease, transform .3s ease, border-color .3s ease; }
    .filter-box:hover { border-color: rgba(232,71,42,.35); box-shadow: 0 12px 24px rgba(45,45,45,.09); transform: translateY(-3px); }
    .filter-box h3 { font-size: 0.88rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #888; margin-bottom: 0.8rem; transition: color .2s ease; }
    .filter-box:hover h3 { color: var(--coral); }
    .filter-item { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; padding: .18rem .25rem; font-size: 0.88rem; cursor: pointer; border-radius: 5px; transition: background-color .2s ease, color .2s ease, transform .2s ease; }
    .filter-item:hover { background: #fff3f0; color: var(--coral); transform: translateX(4px); }
    .filter-item input { accent-color: var(--coral); cursor: pointer; }
    .filter-item input:focus-visible { outline: 2px solid var(--coral); outline-offset: 2px; }
    .filter-item label { cursor: pointer; }
    .price-range { display: flex; gap: 0.5rem; align-items: center; font-size: 0.85rem; }
    .price-range input { width: 70px; padding: 0.35rem 0.5rem; border: 1.5px solid #ddd6c8; border-radius: 6px; font-size: 0.82rem; background: var(--bone); outline: none; transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease; }
    .price-range input:focus { border-color: var(--coral); box-shadow: 0 0 0 3px rgba(232,71,42,.12); transform: translateY(-1px); }
    .btn-filter { width: 100%; padding: 0.55rem; background: var(--coral); color: #fff; border: none; border-radius: 8px; font-size: 0.88rem; font-weight: 700; cursor: pointer; margin-top: 0.8rem; transition: background 0.2s, transform 0.2s, box-shadow 0.2s; }
    .btn-filter:hover { background: var(--coral-dark); transform: translateY(-2px); box-shadow: 0 8px 16px rgba(232,71,42,.22); }
    .btn-filter:active { transform: translateY(0); box-shadow: none; }

    /* Main */
    .shop-main { flex: 1; min-width: 0; }
    .shop-toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.2rem; flex-wrap: wrap; gap: 0.5rem; }
    .shop-count { font-size: 0.88rem; color: #777; }
    .shop-count strong { color: var(--charcoal); }
    .sort-select { padding: 0.45rem 0.8rem; border: 1.5px solid #ddd6c8; border-radius: 8px; font-size: 0.85rem; background: #fff; color: var(--charcoal); outline: none; cursor: pointer; }
    .sort-select:focus { border-color: var(--coral); }

    .products-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
    .product-card { background: #fff; border: 1px solid #e3e0da; overflow: hidden; position: relative; opacity: 0; transform: translateY(28px) scale(.97); animation: shop-card-in .65s cubic-bezier(.22,1,.36,1) forwards; transition: background-color .45s ease, color .45s ease, box-shadow .35s ease, transform .35s ease; }
    .product-card:nth-child(2) { animation-delay: .06s; }
    .product-card:nth-child(3) { animation-delay: .12s; }
    .product-card:nth-child(4) { animation-delay: .18s; }
    .product-card:nth-child(5) { animation-delay: .24s; }
    .product-card:nth-child(6) { animation-delay: .30s; }
    .product-card:nth-child(7) { animation-delay: .36s; }
    .product-card:nth-child(8) { animation-delay: .42s; }
    .product-card:hover { box-shadow: 0 14px 28px rgba(23,25,29,.12); transform: translateY(-6px); }
    .product-card::after { content: ''; position: absolute; inset: 0; pointer-events: none; background: linear-gradient(110deg, transparent 30%, rgba(255,255,255,.32) 48%, transparent 64%); transform: translateX(-125%); transition: transform .8s cubic-bezier(.22,1,.36,1); }
    .product-card:hover::after { transform: translateX(125%); }
    .product-badge { position: absolute; top: 10px; left: 10px; background: var(--coral); color: #fff; font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 4px; text-transform: uppercase; }
    .product-badge.new { background: var(--charcoal); }
    .product-wishlist { position: absolute; top: 10px; right: 10px; z-index: 2; background: #fff; border: none; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.25rem; line-height: 1; color: var(--coral); box-shadow: 0 2px 6px rgba(0,0,0,0.1); transition: transform 0.2s, background-color .2s; }
    .product-wishlist:hover { transform: scale(1.15); }
    .product-img { width: 100%; aspect-ratio: 1.05; background: linear-gradient(135deg, #eeeae3, #f8f6f2); display: grid; place-items: center; position: relative; overflow: hidden; }
    .product-img img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .45s ease, filter .45s ease; }
    .product-card:hover .product-img img { transform: scale(1.05); filter: saturate(1.08); }
    .product-placeholder { width: 42%; height: 42%; border: 1px solid rgba(23,25,29,.12); border-radius: 8px; background: rgba(255,255,255,.65); box-shadow: 0 10px 20px rgba(23,25,29,.06); transition: transform .55s cubic-bezier(.22,1,.36,1), box-shadow .55s ease; }
    .product-image-details { position: absolute; inset: 0; display: flex; flex-direction: column; justify-content: flex-end; padding: .85rem; color: #fff; background: linear-gradient(transparent 20%, rgba(23,25,29,.9)); opacity: 0; transform: translateY(10px); transition: opacity .25s, transform .25s; }
    .product-image-details small { color: #ffd8d1; font-size: .62rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .product-image-details strong { font-size: .82rem; line-height: 1.2; margin-top: .2rem; }
    .product-img::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,.4), transparent 45%); opacity: 0; transition: opacity .3s; }
    .product-card:hover .product-img::before { opacity: 1; }
    .product-card:hover .product-placeholder { transform: scale(1.12) rotate(3deg); box-shadow: 0 16px 28px rgba(23,25,29,.12); }
    .product-card:hover .product-image-details { opacity: 1; transform: translateY(0); }
    .product-info { padding: 1rem; transition: background-color .45s ease; }
    .product-cat { font-size: 0.72rem; color: #aaa; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.2rem; }
    .product-name { font-weight: 700; font-size: 0.9rem; margin-bottom: 0.4rem; line-height: 1.3; }
    .product-rating { font-size: 0.78rem; color: #f5a623; margin-bottom: 0.4rem; }
    .product-rating span { color: #aaa; margin-left: 0.2rem; }
    .product-prices { display: flex; align-items: baseline; gap: 0.4rem; margin-bottom: 0.6rem; }
    .product-price { color: var(--coral); font-weight: 800; font-size: 1.05rem; }
    .product-old { color: #bbb; font-size: 0.8rem; text-decoration: line-through; }
    .product-discount { background: #fff3f0; color: var(--coral); font-size: 0.72rem; font-weight: 700; padding: 0.1rem 0.35rem; border-radius: 4px; }
    .product-btn { display: block; text-align: center; background: var(--charcoal); color: #fff; padding: 0.6rem; border-radius: 0; font-size: 0.78rem; font-weight: 800; transition: background 0.2s, transform .2s; }
    .product-btn:hover { background: var(--coral); }
    .product-card:hover .product-info { background: var(--coral); color: #fff; }
    .product-card:hover .product-price { color: #fff; }
    .product-card:hover .product-old { color: #ffd8d1; }
    .product-card:hover .product-btn { background: #fff; color: var(--charcoal); transform: translateY(-2px); }
    @keyframes shop-card-in { to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes filter-box-in { from { opacity: 0; transform: translateX(-18px); } to { opacity: 1; transform: translateX(0); } }

    @media(max-width: 640px) {
        .shop-layout { flex-direction: column; }
        .shop-sidebar { width: 100%; }
        .products-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media(max-width: 420px) { .products-grid { grid-template-columns: 1fr; } }
    @media(min-width: 1440px) {
        .shop-layout { max-width: 1680px; padding-inline: clamp(1.5rem, 4vw, 5rem); gap: clamp(2rem, 3vw, 4rem); }
        .shop-sidebar { width: 280px; }
        .products-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 1.25rem; }
        .product-info { padding: 1.25rem; }
        .product-name { font-size: 1rem; }
        .product-price { font-size: 1.2rem; }
    }
</style>
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
            @php $cats = ['Electronics','Fashion','Home & Living','Sports','Beauty','Books','Toys']; @endphp
            <form method="GET" action="/shop">
            @foreach($cats as $cat)
            <div class="filter-item">
                <input type="checkbox" name="cat" id="cat-{{ $loop->index }}" value="{{ strtolower($cat) }}" {{ request('cat') === strtolower($cat) ? 'checked' : '' }}>
                <label for="cat-{{ $loop->index }}">{{ $cat }}</label>
            </div>
            @endforeach

        <div class="filter-box" style="border:none;padding:0;margin-top:1rem;">
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
