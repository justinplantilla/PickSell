@extends('seller.layout')
@section('styles')
@vite('resources/css/views/seller-inventory.css')
@endsection
@section('title', 'Inventory')

@section('content')
<div class="card">
    <div class="card-header">
        <span class="card-title">Product Inventory</span>
        <div class="filters">
            <form method="GET" class="blade-inline-1">
                <input type="text" name="search" data-product-search value="{{ $search }}" placeholder="Search products..." class="search-input">
                <select name="status" class="filter-select" data-submit-on-change>
                    <option value="all" {{ $status==='all'?'selected':'' }}>All Status</option>
                    <option value="active" {{ $status==='active'?'selected':'' }}>Active</option>
                    <option value="archived" {{ $status==='archived'?'selected':'' }}>Archived</option>
                </select>
                <select name="filter" class="filter-select" data-submit-on-change>
                    <option value="all" {{ $stockFilter==='all'?'selected':'' }}>All Stock</option>
                    <option value="low-stock" {{ $stockFilter==='low'?'selected':'' }}>Low Stock ({{ \App\Models\Product::LOW_STOCK_THRESHOLD }} or fewer)</option>
                </select>
                <button type="submit" class="btn btn-outline btn-sm">Search</button>
            </form>
            <button class="btn btn-coral btn-sm" onclick="openModal('addModal')">+ Add Product</button>
        </div>
    </div>
    <div class="inventory-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Product</th><th>Category</th><th>Price</th><th>Discount</th><th>Voucher</th><th>Stock</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($products as $product)
            <tr>
                <td>
                    <div class="blade-inline-3">
                        @if($product->primary_image)
                            <img src="{{ Storage::url($product->primary_image) }}" class="blade-inline-4">
                        @else
                            <div class="blade-inline-5">📦</div>
                        @endif
                        <div>
                            <div class="blade-inline-6">{{ $product->name }}</div>
                            <div class="blade-inline-7">{{ Str::limit($product->description, 40) }}</div>
                        </div>
                    </div>
                </td>
                <td>{{ $product->category ?? '—' }}</td>
                <td>₱{{ number_format($product->price, 2) }}</td>
                <td>{{ $product->discount > 0 ? $product->discount.'%' : '—' }}</td>
                <td>{{ $product->voucher_code ? $product->voucher_code.' ('.$product->voucher_discount.'%)' : '—' }}</td>
                <td><span class="blade-inline-6">{{ $product->stock }}</span></td>
                <td><x-stock-status-badge :product="$product" /></td>
                <td>
                    <div class="blade-inline-10">
                        <button class="btn btn-outline btn-sm" onclick="openEditModal({{ $product->id }}, {{ json_encode($product) }})">Edit</button>
                        @if($product->isUnderAdminHold())
                            <span class="admin-hold" title="{{ $product->latestStatusModeration->reason }}">
                                Archived by Admin
                                @if($product->latestStatusModeration->reason)<small>{{ $product->latestStatusModeration->reason }}</small>@endif
                            </span>
                        @else
                        <form method="POST" action="/seller/inventory/{{ $product->id }}/archive">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-sm {{ $product->status==='archived' ? 'btn-success' : 'btn-danger' }}">
                                {{ $product->status==='archived' ? 'Restore' : 'Archive' }}
                            </button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="blade-inline-11">No products found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($products->hasPages())
    <div class="dashboard-pagination">{{ $products->withQueryString()->links() }}</div>
    @endif
</div>

<!-- Add Product Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-title">Add New Product</div>
        <form method="POST" action="/seller/inventory" enctype="multipart/form-data">
            @csrf
            @include('seller._product-form')
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-coral">Add Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-title">Edit Product</div>
        <form method="POST" id="editForm" enctype="multipart/form-data">
            @csrf @method('PATCH')
            @include('seller._product-form', ['edit' => true])
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-coral">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
@vite('resources/js/views/seller-inventory.js')
@endsection
