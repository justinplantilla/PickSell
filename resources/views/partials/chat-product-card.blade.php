{{-- The product a message asks about, shown in the conversation where the inquiry starts. Needs $product, $link (nullable), $mine (bool). --}}
<div class="chat-thread-product {{ $mine ? 'is-mine' : '' }}" role="note" aria-label="Asking about {{ $product->name }}">
    @if($product->primary_image)
        <img src="{{ Storage::url($product->primary_image) }}" alt="" loading="lazy">
    @else
        <span class="chat-thread-product-placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</span>
    @endif
    <div class="chat-thread-product-copy">
        <span class="chat-thread-product-kicker">Asking about</span>
        <strong>{{ $product->name }}</strong>
        <span class="chat-thread-product-price">₱{{ number_format($product->effective_price, 2) }}</span>
    </div>
    @if($link)<a href="{{ $link }}" class="chat-thread-product-link">View</a>@endif
</div>
