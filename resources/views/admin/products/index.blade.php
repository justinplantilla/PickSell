@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-products.css'])
@endsection
@section('title', 'Products')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Product queue</span>
            <p class="oversight-description">Open a product to archive, restore or feature it. Every action is recorded with its reason and the seller is notified.</p>
        </div>
    </div>
    <form method="GET" class="oversight-filters oversight-note" role="search" aria-label="Filter products" data-saved-filter-scope="products" data-saved-filter-user="{{ auth()->id() }}">
        <input type="search" name="search" value="{{ $search }}" class="search-input" placeholder="Product name" aria-label="Search products">
        <select name="seller" class="filter-select" aria-label="Seller" data-submit-on-change>
            <option value="">All sellers</option>
            @foreach($sellers as $option)
                <option value="{{ $option->id }}" {{ $seller === $option->id ? 'selected' : '' }}>{{ $option->business_name ?? $option->full_name }}</option>
            @endforeach
        </select>
        <select name="category" class="filter-select" aria-label="Category" data-submit-on-change>
            <option value="">All categories</option>
            @foreach($categories as $option)
                <option value="{{ $option }}" {{ $category === $option ? 'selected' : '' }}>{{ $option }}</option>
            @endforeach
        </select>
        <select name="status" class="filter-select" aria-label="Status" data-submit-on-change>
            @foreach(['all' => 'All statuses', 'active' => 'Active', 'archived' => 'Archived (any)', 'admin_hold' => 'Archived by Admin'] as $value => $label)
                <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <select name="featured" class="filter-select" aria-label="Featured" data-submit-on-change>
            @foreach(['all' => 'Featured or not', 'yes' => 'Featured', 'no' => 'Not featured'] as $value => $label)
                <option value="{{ $value }}" {{ $featured === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-outline btn-sm">Search</button>
        @if($search !== '' || $seller || $category || $status !== 'all' || $featured !== 'all')
            <a href="{{ route('admin.products') }}" class="btn btn-outline btn-sm">Reset</a>
        @endif
    </form>

    @if($products->isEmpty())
        <div class="oversight-empty"><strong>No products found</strong>Try different filters.</div>
    @else
        @can(\App\Auth\Permission::PRODUCTS_MODERATE)
        <details class="bulk-action-panel oversight-note">
            <summary class="btn btn-outline btn-sm">Bulk moderation · {{ number_format($matchingCount) }} matching</summary>
            <form method="POST" action="{{ route('admin.products.bulk-status') }}" class="bulk-action-form">
                @csrf
                <input type="hidden" name="search" value="{{ $search }}">
                <input type="hidden" name="seller" value="{{ $seller }}">
                <input type="hidden" name="category" value="{{ $category }}">
                <input type="hidden" name="filter_status" value="{{ $status }}">
                <input type="hidden" name="featured" value="{{ $featured }}">
                <input type="hidden" name="matching_count" value="{{ $matchingCount }}">
                <label class="form-label" for="bulk-product-status">New status</label>
                <select id="bulk-product-status" name="to_status" class="form-control" required>
                    <option value="archived">Archive</option>
                    <option value="active">Restore</option>
                </select>
                <label class="form-label" for="bulk-product-reason">Reason</label>
                <textarea id="bulk-product-reason" name="reason" class="form-control" minlength="10" maxlength="1000" required></textarea>
                <label class="account-confirm"><input type="checkbox" name="confirm" value="1" required> Apply to all {{ number_format($matchingCount) }} products matching these filters; invalid products will be skipped.</label>
                <button type="submit" class="btn btn-coral" @disabled($matchingCount === 0)>Apply to filtered products</button>
            </form>
        </details>
        @endcan
        @if(session('bulkSkippedCount', 0) > 0)
            <div class="alert alert-warning" role="status">
                {{ session('bulkSkippedCount') }} product(s) were skipped:
                <ul>@foreach(session('bulkSkipped', []) as $skipped)<li>{{ $skipped }}</li>@endforeach</ul>
                @if(session('bulkSkippedCount') > count(session('bulkSkipped', [])))<p>Additional skipped products are omitted from this summary.</p>@endif
            </div>
        @endif
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>Product</th><th>Seller</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                @foreach($products as $product)
                    <tr>
                        <td>
                            <div class="product-cell">
                                @if($product->primary_image)<img src="{{ Storage::url($product->primary_image) }}" alt="" class="product-thumb">@endif
                                <span><strong>{{ $product->name }}</strong><span class="oversight-meta">₱{{ number_format($product->effective_price, 2) }} · {{ $product->stock }} in stock</span></span>
                            </div>
                        </td>
                        <td>{{ $product->seller?->business_name ?? $product->seller?->full_name ?? '—' }}</td>
                        <td>
                            {{ $product->category ?? '—' }}
                            @if($product->category && $product->seller?->line_of_business && strcasecmp($product->category, $product->seller->line_of_business) !== 0)
                                <span class="oversight-meta">Outside registered line: {{ $product->seller->line_of_business }}</span>
                            @endif
                        </td>
                        <td>
                            <x-stock-status-badge :product="$product" />
                            @if($product->is_featured)<span class="badge badge-approved">Featured</span>@endif
                            @if($product->isUnderAdminHold())<span class="oversight-meta">Archived by Admin</span>@endif
                        </td>
                        <td><a href="{{ route('admin.products.show', $product) }}" class="btn btn-outline btn-sm">Review<span class="sr-only"> {{ $product->name }}</span></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($products->hasPages())<div class="dashboard-pagination">{{ $products->links() }}</div>@endif
    @endif
</div>
@endsection
