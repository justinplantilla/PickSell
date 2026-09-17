@extends('admin.layout')
@section('title', 'Products')

@section('content')
<div class="card">
    <div class="card-header">
        <span class="card-title">Product Curation</span>
        <form method="GET" class="filters">
            <input type="text" name="search" class="search-input" placeholder="Search products..." value="{{ $search }}">
            <button type="submit" class="btn btn-coral btn-sm">Search</button>
        </form>
    </div>
    <div style="overflow-x:auto;">
        <table>
            <thead><tr><th>Product</th><th>Seller</th><th>Category</th><th>Price</th><th>Status</th><th>Featured</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($products as $product)
            <tr>
                <td><div style="display:flex;align-items:center;gap:0.65rem;min-width:220px;">
                    @if($product->image)<img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" style="width:42px;height:42px;object-fit:cover;border-radius:6px;">@endif
                    <strong>{{ $product->name }}</strong>
                </div></td>
                <td>{{ $product->seller->business_name ?? $product->seller->full_name }}</td>
                <td>{{ $product->category ?? '—' }}</td>
                <td>₱{{ number_format($product->effective_price, 2) }}</td>
                <td><span class="badge badge-{{ $product->status }}">{{ ucfirst($product->status) }}</span></td>
                <td><span class="badge {{ $product->is_featured ? 'badge-approved' : 'badge-archived' }}">{{ $product->is_featured ? 'Yes' : 'No' }}</span></td>
                <td><form method="POST" action="{{ route('admin.products.featured', $product) }}">@csrf @method('PATCH')<button type="submit" class="btn {{ $product->is_featured ? 'btn-outline' : 'btn-coral' }} btn-sm">{{ $product->is_featured ? 'Unfeature' : 'Feature' }}</button></form></td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;color:#888;padding:2rem;">No products found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($products->hasPages())<div class="dashboard-pagination">{{ $products->withQueryString()->links() }}</div>@endif
</div>
@endsection