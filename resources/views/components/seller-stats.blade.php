@props(['seller'])
{{-- A store's standing, as buyers see it on the storefront and on its products. --}}
@php
    $rating = $seller->sellerRating();
    $followers = $seller->followers()->count();
@endphp
<span {{ $attributes->merge(['class' => 'seller-stats']) }}>
    @if(app(\App\Services\SellerPerformance::class)->isPreferred($seller))<span class="seller-preferred" title="Highly rated, reliable seller">✓ Preferred Seller</span>@endif
    <span>@if($rating['count'])<b class="seller-stats-star" aria-hidden="true">★</b> {{ number_format($rating['average'], 1) }} <small>({{ $rating['count'] }})</small>@else No ratings yet @endif</span>
    <span>{{ number_format($followers) }} {{ Str::plural('follower', $followers) }}</span>
    <span>Joined {{ $seller->created_at?->format('M Y') ?? '—' }}</span>
</span>
