@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-products.css'])
@endsection
@section('title', $product->name)

@php
    $actionLabels = ['archived' => 'Archived', 'restored' => 'Restored', 'featured' => 'Featured', 'unfeatured' => 'Unfeatured'];
    $canModerate = auth()->user()->can(\App\Auth\Permission::PRODUCTS_MODERATE);
    $nextStatus = $product->status === 'archived' ? 'active' : 'archived';
@endphp

@section('content')
<p class="product-back"><a href="{{ route('admin.products') }}" class="btn btn-outline btn-sm">← Back to products</a></p>

<div class="product-layout">
    {{-- Product information --}}
    <section class="card" aria-labelledby="info-heading">
        <div class="card-header"><span class="card-title" id="info-heading">Product information</span></div>
        <div class="product-info">
            @if($product->images->isNotEmpty())
                <div class="product-gallery-strip">
                    @foreach($product->images as $image)
                        <img src="{{ $image->url }}" alt="{{ $image->alt_text }}" class="{{ $image->is_primary ? 'is-primary' : '' }}">
                    @endforeach
                </div>
            @endif
            <dl class="product-fields">
                <div><dt>Name</dt><dd>{{ $product->name }}</dd></div>
                <div><dt>Price</dt><dd>₱{{ number_format($product->effective_price, 2) }}@if($product->discount > 0) <span class="oversight-meta">₱{{ number_format($product->price, 2) }} less {{ $product->discount }}%</span>@endif</dd></div>
                <div><dt>Stock</dt><dd>{{ $product->stock }}</dd></div>
                <div><dt>Description</dt><dd class="is-text">{{ $product->description ?: '—' }}</dd></div>
                <div><dt>Listed</dt><dd>{{ $product->created_at->format('M d, Y') }}</dd></div>
            </dl>
        </div>
    </section>

    {{-- Seller + category --}}
    <section class="card" aria-labelledby="seller-heading">
        <div class="card-header"><span class="card-title" id="seller-heading">Seller &amp; category</span></div>
        <dl class="product-fields product-pad">
            <div><dt>Seller</dt><dd>
                {{ $product->seller?->business_name ?? $product->seller?->full_name ?? '—' }}
                @if($product->seller)
                    <span class="badge badge-{{ $product->seller->status }}">{{ $product->seller->status === 'approved' ? 'Active' : ucfirst($product->seller->status) }}</span>
                    @can('viewAccount', $product->seller)<a href="{{ route('admin.users.show', $product->seller) }}" class="oversight-meta">Open seller account</a>@endcan
                @endif
            </dd></div>
            <div><dt>Category</dt><dd>{{ $product->category ?? '—' }}</dd></div>
            <div><dt>Registered line</dt><dd>
                {{ $product->seller?->line_of_business ?? '—' }}
                <span class="badge {{ $categoryMatches ? 'badge-approved' : 'badge-pending' }}">{{ $categoryMatches ? 'Category matches' : 'Review category' }}</span>
            </dd></div>
        </dl>
    </section>

    {{-- Current status + actions --}}
    <section class="card" aria-labelledby="status-heading">
        <div class="card-header">
            <span class="card-title" id="status-heading">Current status</span>
            <span>
                <x-stock-status-badge :product="$product" />
                @if($product->is_featured)<span class="badge badge-approved">Featured</span>@endif
            </span>
        </div>
        <div class="product-pad">
            <p class="oversight-description">
                @if($product->isUnderAdminHold())
                    Archived by Admin on {{ $product->latestStatusModeration->created_at->format('M d, Y') }}. Hidden from buyers; the seller cannot restore it.
                @elseif($product->status === 'archived')
                    Archived by the seller. Hidden from buyers.
                @else
                    Visible to buyers and can be ordered.
                @endif
                @if($openOrders) {{ $openOrders }} open {{ $openOrders === 1 ? 'order is' : 'orders are' }} still being fulfilled; archiving does not cancel them. @endif
            </p>

            @if($canModerate)
                <div class="product-actions-grid">
                    <form method="POST" action="{{ route('admin.products.status', $product) }}" class="product-action-form">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $nextStatus }}">
                        <h3>{{ $nextStatus === 'archived' ? 'Archive product' : 'Restore product' }}</h3>
                        <label class="form-label" for="status-reason">Reason / evidence{{ $nextStatus === 'archived' ? '' : ' (optional)' }}</label>
                        <textarea id="status-reason" name="reason" class="form-control" rows="3" maxlength="1000" @if($nextStatus === 'archived') required minlength="10" @endif
                            @if(old('status') === $nextStatus) @error('reason') aria-invalid="true" aria-describedby="status-reason-error" @enderror @endif
                            placeholder="{{ $nextStatus === 'archived' ? 'e.g. Counterfeit branding reported in complaint #12; listing photos do not match the item.' : '' }}">{{ old('status') === $nextStatus ? old('reason') : '' }}</textarea>
                        @if(old('status') === $nextStatus) @error('reason')<span class="product-error" id="status-reason-error">{{ $message }}</span>@enderror @endif
                        <p class="oversight-meta">The seller is notified{{ $nextStatus === 'archived' ? ' and sees this reason' : '' }}.</p>
                        <button type="submit" class="btn btn-sm {{ $nextStatus === 'archived' ? 'btn-danger' : 'btn-success' }}">{{ $nextStatus === 'archived' ? 'Archive product' : 'Restore product' }}</button>
                    </form>

                    @can('toggleFeatured', $product)
                        <form method="POST" action="{{ route('admin.products.featured', $product) }}" class="product-action-form">
                            @csrf @method('PATCH')
                            <h3>{{ $product->is_featured ? 'Remove from Featured' : 'Feature product' }}</h3>
                            <label class="form-label" for="featured-reason">Reason (optional)</label>
                            <textarea id="featured-reason" name="reason" class="form-control" rows="3" maxlength="1000"></textarea>
                            <button type="submit" class="btn btn-sm {{ $product->is_featured ? 'btn-outline' : 'btn-coral' }}">{{ $product->is_featured ? 'Unfeature' : 'Feature' }}</button>
                        </form>
                    @endcan
                </div>
            @else
                <p class="oversight-description">Moderating products requires the products moderation permission.</p>
            @endif
        </div>
    </section>

    {{-- Moderation history (with reasons) --}}
    <section class="card" aria-labelledby="history-heading">
        <div class="card-header"><span class="card-title" id="history-heading">Moderation history</span></div>
        @if($history->isEmpty())
            <p class="oversight-description product-pad">No admin moderation on record.</p>
        @else
            <ol class="product-timeline">
                @foreach($history as $entry)
                    <li>
                        <span><strong>{{ $actionLabels[$entry->action] ?? ucfirst($entry->action) }}</strong>
                            @if(in_array($entry->action, ['archived', 'restored'], true))<span class="oversight-meta">{{ $entry->from_status }} → {{ $entry->to_status }}</span>@endif
                        </span>
                        <span class="oversight-meta">by {{ $entry->admin?->full_name ?? 'Admin' }} · <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->format('M d, Y h:i A') }}</time></span>
                        @if($entry->reason)<p class="product-reason">{{ $entry->reason }}</p>@endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    {{-- Evidence from buyers --}}
    <section class="card" aria-labelledby="evidence-heading">
        <div class="card-header">
            <span class="card-title" id="evidence-heading">Buyer feedback</span>
            <span class="oversight-description">{{ $reviewStats['count'] }} {{ $reviewStats['count'] === 1 ? 'review' : 'reviews' }}@if($reviewStats['count']) · {{ $reviewStats['average'] }}★ average @endif</span>
        </div>
        @if($lowRatedReviews->isEmpty())
            <p class="oversight-description product-pad">No low-rated (1–2★) reviews.</p>
        @else
            <ul class="product-timeline">
                @foreach($lowRatedReviews as $review)
                    <li>
                        <span><strong>{{ $review->rating }}★</strong> <span class="oversight-meta">{{ $review->buyer?->full_name ?? 'Buyer' }} · {{ $review->created_at->format('M d, Y') }}</span></span>
                        @if($review->body)<p class="product-reason">{{ $review->body }}</p>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Audit --}}
    <section class="card" aria-labelledby="audit-heading">
        <div class="card-header">
            <span class="card-title" id="audit-heading">Audit history</span>
            @can(\App\Auth\Permission::AUDIT_VIEW)<a href="{{ route('admin.audit') }}" class="oversight-description">All audit logs</a>@endcan
        </div>
        @if($auditHistory->isEmpty())
            <p class="oversight-description product-pad">No audited actions on this product.</p>
        @else
            <ul class="product-timeline">
                @foreach($auditHistory as $entry)
                    <li>
                        <span class="audit-action">{{ $entry->action }}</span>
                        <span class="oversight-meta">{{ $entry->actor?->full_name ?? 'System' }} · {{ $entry->created_at?->format('M d, Y h:i A') }}
                            @foreach($entry->changes ?? [] as $field => $change) · {{ $field }}: {{ json_encode($change['from'] ?? null) }} → {{ json_encode($change['to'] ?? null) }}@endforeach
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
