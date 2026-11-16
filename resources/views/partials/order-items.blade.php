@if($order->items->isNotEmpty())
<ul class="order-item-list" aria-label="Order items">
    @foreach($order->items as $line)
    <li>
        @if($line->product)<a href="{{ route('buyer.product', $line->product) }}">{{ $line->product_name }}</a>@else{{ $line->product_name }}@endif
        @if($line->variation_label) · {{ $line->variation_label }}@endif
        × {{ $line->quantity }} — ₱{{ number_format($line->amount, 2) }}
    </li>
    @endforeach
</ul>
@endif
