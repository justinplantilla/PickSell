@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-list.css', 'resources/css/views/admin-products.css'])
@endsection
@section('title', 'Products')

@section('content')
@php
    $canExport = auth()->user()->can(\App\Auth\Permission::REPORTS_EXPORT);
    $canModerate = auth()->user()->can(\App\Auth\Permission::PRODUCTS_MODERATE);
    $exportQuery = request()->except('page');
    $tabQuery = fn (string $key) => array_filter(['tab' => $key === 'all' ? null : $key] + request()->except(['tab', 'page']), fn ($v) => $v !== null && $v !== '');
@endphp
<div class="card list-page">
    <div class="card-header">
        <div>
            <span class="card-title">Product queue</span>
            <p class="oversight-description">Click a product for a quick look. Every archive, restore or feature is recorded with its reason and the seller is notified.</p>
        </div>
        @if($canExport && $matchingCount > 0)
            <a href="{{ route('admin.products.export', $exportQuery) }}" class="btn btn-outline btn-sm list-export">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v3h16v-3"/></svg>
                Export {{ number_format($matchingCount) }}
            </a>
        @endif
    </div>

    <nav class="oversight-tabs" aria-label="Product views">
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.products', $tabQuery($key)) }}" class="oversight-tab {{ $tab === $key ? 'is-active' : '' }} {{ $key === 'attention' && $tabCounts[$key] > 0 ? 'is-attention' : '' }}" @if($tab === $key) aria-current="page" @endif>
                @if($key === 'featured')<span aria-hidden="true">★</span>@endif
                {{ $label }} <span class="oversight-tab-count">{{ number_format($tabCounts[$key]) }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.products') }}" class="list-toolbar" role="search" aria-label="Filter products"
          data-saved-filter-scope="products" data-saved-filter-user="{{ auth()->id() }}" data-saved-filter-mode="menu">
        @if($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
        <label class="list-search">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span class="sr-only">Search products</span>
            <input type="search" name="search" value="{{ $search }}" placeholder="Search product or seller…">
        </label>
        <select name="category" class="filter-select" aria-label="Category" data-submit-on-change>
            <option value="">All categories</option>
            @foreach($categories as $option)
                <option value="{{ $option }}" @selected($category === $option)>{{ $option }}</option>
            @endforeach
        </select>
        <details class="list-popover">
            <summary class="btn btn-outline btn-sm">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                More filters @if($moreFiltersCount)<span class="list-count-pill">{{ $moreFiltersCount }}</span>@endif
            </summary>
            <div class="list-popover-panel">
                <label class="list-field">
                    <span>Seller</span>
                    <select name="seller" class="filter-select">
                        <option value="">All sellers</option>
                        @foreach($sellers as $option)
                            <option value="{{ $option->id }}" @selected($seller === $option->id)>{{ $option->business_name ?? $option->full_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="list-field">
                    <span>Listing status</span>
                    <select name="status" class="filter-select">
                        <option value="all">Any status</option>
                        @foreach(\App\Http\Controllers\AdminProductController::STATUS_FILTERS as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="list-field">
                    <span>Featured</span>
                    <select name="featured" class="filter-select">
                        <option value="all">Featured or not</option>
                        @foreach(\App\Http\Controllers\AdminProductController::FEATURED_FILTERS as $value => $label)
                            <option value="{{ $value }}" @selected($featured === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="list-popover-actions">
                    <button type="submit" class="btn btn-coral btn-sm">Apply filters</button>
                </div>
            </div>
        </details>
        <div class="list-views" data-saved-filter-mount></div>
        <button type="submit" class="sr-only">Search</button>
    </form>

    @if($chips)
        <div class="list-chips" aria-label="Active filters">
            @foreach($chips as $chip)
                <a href="{{ $chip['url'] }}" class="list-chip">{{ $chip['label'] }} <span aria-hidden="true">✕</span><span class="sr-only">(remove filter)</span></a>
            @endforeach
            <a href="{{ route('admin.products', $tab !== 'all' ? ['tab' => $tab] : []) }}" class="list-chips-clear">Clear all</a>
        </div>
    @endif

    @if(session('bulkSkippedCount', 0) > 0)
        <div class="alert alert-warning list-alert" role="status">
            {{ session('bulkSkippedCount') }} product(s) were skipped:
            <ul>@foreach(session('bulkSkipped', []) as $skipped)<li>{{ $skipped }}</li>@endforeach</ul>
            @if(session('bulkSkippedCount') > count(session('bulkSkipped', [])))<p>Additional skipped products are omitted from this summary.</p>@endif
        </div>
    @endif

    @if($products->isEmpty())
        <div class="oversight-empty"><strong>No products found</strong>{{ $chips || $tab !== 'all' ? 'Nothing matches this view.' : 'Products appear here once sellers list them.' }}</div>
    @else
        <div class="oversight-table-wrap">
            <table class="list-table" data-list-table>
                <thead>
                    <tr>
                        @if($canModerate)<th class="list-check"><input type="checkbox" data-select-page aria-label="Select all products on this page"></th>@endif
                        <th>Product</th>
                        <th>Seller</th>
                        <th class="list-amount">Price</th>
                        <th class="list-number">Stock</th>
                        <th>Status</th>
                        <th class="list-chevron"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($products as $product)
                    @php
                        $attention = \App\Http\Controllers\AdminProductController::attentionReasons($product);
                        $outsideLine = $product->category && $product->seller?->line_of_business
                            && strcasecmp($product->category, $product->seller->line_of_business) !== 0;
                        $stockState = $product->stock_status;
                    @endphp
                    <tr class="list-row" data-list-row data-preview-url="{{ route('admin.products.preview', $product) }}">
                        @if($canModerate)
                            <td class="list-check"><input type="checkbox" value="{{ $product->id }}" data-select-row aria-label="Select {{ $product->name }}"></td>
                        @endif
                        <td class="list-cell-main" data-label="Product">
                            <div class="product-cell">
                                @if($product->primary_image)
                                    <img src="{{ Storage::url($product->primary_image) }}" alt="" class="product-thumb" loading="lazy">
                                @else
                                    <span class="product-thumb is-empty" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg></span>
                                @endif
                                <span class="product-cell-text">
                                    <a href="{{ route('admin.products.show', $product) }}" class="list-title product-name" data-row-link title="{{ $product->name }}">{{ $product->name }}</a>
                                    <span class="oversight-meta">{{ $product->category ?? 'Uncategorised' }}@if($outsideLine) · <span class="product-outside-line" title="Seller registered for {{ $product->seller->line_of_business }}">outside registered line</span>@endif</span>
                                    @if($attention)
                                        <span class="oversight-flag product-attention"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>{{ implode(' · ', $attention) }}</span>
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td class="list-cell-wide" data-label="Seller">
                            <span class="list-line">{{ $product->seller?->business_name ?? $product->seller?->full_name ?? '—' }}</span>
                            @if($product->seller && $product->seller->status !== 'approved')<span class="oversight-meta">Seller {{ str_replace('_', ' ', $product->seller->status) }}</span>@endif
                        </td>
                        <td class="list-amount list-cell-aside" data-label="Price">
                            <span class="list-money"><span aria-hidden="true">₱</span><span class="sr-only">PHP </span><span>{{ number_format($product->effective_price, 2) }}</span></span>
                            @if($product->discount > 0)<span class="oversight-meta product-was">₱{{ number_format($product->price, 2) }} · −{{ rtrim(rtrim(number_format($product->discount, 2), '0'), '.') }}%</span>@endif
                        </td>
                        <td class="list-number list-cell-sub-aside product-stock is-{{ str_replace('_', '-', $stockState) }}" data-label="Stock">
                            <span class="list-line">@if(in_array($stockState, ['low_stock', 'out_of_stock'], true))<span aria-hidden="true">⚠ </span>@endif{{ number_format($product->stock) }}</span>
                            @if($stockState === 'low_stock')<span class="oversight-meta">Low</span>@elseif($stockState === 'out_of_stock')<span class="oversight-meta">Out</span>@endif
                        </td>
                        <td class="list-status list-cell-sub" data-label="Status">
                            <div class="product-status-line">
                                <span class="badge {{ $product->status === 'active' ? 'badge-approved' : 'badge-deactivated' }}">{{ $product->status === 'active' ? 'Active' : 'Archived' }}</span>
                                @if($canModerate && ($product->is_featured || $product->status === 'active'))
                                    <form method="POST" action="{{ route('admin.products.featured', $product) }}" class="product-star-form"
                                          data-confirm="{{ $product->is_featured ? 'Remove '.$product->name.' from Featured Products?' : 'Feature '.$product->name.'?' }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="product-star {{ $product->is_featured ? 'is-on' : '' }}" aria-pressed="{{ $product->is_featured ? 'true' : 'false' }}"
                                                title="{{ $product->is_featured ? 'Featured — click to remove' : 'Not featured — click to feature' }}">
                                            <span aria-hidden="true">{{ $product->is_featured ? '★' : '☆' }}</span><span class="sr-only">{{ $product->is_featured ? 'Unfeature' : 'Feature' }} {{ $product->name }}</span>
                                        </button>
                                    </form>
                                @elseif($product->is_featured)
                                    <span class="product-star is-on" title="Featured"><span aria-hidden="true">★</span><span class="sr-only">Featured</span></span>
                                @endif
                            </div>
                            @if($product->isUnderAdminHold())<span class="oversight-meta">Archived by Admin</span>@elseif($product->status === 'archived')<span class="oversight-meta">Archived by seller</span>@endif
                        </td>
                        <td class="list-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($products->hasPages())<div class="dashboard-pagination">{{ $products->links() }}</div>@endif
    @endif
</div>

@if($canModerate && $matchingCount > 0)
    <div class="list-bulkbar" data-bulkbar data-matching-count="{{ $matchingCount }}" data-noun="product" hidden>
        <div class="list-bulkbar-summary">
            <strong data-bulk-count>0 selected</strong>
            @if($matchingCount > $products->count())
                <button type="button" class="list-link-button" data-select-all-matching>Select all {{ number_format($matchingCount) }} matching</button>
            @endif
        </div>
        <div class="list-bulkbar-actions">
            <button type="button" class="btn btn-coral btn-sm" data-bulk-open="bulk-archive-dialog">Archive</button>
            <button type="button" class="btn btn-outline btn-sm" data-bulk-open="bulk-restore-dialog">Restore</button>
            <button type="button" class="btn btn-outline btn-sm" data-bulk-open="bulk-feature-dialog">★ Feature</button>
            <button type="button" class="btn btn-outline btn-sm" data-bulk-open="bulk-unfeature-dialog">Unfeature</button>
            @if($canExport)
                <a href="{{ route('admin.products.export', $exportQuery) }}" class="btn btn-outline btn-sm" data-bulk-export data-ids-param="product_ids" data-export-base="{{ route('admin.products.export', $exportQuery) }}">Export selected</a>
            @endif
            <button type="button" class="list-link-button" data-bulk-clear>Clear</button>
        </div>
    </div>

    @foreach([
        'bulk-archive-dialog' => ['Archive products', route('admin.products.bulk-status'), ['to_status' => 'archived'], true, 'Archived products are hidden from buyers. The seller is notified with your reason.'],
        'bulk-restore-dialog' => ['Restore products', route('admin.products.bulk-status'), ['to_status' => 'active'], true, 'Restored products become visible to buyers again. The seller is notified with your reason.'],
        'bulk-feature-dialog' => ['Feature products', route('admin.products.bulk-featured'), ['featured' => '1'], false, 'Only active products can be featured; others are skipped.'],
        'bulk-unfeature-dialog' => ['Remove from Featured', route('admin.products.bulk-featured'), ['featured' => '0'], false, 'Products that are not featured are skipped.'],
    ] as $dialogId => [$title, $action, $fixed, $reasonRequired, $note])
        <dialog class="list-dialog" id="{{ $dialogId }}" data-bulk-dialog aria-labelledby="{{ $dialogId }}-title">
            <form method="POST" action="{{ $action }}" class="bulk-action-form" data-no-confirm>
                @csrf
                <h2 id="{{ $dialogId }}-title">{{ $title }}</h2>
                <p class="oversight-description"><span data-bulk-scope>Applies to the selected products.</span> {{ $note }}</p>
                <div data-bulk-ids data-ids-name="product_ids[]"></div>
                @foreach($fixed as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                @if($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
                <input type="hidden" name="search" value="{{ $search }}">
                <input type="hidden" name="seller" value="{{ $seller }}">
                <input type="hidden" name="category" value="{{ $category }}">
                <input type="hidden" name="filter_status" value="{{ $status }}">
                <input type="hidden" name="featured_filter" value="{{ $featured }}">
                <input type="hidden" name="matching_count" value="{{ $matchingCount }}" data-bulk-matching>
                <label class="form-label" for="{{ $dialogId }}-reason">Reason {{ $reasonRequired ? '(required, shown to the seller)' : '(optional)' }}</label>
                <textarea id="{{ $dialogId }}-reason" name="reason" class="form-control" maxlength="1000" @if($reasonRequired) minlength="10" required @endif></textarea>
                <label class="account-confirm"><input type="checkbox" name="confirm" value="1" required> <span data-bulk-confirm-text>Apply to the selected products.</span> Products that cannot change are skipped.</label>
                <div class="list-dialog-actions">
                    <button type="button" class="btn btn-outline btn-sm" data-bulk-cancel>Cancel</button>
                    <button type="submit" class="btn btn-coral btn-sm">Apply</button>
                </div>
            </form>
        </dialog>
    @endforeach
@endif

<div class="list-drawer-backdrop" data-drawer-backdrop hidden></div>
<aside class="list-drawer" data-list-drawer hidden aria-labelledby="list-drawer-title" role="dialog" aria-modal="true">
    <button type="button" class="list-drawer-close" data-drawer-close aria-label="Close product preview">&times;</button>
    <div data-drawer-body><p class="oversight-empty">Loading…</p></div>
</aside>
@endsection

@section('scripts')
@vite('resources/js/components/admin-list.js')
@endsection
