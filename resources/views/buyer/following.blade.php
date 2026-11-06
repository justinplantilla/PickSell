@extends('buyer.layout')
@section('styles')
@vite('resources/css/views/buyer-recommended.css')
@endsection
@section('title', 'Following')

@section('content')
<div class="rec-page">
    <div class="rec-header">
        <h1>Following</h1>
        <p>Stores you follow. You get a notification when they list something new.</p>
    </div>

    @if($stores->isEmpty())
        <p class="wishlist-empty">You don't follow any stores yet. Tap <strong>+ Follow</strong> on a store or product page.</p>
        <a href="{{ route('buyer.home') }}" class="btn btn-coral">Browse products</a>
    @else
        <ul class="following-list">
            @foreach($stores as $store)
            <li class="following-card">
                <a href="{{ route('buyer.seller', $store) }}" class="following-avatar" aria-hidden="true" tabindex="-1">{{ strtoupper(substr($store->business_name ?: $store->first_name, 0, 1)) }}</a>
                <div class="following-copy">
                    <a href="{{ route('buyer.seller', $store) }}" class="following-name">{{ $store->business_name ?? $store->full_name }}</a>
                    <x-seller-stats :seller="$store" />
                    <small>{{ $store->active_products_count }} {{ Str::plural('product', $store->active_products_count) }}</small>
                </div>
                <div class="following-actions">
                    <x-follow-seller-button :seller="$store" />
                    <a href="/buyer/chat?user={{ $store->id }}" class="btn btn-outline btn-sm">Chat</a>
                </div>
            </li>
            @endforeach
        </ul>
        {{ $stores->links() }}
    @endif
</div>
@endsection
