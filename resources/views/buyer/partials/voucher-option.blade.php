{{--
    One voucher at checkout. Read top to bottom: the amount, the minimum spend (or why it can't
    be used yet), until when it is valid, and how much of it is taken. The stamp is how many more
    times this buyer may use it; the best usable saving is flagged "Recommended".
--}}
@php($voucher = $option['voucher'])
@php($recommended = ($option['recommended'] ?? false) && ! $isPicked)
@php($upcoming = $option['upcoming'] ?? false)
{{-- Anything not usable right now (scheduled for later, min. spend not met, used up) is covered grey. --}}
@php($blocked = (bool) $option['problem'])
<a class="checkout-voucher-option voucher-ticket {{ $isPicked ? 'selected' : '' }} {{ $recommended ? 'is-recommended' : '' }} {{ $option['problem'] ? 'is-unavailable' : '' }} "
   href="{{ $href }}" title="Code {{ $voucher->code }}"
   @if($isPicked) aria-current="true" @endif @if($option['problem']) aria-disabled="true" @endif
   @if($blocked) data-voucher-blocked="{{ $option['problem'] }}" @endif>
    @if($blocked)<span class="voucher-ticket-cover" aria-hidden="true"></span>@endif
    @if($recommended)<span class="voucher-ticket-flag">Recommended</span>
    @elseif($blocked)<span class="voucher-ticket-flag voucher-ticket-flag--off">Can't use</span>@endif
    <div class="checkout-voucher-info">
        <span class="voucher-ticket-amount">{{ $voucher->discount_label }}</span>
        @if($blocked && ! $upcoming)
            {{-- Why not, tied to what it covers: a store voucher only counts that store's items. --}}
            <span class="checkout-voucher-desc voucher-ticket-problem">{{ $option['problem'] }}@isset($scope) <span class="voucher-ticket-scope">({{ $scope }})</span>@endisset</span>
        @else
            <span class="checkout-voucher-desc">{{ (float) $voucher->min_spend > 0 ? 'Min. spend ₱'.rtrim(rtrim(number_format((float) $voucher->min_spend, 2), '0'), '.') : 'No minimum spend' }}</span>
        @endif
        @if($upcoming)
            <span class="checkout-voucher-desc voucher-ticket-problem">Starts {{ $voucher->starts_at->format('M j') }}</span>
        @elseif($voucher->ends_at)<span class="checkout-voucher-desc">Valid until {{ $voucher->ends_at->format('M j, Y') }}</span>@endif
        @include('partials.voucher-usage-bar', ['percent' => $voucher->used_percent])
    </div>
    <div class="voucher-ticket-side">
        @if(($option['uses_left'] ?? 0) > 0)<span class="voucher-ticket-stamp" aria-label="You can use it {{ $option['uses_left'] }} more {{ $option['uses_left'] === 1 ? 'time' : 'times' }}">x{{ $option['uses_left'] }}</span>@endif
        <span class="checkout-voucher-savings">{{ $isPicked ? 'Remove' : ($option['problem'] ? '' : '−₱'.number_format($option['discount'], 2)) }}</span>
    </div>
</a>
