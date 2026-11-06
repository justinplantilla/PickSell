@extends('admin.layout')
@section('styles')
@vite('resources/css/components/vouchers.css')
@endsection
@section('title', 'Vouchers & Campaigns')

@section('content')
<div class="card">
    <div class="card-header"><span class="card-title">PickSell vouchers &amp; campaigns</span></div>
    <div class="card-body">
        <p class="voucher-intro">Vouchers PickSell pays for, e.g. "Free shipping weekend" or ₱100 off orders over ₱1,000. Sellers keep their full sale; the discount comes off what the rider collects. Buyers can use one PickSell voucher per checkout, on top of store vouchers. {{ $storeVouchers }} store {{ Str::plural('voucher', $storeVouchers) }} from sellers {{ $storeVouchers === 1 ? 'is' : 'are' }} live right now.</p>
        <div class="vouchers-panel">
            @can(\App\Auth\Permission::SETTINGS_MANAGE)
            <details class="voucher-new" @if($errors->any() && ! old('_method')) open @endif>
                <summary class="btn btn-coral btn-sm">+ New voucher or campaign</summary>
                @include('partials.voucher-form', ['voucher' => null, 'action' => route('admin.vouchers.store')])
            </details>
            @endcan

            @forelse($vouchers as $voucher)
            <details class="voucher-row">
                <summary>
                    <span class="voucher-code">{{ $voucher->code }}</span>
                    <span class="voucher-main"><strong>{{ $voucher->title }}</strong><small>{{ $voucher->summary }}@if($voucher->starts_at || $voucher->ends_at) · {{ $voucher->starts_at?->format('M j, g:i A') ?? 'now' }} – {{ $voucher->ends_at?->format('M j, g:i A') ?? 'no end' }}@endif</small></span>
                    <span class="voucher-meta">
                        @php $live = $voucher->is_active && (! $voucher->ends_at || $voucher->ends_at->isFuture()) && (! $voucher->starts_at || $voucher->starts_at->isPast()); @endphp
                        <span class="voucher-state {{ $live ? 'is-live' : '' }}">{{ ! $voucher->is_active ? 'Off' : ($voucher->starts_at?->isFuture() ? 'Scheduled' : ($voucher->ends_at?->isPast() ? 'Ended' : 'Live')) }}</span>
                        <small>{{ $voucher->redemptions_count }} used{{ $voucher->usage_limit ? ' of '.$voucher->usage_limit : '' }} · PickSell paid ₱{{ number_format((float) $voucher->redemptions_sum_amount, 2) }}</small>
                    </span>
                </summary>
                @can(\App\Auth\Permission::SETTINGS_MANAGE)
                @include('partials.voucher-form', ['voucher' => $voucher, 'action' => route('admin.vouchers.update', $voucher)])
                <form method="POST" action="{{ route('admin.vouchers.destroy', $voucher) }}" class="voucher-delete">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline btn-sm">{{ $voucher->redemptions_count ? 'Switch off' : 'Delete' }}</button>
                </form>
                @endcan
            </details>
            @empty
            <p class="voucher-intro">No PickSell vouchers yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
