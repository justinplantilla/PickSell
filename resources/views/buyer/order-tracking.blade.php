@extends('buyer.layout')
@section('title', 'Delivery Tracking')

@section('styles')
@vite('resources/css/views/buyer-orders.css')
@endsection

@section('content')
@php
    $delivery = $order->delivery;
    $logs = $delivery?->logs ?? collect();
    $currentStatus = $delivery?->status ?? $order->status;

    $assuranceMap = [
        'placed'             => ['next' => 'Waiting for the seller to confirm and start packing your order.', 'icon' => '🛍️'],
        'pending'            => ['next' => 'Waiting for the seller to confirm and start packing your order.', 'icon' => '🛍️'],
        'confirmed'          => ['next' => 'The seller has confirmed your order and will begin preparing it shortly.', 'icon' => '✅'],
        'preparing'          => ['next' => 'Your order is being packed. It will be handed over to the courier soon.', 'icon' => '📦'],
        'processing'         => ['next' => 'Your order is being processed and will be ready for pickup shortly.', 'icon' => '⚙️'],
        'ready_for_pickup'   => ['next' => 'Your package is ready! The courier will pick it up very soon.', 'icon' => '🏷️'],
        'picked_up'          => ['next' => 'The courier has picked up your package and is heading to the sorting center.', 'icon' => '🚚'],
        'at_sorting_center'  => ['next' => 'Your package is at the sorting center and will be dispatched to your area soon.', 'icon' => '🏭'],
        'sorted'             => ['next' => 'Your package has been sorted and is on its way to your local delivery hub.', 'icon' => '📬'],
        'shipped'            => ['next' => 'Your package is on its way! A rider will be assigned to deliver it to you.', 'icon' => '🚀'],
        'assigned_to_rider'  => ['next' => 'A rider has been assigned and will pick up your package for delivery.', 'icon' => '🏍️'],
        'out_for_delivery'   => ['next' => 'Your package is out for delivery right now — keep an eye out for the rider!', 'icon' => '📍'],
        'delivery_failed'    => ['next' => 'Delivery was attempted but unsuccessful. The rider will try again soon.', 'icon' => '⚠️'],
        'delivered'          => ['next' => 'Your package has been delivered. Enjoy your order!', 'icon' => '🎉'],
        'completed'          => ['next' => 'Order completed. Thank you for shopping with PickSell!', 'icon' => '⭐'],
        'returned'           => ['next' => 'Your return is being processed. We\'ll keep you updated.', 'icon' => '↩️'],
        'cancelled'          => ['next' => 'This order has been cancelled.', 'icon' => '❌'],
    ];
    $assurance = $assuranceMap[$currentStatus] ?? ['next' => 'Your order is being processed.', 'icon' => '📦'];
@endphp

<div class="tracking-page">
    <p><a class="btn btn-outline btn-sm" href="{{ route('buyer.orders') }}">← Back to my orders</a></p>

    <section class="order-card" aria-labelledby="tracking-heading">
        <div class="order-card-header">
            <div>
                <span class="blade-inline-2">Delivery tracking</span>
                <h1 id="tracking-heading" class="tracking-title">{{ $order->order_number }}</h1>
            </div>
            <span class="badge badge-{{ $currentStatus }}">{{ ucfirst(str_replace('_', ' ', $currentStatus)) }}</span>
        </div>
        <div class="order-card-body tracking-summary">
            <div>
                <strong>{{ $order->product_name }}</strong>
                <span class="tracking-meta">Quantity: {{ $order->quantity }}</span>
            </div>
            <div>
                <span class="tracking-meta">Tracking number</span>
                <strong>{{ $delivery?->tracking_number ?? 'Not available' }}</strong>
            </div>
            <div>
                <span class="tracking-meta">Delivering to</span>
                <strong>{{ collect([$order->buyer?->house_no, $order->buyer?->street, $order->buyer?->barangay, $order->buyer?->municipality, $order->buyer?->province])->filter()->join(', ') ?: 'Address unavailable' }}</strong>
            </div>
        </div>

        {{-- Assurance banner --}}
        @if($order->status !== 'cancelled')
        <div class="tracking-assurance">
            <span class="tracking-assurance-icon">{{ $assurance['icon'] }}</span>
            <span>{{ $assurance['next'] }}</span>
        </div>
        @endif
    </section>

    <section class="order-card tracking-events" aria-labelledby="tracking-events-heading">
        <div class="order-card-header">
            <h2 id="tracking-events-heading">Delivery updates</h2>
        </div>
        @if($logs->isEmpty())
            <p class="tracking-empty">No delivery scans or status updates have been recorded yet.</p>
        @else
            <ol class="tracking-timeline">
                @foreach($logs as $log)
                    @php
                        $logAssurance = $assuranceMap[$log->to_status] ?? null;
                    @endphp
                    <li class="tracking-event {{ $loop->last ? 'is-latest' : '' }}" @if($loop->last) aria-current="step" @endif>
                        <span class="tracking-event-marker" aria-hidden="true"></span>
                        <div class="tracking-event-content">
                            <div class="tracking-event-heading">
                                <strong>{{ ucfirst(str_replace('_', ' ', $log->to_status)) }}</strong>
                                <time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('M d, Y h:i A') }}</time>
                            </div>
                            @if($log->note)<p>{{ $log->note }}</p>@endif
                            @if($logAssurance && !$loop->last)
                                <p class="tracking-event-assurance">{{ $logAssurance['next'] }}</p>
                            @endif
                            <span class="tracking-event-actor">{{ ucfirst($log->actor_role) }}</span>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
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
                    <div class="star-rating">
                        @for($i=1;$i<=5;$i++)
                        <span class="star {{ $i <= $order->rating ? 'active' : '' }}">★</span>
                        @endfor
                    </div>
                    <p>Thanks for your feedback!</p>
                    @if($order->feedback)<p class="tracking-cancel-feedback-text">"{{ $order->feedback }}"</p>@endif
                </div>
            @else
                <p class="tracking-cancel-hint">We're sorry your order was cancelled. Let us know how we can do better.</p>
                <form method="POST" action="{{ route('buyer.orders.feedback', $order) }}" class="tracking-cancel-form">
                    @csrf
                    <div class="star-rating" id="cancelStarRating">
                        @for($i=1;$i<=5;$i++)
                        <span class="star" data-val="{{ $i }}" onclick="setCancelRating({{ $i }})">★</span>
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
window.setCancelRating = setCancelRating;
</script>
@endsection
