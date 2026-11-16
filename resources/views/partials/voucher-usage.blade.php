{{-- Voucher list row, right side: "Starts …" while scheduled, and how much of the usage limit is used. --}}
<span class="voucher-meta">
    @if($voucher->is_active && $voucher->starts_at?->isFuture())
        <span class="voucher-state">Starts {{ $voucher->starts_at->format('M j') }}</span>
    @endif
    @include('partials.voucher-usage-bar', ['percent' => $voucher->used_percent])
</span>
