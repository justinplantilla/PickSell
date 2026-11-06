@extends('seller.layout')
@section('styles')
@vite(['resources/css/views/seller-dashboard.css', 'resources/css/views/seller-earnings.css'])
@endsection
@section('title', 'Vouchers')

@section('content')
<div class="earnings-shell">
    <section class="earnings-intro">
        <div>
            <span class="earnings-eyebrow">Promotions</span>
            <h1>Store vouchers</h1>
            <p>Codes buyers can use on your items at checkout, e.g. ₱50 off orders over ₱500. You pay for the discount: the orders are saved at the lower amount, and commission is taken on that.</p>
        </div>
    </section>

    <section class="earnings-panel vouchers-panel">
        <details class="voucher-new" @if($errors->any() && ! old('_method')) open @endif>
            <summary class="btn btn-coral btn-sm">+ New voucher</summary>
            @include('partials.voucher-form', ['voucher' => null, 'action' => route('seller.vouchers.store')])
        </details>

        @forelse($vouchers as $voucher)
        <details class="voucher-row">
            <summary>
                <span class="voucher-code">{{ $voucher->code }}</span>
                <span class="voucher-main"><strong>{{ $voucher->title }}</strong><small>{{ $voucher->summary }}</small></span>
                <span class="voucher-meta">
                    @php $live = $voucher->is_active && (! $voucher->ends_at || $voucher->ends_at->isFuture()) && (! $voucher->starts_at || $voucher->starts_at->isPast()); @endphp
                    <span class="voucher-state {{ $live ? 'is-live' : '' }}">{{ ! $voucher->is_active ? 'Off' : ($voucher->starts_at?->isFuture() ? 'Starts '.$voucher->starts_at->format('M j') : ($voucher->ends_at?->isPast() ? 'Ended' : 'Live')) }}</span>
                    <small>{{ $voucher->redemptions_count }} used{{ $voucher->usage_limit ? ' of '.$voucher->usage_limit : '' }} · −₱{{ number_format((float) $voucher->redemptions_sum_amount, 2) }}</small>
                </span>
            </summary>
            @include('partials.voucher-form', ['voucher' => $voucher, 'action' => route('seller.vouchers.update', $voucher)])
            <form method="POST" action="{{ route('seller.vouchers.destroy', $voucher) }}" class="voucher-delete">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline btn-sm">{{ $voucher->redemptions_count ? 'Switch off' : 'Delete' }}</button>
            </form>
        </details>
        @empty
        <p class="review-muted">No vouchers yet. Create one to give buyers a reason to check out.</p>
        @endforelse
    </section>
</div>
@endsection
