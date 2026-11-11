@extends('buyer.layout')
@section('title', 'Delivery Tracking')

@section('styles')
@vite('resources/css/views/buyer-orders.css')
@endsection

@section('content')
@php
    // Pickrush lists parcel events newest first; the timeline reads oldest to newest.
    $events = collect($tracking['events'] ?? [])->reverse()->values();
    $currentStatus = $order->status;

    $assuranceMap = [
        'placed'             => ['next' => 'Waiting for the seller to confirm and start packing your order.', 'icon' => 'bag'],
        'pending'            => ['next' => 'Waiting for the seller to confirm and start packing your order.', 'icon' => 'bag'],
        'confirmed'          => ['next' => 'The seller has confirmed your order and will begin preparing it shortly.', 'icon' => 'check'],
        'preparing'          => ['next' => 'Your order is being packed. It will be handed over to the courier soon.', 'icon' => 'box'],
        'processing'         => ['next' => 'Your order is being processed and will be ready for pickup shortly.', 'icon' => 'box'],
        'ready_for_pickup'   => ['next' => 'Your package is ready! The courier will pick it up very soon.', 'icon' => 'tag'],
        'picked_up'          => ['next' => 'The courier has picked up your package and is heading to the sorting center.', 'icon' => 'truck'],
        'at_sorting_center'  => ['next' => 'Your package is at the sorting center and will be dispatched to your area soon.', 'icon' => 'hub'],
        'sorted'             => ['next' => 'Your package has been sorted and is on its way to your local delivery hub.', 'icon' => 'hub'],
        'shipped'            => ['next' => 'Your package is on its way! A rider will be assigned to deliver it to you.', 'icon' => 'truck'],
        'assigned_to_rider'  => ['next' => 'A rider has been assigned and will pick up your package for delivery.', 'icon' => 'rider'],
        'out_for_delivery'   => ['next' => 'Your package is out for delivery right now — keep an eye out for the rider!', 'icon' => 'pin'],
        'delivery_failed'    => ['next' => 'Delivery was attempted but unsuccessful. The rider will try again soon.', 'icon' => 'alert'],
        'delivered'          => ['next' => 'Your package has been delivered. Enjoy your order!', 'icon' => 'home'],
        'completed'          => ['next' => 'Order completed. Thank you for shopping with PickSell!', 'icon' => 'star'],
        'returned'           => ['next' => 'Your return is being processed. We\'ll keep you updated.', 'icon' => 'return'],
        'cancelled'          => ['next' => 'This order has been cancelled.', 'icon' => 'x'],
    ];
    $assurance = $assuranceMap[$currentStatus] ?? ['next' => 'Your order is being processed.', 'icon' => 'box'];
    // Line icons (24×24, drawn in the current text colour) instead of emoji.
    $statusIcons = [
        'bag' => '<path d="M6 7h12l1 13H5L6 7Z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'box' => '<path d="m3 7 9-4 9 4-9 4-9-4Z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>',
        'tag' => '<path d="M3 12V4h8l10 10-8 8L3 12Z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
        'truck' => '<path d="M2 6h11v10H2z"/><path d="M13 9h4l4 4v3h-8"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'hub' => '<path d="M3 21V9l9-5 9 5v12"/><path d="M8 21v-7h8v7"/>',
        'rider' => '<circle cx="5.5" cy="17.5" r="3"/><circle cx="18.5" cy="17.5" r="3"/><path d="M5.5 17.5 9 10h5l4.5 7.5"/><path d="M14 10 12 6h-2"/>',
        'pin' => '<path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/>',
        'alert' => '<path d="M12 3 2 21h20L12 3Z"/><path d="M12 10v5"/><path d="M12 18h.01"/>',
        'home' => '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/><path d="m9 15 2 2 4-4"/>',
        'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9L12 3Z"/>',
        'return' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
        'x' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
    ];
@endphp

<div class="tracking-page">
    <p><a class="btn btn-outline btn-sm" href="{{ route('buyer.orders') }}">← Back to my orders</a></p>

    <section class="order-card" aria-labelledby="tracking-heading">
        <div class="order-card-header">
            <div>
                <span class="orders-muted-text">Delivery tracking</span>
                <h1 id="tracking-heading" class="tracking-title">{{ $order->order_number }}</h1>
            </div>
            <span class="badge badge-{{ $currentStatus }}">{{ ucfirst(str_replace('_', ' ', $currentStatus)) }}</span>
        </div>
        <div class="order-card-body tracking-summary">
            <div>
                <strong>{{ $order->product_name }}</strong>
                @if($order->variation_label)<span class="tracking-meta">{{ $order->variation_label }}</span>@endif
                <span class="tracking-meta">Quantity: {{ $order->quantity }}</span>
            </div>
            <div>
                <span class="tracking-meta">Tracking number</span>
                <strong>{{ $tracking['tracking_no'] ?? 'Not available' }}</strong>
            </div>
            <div>
                <span class="tracking-meta">Delivering to</span>
                <strong>{{ collect([$order->buyer?->house_no, $order->buyer?->street, $order->buyer?->barangay, $order->buyer?->municipality, $order->buyer?->province])->filter()->join(', ') ?: 'Address unavailable' }}</strong>
            </div>
            @if($order->shipment)
                <div>
                    <span class="tracking-meta">Delivery fee (one parcel for this seller's items)</span>
                    <strong>₱{{ number_format((float) $order->shipment->delivery_fee, 2) }}</strong>
                    <span class="tracking-meta">Cash to pay on delivery: ₱{{ number_format((float) ($order->shipment->activeOrders()->sum('amount')) + (float) $order->shipment->delivery_fee, 2) }}</span>
                </div>
            @endif
        </div>

        {{-- Assurance banner --}}
        @if($order->status !== 'cancelled')
        <div class="tracking-assurance">
            <span class="tracking-assurance-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $statusIcons[$assurance['icon']] ?? $statusIcons['box'] !!}</svg></span>
            <span>{{ $assurance['next'] }}</span>
        </div>
        @endif
    </section>

    @include('partials.delivery-proof', ['order' => $order])
    @if($order->status === 'delivered')
        <form method="POST" action="{{ route('buyer.orders.received', $order) }}" style="margin:12px 0" data-no-confirm>
            @csrf @method('PATCH')
            <button type="submit" class="btn btn-coral btn-sm">Order Received</button>
            <span style="font-size:.82rem;color:#777;margin-left:8px">Got your parcel? Confirm it, then rate it.@if($autoAt = \App\Services\Orders\OrderReceipt::autoConfirmAt($order)) We’ll confirm it for you on {{ $autoAt->format('M d, Y') }}.@endif</span>
        </form>
    @endif

    <section class="order-card tracking-events" aria-labelledby="tracking-events-heading">
        <div class="order-card-header">
            <h2 id="tracking-events-heading">Delivery updates</h2>
        </div>
        @if($events->isEmpty())
            <p class="tracking-empty">No delivery scans or status updates have been recorded yet.</p>
        @else
            <ol class="tracking-timeline">
                @foreach($events as $event)
                    <li class="tracking-event {{ $loop->last ? 'is-latest' : '' }}" @if($loop->last) aria-current="step" @endif>
                        <span class="tracking-event-marker" aria-hidden="true"></span>
                        <div class="tracking-event-content">
                            <div class="tracking-event-heading">
                                <strong>{{ $event['headline'] }}</strong>
                                <time datetime="{{ $event['at']->toIso8601String() }}">{{ $event['at']->format('M d, Y h:i A') }}</time>
                            </div>
                            <span class="tracking-event-actor">{{ $order->carrierName() }}</span>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    <section class="order-card" aria-label="Delivery problem">
        @include('partials.delivery-problem', ['order' => $order, 'role' => 'buyer'])
    </section>

    @if($order->returnRequest)
        <section class="return-request-summary" aria-label="Related return or refund">
            <div class="return-request-heading">
                <strong>Related return / refund request</strong>
                <span class="badge badge-pending">{{ ucfirst(str_replace('_', ' ', $order->returnRequest->status)) }}</span>
            </div>
            <p>{{ ucfirst(str_replace('_', ' ', $order->returnRequest->reason)) }}</p>
            @if($order->returnRequest->dispute_status === 'open')
                <p class="return-dispute-note">This request is under dispute review.</p>
            @endif
        </section>
    @endif

    {{-- Cancellation experience rating --}}
    @if($order->status === 'cancelled')
    <section class="order-card tracking-cancel-rating" aria-label="Cancellation feedback">
        <div class="order-card-header">
            <h2>How was your experience?</h2>
        </div>
        <div class="card-body">
            @if($order->rating)
                <div class="tracking-cancel-rated">
                    <div><x-star-rating :value="$order->rating" style="font-size:1.8rem" /></div>
                    <p>Thanks for your feedback!</p>
                    @if($order->feedback)<p class="tracking-cancel-feedback-text">"{{ $order->feedback }}"</p>@endif
                </div>
            @else
                <p class="tracking-cancel-hint">We're sorry your order was cancelled. Let us know how we can do better.</p>
                <form method="POST" action="{{ route('buyer.feedback', $order) }}" class="tracking-cancel-form">
                    @csrf
                    <div class="star-rating" id="cancelStarRating">
                        @for($i=1;$i<=5;$i++)
                        <button type="button" class="star" data-val="{{ $i }}" aria-label="{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}">★</button>
                        @endfor
                    </div>
                    <input type="hidden" name="rating" id="cancelRatingInput" required>
                    <textarea name="feedback" class="form-control" rows="3" placeholder="Tell us what happened or how we can improve (optional)..." style="margin-top:0.75rem;"></textarea>
                    <button type="submit" class="btn btn-coral btn-sm" style="margin-top:0.75rem;" id="cancelRatingSubmit" disabled>Submit Feedback</button>
                </form>
            @endif
        </div>
    </section>
    @endif
</div>
@endsection

@section('scripts')
<script>
function setCancelRating(val) {
    document.getElementById('cancelRatingInput').value = val;
    document.querySelectorAll('#cancelStarRating .star').forEach(s => {
        s.classList.toggle('active', parseInt(s.dataset.val) <= val);
    });
    document.getElementById('cancelRatingSubmit').disabled = false;
}
document.querySelectorAll('#cancelStarRating .star').forEach(star => star.addEventListener('click', () => setCancelRating(Number(star.dataset.val))));
</script>
@endsection
