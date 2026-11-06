@extends('seller.layout')
@section('styles')
@vite(['resources/css/views/seller-dashboard.css', 'resources/css/views/seller-earnings.css', 'resources/css/components/review-moderation.css'])
@endsection
@section('title', 'Reviews')

@section('content')
<div class="earnings-shell">
    <section class="earnings-intro">
        <div>
            <span class="earnings-eyebrow">Buyer feedback</span>
            <h1>Ratings &amp; reviews</h1>
            <p>How buyers rated your products, your service and the delivery of their orders.</p>
        </div>
    </section>

    <section class="earnings-summary" aria-label="Rating overview">
        @foreach(['rating' => 'Product rating', 'seller_rating' => 'Seller service', 'delivery_rating' => 'Delivery'] as $field => $label)
        <article class="kpi-block earnings-summary-card">
            <div class="kpi-title">{{ $label }}</div>
            <strong>{{ $averages[$field]['count'] ? '★ '.number_format($averages[$field]['average'], 1) : '—' }}</strong>
            <small>{{ $averages[$field]['count'] }} {{ Str::plural('rating', $averages[$field]['count']) }}</small>
        </article>
        @endforeach
    </section>

    <section class="earnings-panel reviews-panel">
        @forelse($reviews as $order)
        <article class="review-row">
            <div class="review-row-head">
                <strong>{{ $order->product_name }}</strong>@if($order->variation_label) <span class="review-muted">· {{ $order->variation_label }}</span>@endif
                <span class="review-muted">{{ $order->buyer?->first_name ?? 'Buyer' }} · {{ $order->updated_at->format('M d, Y') }} · <a href="{{ route('seller.orders.show', $order) }}">{{ $order->order_number }}</a></span>
            </div>
            <div class="review-row-stars">
                @foreach(['rating' => 'Product', 'seller_rating' => 'Service', 'delivery_rating' => 'Delivery'] as $field => $label)
                    @if($order->$field)
                    <span><span class="review-muted">{{ $label }}</span> <span class="review-stars" aria-label="{{ $order->$field }} out of 5">{{ str_repeat('★', $order->$field) }}<span class="review-stars-off">{{ str_repeat('★', 5 - $order->$field) }}</span></span></span>
                    @endif
                @endforeach
            </div>
            @if($order->feedback)<p>{{ $order->feedback }}</p>@endif
            @if($order->productReview)
            {{-- The reply shows under the review on the product page. --}}
            <form method="POST" action="{{ route('seller.reviews.reply', $order->productReview) }}" class="review-reply-form">
                @csrf @method('PUT')
                <label class="review-muted" for="reply-{{ $order->productReview->id }}">{{ $order->productReview->seller_reply ? 'Your public reply' : 'Reply publicly' }}</label>
                <textarea id="reply-{{ $order->productReview->id }}" name="seller_reply" class="form-control" rows="2" maxlength="1000" placeholder="Thank the buyer or answer their concern.">{{ $order->productReview->seller_reply }}</textarea>
                <button type="submit" class="btn btn-outline btn-sm">{{ $order->productReview->seller_reply ? 'Update reply' : 'Post reply' }}</button>
            </form>
            @if($order->productReview->hidden_at)
            <p class="review-muted">PickSell hid this review: {{ $order->productReview->hidden_reason }}</p>
            @else
            @include('partials.review-report-form', ['action' => route('seller.reviews.report', $order->productReview), 'id' => $order->productReview->id])
            @endif
            @else
            <p class="review-muted">You can reply once you confirm the delivery and the review is published.</p>
            @endif
        </article>
        @empty
        <p class="review-muted">No ratings yet. Buyers can rate an order once it is delivered.</p>
        @endforelse
        {{ $reviews->links() }}
    </section>
</div>
@endsection
