{{-- Product summary and quick moderation actions, loaded into the side panel on the products list. --}}
@php
    $canModerate = auth()->user()->can(\App\Auth\Permission::PRODUCTS_MODERATE);
    $image = $product->primary_image;
@endphp
<div class="list-preview product-preview">
    @if($image)
        <img src="{{ Storage::url($image) }}" alt="" class="product-preview-image">
    @else
        <div class="product-preview-image is-empty" aria-hidden="true"><svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg></div>
    @endif
    @if($product->images->count() > 1)<p class="oversight-meta">{{ $product->images->count() }} photos</p>@endif

    <p class="list-preview-kicker">Product</p>
    <h2 id="list-drawer-title">{{ $product->name }}</h2>
    <div class="list-preview-status">
        <span class="badge {{ $product->status === 'active' ? 'badge-approved' : 'badge-deactivated' }}">{{ $product->status === 'active' ? 'Active' : 'Archived' }}</span>
        <x-stock-status-badge :product="$product" />
        @if($product->is_featured)<span class="product-star is-on"><span aria-hidden="true">★</span> Featured</span>@endif
    </div>
    @if($attention)<p class="oversight-flag">{{ implode(' · ', $attention) }}</p>@endif
    @if($product->isUnderAdminHold())<p class="oversight-meta">Archived by Admin. Only an Admin can restore it.</p>@endif

    <dl class="product-fields">
        <div><dt>Price</dt><dd class="oversight-number">₱{{ number_format($product->effective_price, 2) }}@if($product->discount > 0) <span class="oversight-meta">₱{{ number_format($product->price, 2) }} before {{ rtrim(rtrim(number_format($product->discount, 2), '0'), '.') }}% off</span>@endif</dd></div>
        <div><dt>Stock</dt><dd>{{ number_format($product->stock) }}</dd></div>
        <div><dt>Category</dt><dd>{{ $product->category ?? '—' }}</dd></div>
        <div><dt>Seller</dt><dd>{{ $product->seller?->business_name ?? $product->seller?->full_name ?? '—' }}@if($product->seller?->line_of_business)<span class="oversight-meta">Registered line: {{ $product->seller->line_of_business }}</span>@endif</dd></div>
        <div><dt>Listed</dt><dd>{{ $product->created_at->format('M d, Y') }} · {{ $product->created_at->diffForHumans() }}</dd></div>
    </dl>

    @if($canModerate)
        <h3 class="list-preview-heading">Quick actions</h3>
        <div class="product-quick-actions">
            @if($product->status === 'active')
                <form method="POST" action="{{ route('admin.products.status', $product) }}" class="product-action-form" data-no-confirm>
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="archived">
                    <label class="form-label" for="preview-archive-reason">Archive · reason shown to seller</label>
                    <textarea id="preview-archive-reason" name="reason" class="form-control" rows="2" minlength="10" maxlength="1000" required></textarea>
                    <button type="submit" class="btn btn-coral btn-sm">Archive product</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.products.status', $product) }}" class="product-action-form" data-no-confirm>
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="active">
                    <label class="form-label" for="preview-restore-reason">Restore · note to seller (optional)</label>
                    <textarea id="preview-restore-reason" name="reason" class="form-control" rows="2" maxlength="1000"></textarea>
                    <button type="submit" class="btn btn-coral btn-sm">Restore product</button>
                </form>
            @endif
            @if($product->is_featured || $product->status === 'active')
                <form method="POST" action="{{ route('admin.products.featured', $product) }}" class="product-action-form"
                      data-confirm="{{ $product->is_featured ? 'Remove this product from Featured Products?' : 'Feature this product?' }}">
                    @csrf
                    @method('PATCH')
                    <label class="form-label" for="preview-feature-reason">{{ $product->is_featured ? 'Unfeature' : 'Feature' }} · note (optional)</label>
                    <input id="preview-feature-reason" name="reason" class="form-control" maxlength="1000">
                    <button type="submit" class="btn btn-outline btn-sm">{{ $product->is_featured ? 'Remove from Featured' : '★ Feature product' }}</button>
                </form>
            @endif
        </div>
    @endif

    <h3 class="list-preview-heading">Moderation history</h3>
    @if($history->isEmpty())
        <p class="oversight-meta">No moderation actions yet.</p>
    @else
        <ol class="product-timeline">
            @foreach($history as $log)
                <li>
                    <strong>{{ ucfirst(str_replace('_', ' ', $log->action)) }}</strong>
                    <span class="oversight-meta">{{ $log->created_at->format('M d, h:i A') }} · {{ $log->admin?->full_name ?? 'Admin' }}</span>
                    @if($log->reason)<p class="product-reason">{{ $log->reason }}</p>@endif
                </li>
            @endforeach
        </ol>
    @endif

    <a href="{{ route('admin.products.show', $product) }}" class="btn btn-coral list-preview-open">Open full product</a>
</div>
