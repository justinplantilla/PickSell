@extends('buyer.layout')
@section('styles')
@vite('resources/css/views/buyer-home.css')
@endsection
@section('title', 'Shop')

@section('catbar')
<div class="cat-bar">
    <a href="/buyer/shop" class="cat-pill {{ !$category && !$deals ? 'active' : '' }}">All</a>
    @foreach($categories as $cat)
    <a href="/buyer/shop?category={{ urlencode($cat) }}{{ $search ? '&search='.urlencode($search) : '' }}" class="cat-pill {{ $category === $cat ? 'active' : '' }}">{{ $cat }}</a>
    @endforeach
</div>
@endsection

@section('content')
<div class="buyer-home">
    @php
        $promoMonth = now()->month;
        $seasonalPromo = match (true) {
            $promoMonth === 2 => ['theme' => 'valentines', 'kicker' => "Valentine's picks", 'title' => 'Find something', 'highlight' => 'to love', 'category' => 'Fashion', 'decoration' => 'LOVE'],
            $promoMonth >= 3 && $promoMonth <= 5 => ['theme' => 'summer', 'kicker' => 'Summer finds', 'title' => 'Make it a', 'highlight' => 'bright summer', 'category' => 'Sports', 'decoration' => 'SUMMER'],
            $promoMonth >= 6 && $promoMonth <= 7 => ['theme' => 'rainy', 'kicker' => 'Rainy day picks', 'title' => 'Stay in and', 'highlight' => 'shop happy', 'category' => 'Home & Living', 'decoration' => 'COZY'],
            $promoMonth >= 8 && $promoMonth <= 10 => ['theme' => 'school', 'kicker' => 'Back-to-school picks', 'title' => 'Ready for', 'highlight' => 'what is next', 'category' => 'Books', 'decoration' => 'READY'],
            $promoMonth >= 11 => ['theme' => 'holiday', 'kicker' => 'Holiday picks', 'title' => 'Make gifting', 'highlight' => 'extra special', 'category' => 'Toys', 'decoration' => 'GIFT'],
            default => ['theme' => 'fresh', 'kicker' => 'Fresh finds', 'title' => 'A new year,', 'highlight' => 'new favorites', 'category' => 'Electronics', 'decoration' => 'FRESH'],
        };
    @endphp
    <section class="promo-carousel" id="promoCarousel" aria-label="Promotions">
        <div class="promo-track" id="promoTrack">
            <a href="{{ route('buyer.browse', ['deals' => 1]) }}" class="promo-slide promo-slide--fire" aria-label="Shop flash sale deals">
                <div class="promo-bg-shapes">
                    <span class="promo-shape promo-shape-1"></span>
                    <span class="promo-shape promo-shape-2"></span>
                    <span class="promo-shape promo-shape-3"></span>
                    <span class="promo-spark" aria-hidden="true"></span>
                    <span class="promo-spark" aria-hidden="true"></span>
                    <span class="promo-spark" aria-hidden="true"></span>
                    <span class="promo-spark" aria-hidden="true"></span>
                    <span class="promo-spark" aria-hidden="true"></span>
                </div>
                <div class="promo-copy">
                    <span class="promo-kicker">Limited time</span>
                    <h2>Flash <em>deals</em></h2>
                    <p>Explore discounted finds from local sellers.</p>
                    <span class="promo-cta">Shop deals <span aria-hidden="true">→</span></span>
                </div>
                <div class="promo-deco" aria-hidden="true">DEALS</div>
            </a>

            <a href="{{ route('buyer.categories') }}" class="promo-slide promo-slide--fresh" aria-label="Browse top picks by category">
                <div class="promo-bg-shapes">
                    <span class="promo-shape promo-shape-1"></span>
                    <span class="promo-shape promo-shape-2"></span>
                </div>
                <div class="promo-copy">
                    <span class="promo-kicker">Picked for you</span>
                    <h2>Discover <em>top picks</em></h2>
                    <p>Shop popular products across your favorite categories.</p>
                    <span class="promo-cta">Explore top picks <span aria-hidden="true">→</span></span>
                </div>
                <div class="promo-deco" aria-hidden="true">PICKS</div>
            </a>

            <a href="{{ route('buyer.categories', ['category' => $seasonalPromo['category']]) }}" class="promo-slide promo-slide--seasonal promo-slide--{{ $seasonalPromo['theme'] }}" aria-label="{{ $seasonalPromo['kicker'] }}">
                <div class="promo-bg-shapes">
                    <span class="promo-shape promo-shape-1"></span>
                    <span class="promo-shape promo-shape-2"></span>
                    <span class="promo-shape promo-shape-3"></span>
                </div>
                <div class="promo-copy">
                    <span class="promo-kicker">{{ $seasonalPromo['kicker'] }}</span>
                    <h2>{{ $seasonalPromo['title'] }} <em>{{ $seasonalPromo['highlight'] }}</em></h2>
                    <p>Explore {{ $seasonalPromo['category'] }} finds picked for the season.</p>
                    <span class="promo-cta">Shop seasonal picks <span aria-hidden="true">→</span></span>
                </div>
                <div class="promo-deco" aria-hidden="true">{{ $seasonalPromo['decoration'] }}</div>
            </a>

            <a href="{{ route('buyer.categories', ['category' => 'Fashion']) }}" class="promo-slide promo-slide--style" aria-label="Browse fashion finds">
                <div class="promo-bg-shapes">
                    <span class="promo-shape promo-shape-1"></span>
                    <span class="promo-shape promo-shape-2"></span>
                </div>
                <div class="promo-copy">
                    <span class="promo-kicker">Find your look</span>
                    <h2>Everyday <em>style</em></h2>
                    <p>Discover fashion favorites from local sellers.</p>
                    <span class="promo-cta">Browse fashion <span aria-hidden="true">→</span></span>
                </div>
                <div class="promo-deco" aria-hidden="true">STYLE</div>
            </a>

        </div>

        <div class="promo-dots" id="promoDots" aria-label="Carousel navigation"></div>

        <button class="promo-arrow promo-arrow--prev" id="promoPrev" aria-label="Previous slide">
            <svg viewBox="0 0 24 24" width="20" height="20"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round"/></svg>
        </button>
        <button class="promo-arrow promo-arrow--next" id="promoNext" aria-label="Next slide">
            <svg viewBox="0 0 24 24" width="20" height="20"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round"/></svg>
        </button>
    </section>

    <section class="buyer-section">
        <div class="buyer-section-head">
            <h2 class="buyer-section-title">Top Picks by Category</h2>
            <a href="{{ route('buyer.categories') }}" class="buyer-section-link">View all categories</a>
        </div>
        <div class="buyer-top-picks">
            @forelse($topCategoryProducts as $product)
            <a class="buyer-top-pick" href="{{ route('buyer.product', $product) }}">
                <span class="buyer-top-pick-category">{{ $product->category ?? 'Popular pick' }}</span>
                <span class="buyer-top-pick-image">
                    @if($product->primary_image)
                        <img src="{{ Storage::url($product->primary_image) }}" alt="">
                    @else
                        <span aria-hidden="true">✦</span>
                    @endif
                </span>
                <strong>{{ $product->name }}</strong>
                <span class="buyer-top-pick-price">₱{{ number_format($product->effective_price, 2) }}</span>
            </a>
            @empty
                <p class="buyer-top-picks-empty">Popular picks will appear here as products are added.</p>
            @endforelse
        </div>
    </section>

    <div class="buyer-sale-strip">
        <span><strong>FLASH SALE</strong> &nbsp; Fresh finds at prices worth grabbing.</span>
        <a href="{{ route('buyer.browse', ['deals' => 1]) }}" class="btn">Shop all deals</a>
    </div>

    <section class="buyer-section" id="deals">
        <div class="buyer-section-head">
            <h2 class="buyer-section-title">{{ $search || $category || $deals ? ($search || $category ? 'Search Results' : 'Deals & Promos') : 'Deals of the Day' }}</h2>
            @if(!$search && !$category && !$deals)
            <a href="{{ route('buyer.browse', ['deals' => 1]) }}" class="buyer-section-link">See all deals</a>
            @endif
        </div>

        @if($search || $category || $deals)
            <div class="blade-inline-1">
                Showing results
                @if($search) for "<strong>{{ $search }}</strong>"@endif
                @if($category) in <strong>{{ $category }}</strong>@endif
                — {{ $products->total() }} product(s) found
                <a href="/buyer/shop" class="blade-inline-2">Clear</a>
            </div>
            @if($products->isEmpty())
            <div class="blade-inline-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" viewBox="0 0 24 24" class="blade-inline-4"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM5.2 5H2V3H0v2h2l3.6 7.59L4.25 15A2 2 0 0 0 6 18h14v-2H6.42a.25.25 0 0 1-.25-.25l.03-.12L7.1 14h9.45c.75 0 1.41-.41 1.75-1.03L21.7 6.5A1 1 0 0 0 20.83 5H5.2z"/></svg>
                <div>No products found.</div>
            </div>
            @else
            <div class="product-grid">
                @foreach($products as $product)
                <article class="product-card">
                    <a href="/buyer/product/{{ $product->id }}" class="product-card-link">
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
            </div>
            <div class="buyer-pagination dashboard-pagination">{{ $products->withQueryString()->links() }}</div>
            @endif
        @else
            {{-- Home: show 14 deal cards + 1 view-all card --}}
            @if($dealProducts && $dealProducts->isNotEmpty())
            <div class="product-grid deals-grid">
                @foreach($dealProducts as $product)
                <article class="product-card deal-card">
                    <a href="/buyer/product/{{ $product->id }}" class="product-card-link">
                    <div class="product-img-wrap">
                        @if($product->primary_image)
                            <img src="{{ Storage::url($product->primary_image) }}" alt="{{ $product->name }}">
                        @else
                            <div class="product-img-placeholder"><svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg></div>
                        @endif
                        <span class="product-discount-badge deal-badge">🔥 -{{ $product->discount }}%</span>
                        <span class="deal-shimmer" aria-hidden="true"></span>
                        <div class="product-hover-details">
                            <span class="product-hover-category">{{ $product->category ?? 'Product' }}</span>
                            <span class="product-hover-name">{{ $product->name }}</span>
                            <span class="product-hover-stock">{{ $product->stock }} available · View details</span>
                        </div>
                    </div>
                    <div class="product-info">
                        <div class="product-name">{{ $product->name }}</div>
                        <div class="deal-price-row">
                            <span class="product-price">₱{{ number_format($product->effective_price, 2) }}</span>
                            <span class="product-original deal-original">₱{{ number_format($product->price, 2) }}</span>
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
                {{-- 15th card: view all --}}
                <a href="{{ route('buyer.browse', ['deals' => 1]) }}" class="product-card deals-viewall-card" aria-label="Browse all deals">
                    <div class="deals-viewall-inner">
                        <span class="deals-viewall-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" viewBox="0 0 24 24"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8-8-8z"/></svg>
                        </span>
                        <strong>See all deals</strong>
                        <span>Browse every discounted product</span>
                    </div>
                </a>
            </div>
            @else
            <p style="color:#aaa;font-size:.88rem;">No deals available right now. Check back soon!</p>
            @endif
        @endif
    </section>

    @if(!$search && !$category && !$deals)
    <section class="buyer-section">
        <div class="buyer-section-head">
            <h2 class="buyer-section-title">Recommended For You</h2>
        </div>
        <div class="product-grid">
            @foreach($recommendedProducts as $product)
            <article class="product-card">
                <a href="{{ route('buyer.product', $product) }}" class="product-card-link">
                <div class="product-img-wrap">
                    @if($product->primary_image)
                        <img src="{{ Storage::url($product->primary_image) }}" alt="{{ $product->name }}">
                    @else
                        <div class="product-img-placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 2-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-2 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
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
                    <span class="product-price">₱{{ number_format($product->effective_price, 2) }}</span>
                    <div class="product-seller">{{ $product->seller->business_name ?? $product->seller->full_name }}</div>
                </div>
                </a>
                <div class="product-card-actions">
                    <button type="button" class="card-add-btn" data-card-cart-action="/buyer/cart/{{ $product->id }}">Add to Cart</button>
                    <button type="button" class="card-buy-btn" data-card-cart-action="/buyer/cart/{{ $product->id }}" data-card-buy-now="/buyer/checkout">Buy Now</button>
                </div>
            </article>
            @endforeach
            {{-- View all card --}}
            <a href="{{ route('buyer.recommended') }}" class="product-card deals-viewall-card" aria-label="See all recommended products">
                <div class="deals-viewall-inner">
                    <span class="deals-viewall-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" viewBox="0 0 24 24"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8-8-8z"/></svg>
                    </span>
                    <strong>See all picks</strong>
                    <span>Browse everything recommended for you</span>
                </div>
            </a>
        </div>
    </section>
    @endif
</div>
@endsection

@section('scripts')
@vite('resources/js/views/buyer-home.js')
@endsection
