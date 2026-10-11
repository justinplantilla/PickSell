@extends('buyer.layout')
@section('title', 'My Orders')

@section('styles')
@vite('resources/css/views/buyer-orders.css')
@endsection

@section('content')
<h2 class="blade-inline-1">My Orders</h2>

<div class="status-tabs">
    @foreach(['all'=>'All','pending'=>'To Ship','processing'=>'Preparing','shipped'=>'In Transit','completed'=>'Delivered','returned'=>'Returned','cancelled'=>'Cancelled'] as $val => $label)
    <a href="{{ route('buyer.orders', ['status' => $val]) }}" class="status-tab {{ $status === $val ? 'active' : '' }}" @if($status === $val) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</div>

@forelse($orders as $order)
<div class="order-card">
    <div class="order-card-header">
        <div>
            <span class="blade-inline-2">Order #</span>
            <strong>{{ $order->order_number }}</strong>
            <span class="blade-inline-3">{{ $order->created_at->format('M d, Y') }}</span>
        </div>
        <div class="blade-inline-4">
            <span class="blade-inline-5">{{ $order->seller->business_name ?? $order->seller->full_name }}</span>
            <span class="badge badge-{{ $order->status }}">{{ $order->status }}</span>
        </div>
    </div>
    <div class="order-card-body">
        @if($order->product && $order->product->primary_image)
            <img src="{{ Storage::url($order->product->primary_image) }}" class="blade-inline-6">
        @else
            <div class="blade-inline-7"></div>
        @endif
        <div class="blade-inline-8">
            <div class="blade-inline-9">{{ $order->product_name }}</div>
            <div class="blade-inline-10">Qty: {{ $order->quantity }}</div>
            <div class="blade-inline-11">₱{{ number_format($order->amount, 2) }}</div>
        </div>
        <div class="blade-inline-12">
            @if($order->delivery?->tracking_number)
            <div class="blade-inline-13">Tracking #: <strong>{{ $order->delivery->tracking_number }}</strong></div>
            <a class="btn btn-outline btn-sm" href="{{ route('buyer.orders.tracking', $order) }}">View delivery tracking</a>
            @endif
            @if(in_array($order->status, ['placed', 'confirmed', 'preparing'], true))
            <form method="POST" action="{{ route('buyer.orders.cancel', $order) }}" class="cancel-order-form">
                @csrf @method('PATCH')
                <button type="button" class="btn btn-outline btn-sm" onclick="confirmCancel(this)">Cancel Order</button>
            </form>
            @endif
            @if($order->status === 'completed' && !$order->rating)
            <button class="btn btn-coral btn-sm" onclick="openFeedback({{ $order->id }})">Rate & Review</button>
            @endif
            @if($order->status === 'completed' && $order->rating)
            <div class="blade-inline-14">
                @for($i=1;$i<=5;$i++)<span style="color:{{ $i<=$order->rating ? '#f59e0b' : '#ddd' }};">★</span>@endfor
            </div>
            @endif
        </div>
    </div>

    {{-- Tracking Steps --}}
    @php
        $steps = [
            ['label' => 'Order Placed', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"/></svg>'],
            ['label' => 'Preparing', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.34C18 2.54 15.96.5 13.5.5c-1.3 0-2.48.56-3.33 1.44L9 3.17 7.83 1.94C6.98 1.06 5.8.5 4.5.5 2.04.5 0 2.54 0 4.66c0 .46.11.9.18 1.34H0v2h20V6zm-9.5-3.5c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM4.5 3.5c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM0 20c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V10H0v10zm8-7h8v2H8v-2zm0 4h8v2H8v-2zM4 13h2v2H4v-2zm0 4h2v2H4v-2z"/></svg>'],
            ['label' => $order->status === 'returned' ? 'Returned' : 'In Transit', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>'],
            ['label' => 'Delivered', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>'],
        ];
        $stepStatuses = [
            ['pending', 'placed', 'confirmed'],
            ['processing', 'preparing', 'ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted'],
            ['shipped', 'assigned_to_rider', 'out_for_delivery', 'delivery_failed', 'returned'],
            ['delivered', 'completed'],
        ];
        $currentIdx = false;
        foreach ($stepStatuses as $idx => $statuses) {
            if (in_array($order->status, $statuses, true)) { $currentIdx = $idx; break; }
        }
    @endphp
    @if($order->status !== 'cancelled')
    <div class="blade-inline-15">
        <div class="order-track">
            @foreach($steps as $idx => $step)
            @php $done = $currentIdx !== false && $idx <= $currentIdx; $active = $idx === $currentIdx; @endphp
            @if(!$loop->first)<div class="track-line {{ $done ? 'done' : '' }}"></div>@endif
            <div class="track-step">
                <div class="track-icon {{ $done ? 'done' : '' }} {{ $active ? 'active' : '' }}" @if($active) aria-current="step" @endif>{!! $step['icon'] !!}</div>
                <div class="track-label {{ $done ? 'done' : '' }}">{{ $step['label'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($order->returnRequest)
        @php
            $returnStatus = $order->returnRequest->status;
            $returnBadgeClass = match ($returnStatus) {
                'completed' => 'badge-approved',
                'rejected' => 'badge-cancelled',
                default => 'badge-pending',
            };
        @endphp
        <div class="return-request-summary">
            <div class="return-request-heading">
                <strong>Return / refund</strong>
                <span class="badge {{ $returnBadgeClass }}">{{ ucfirst(str_replace('_', ' ', $returnStatus)) }}</span>
            </div>
            <p>Reason: {{ ucfirst(str_replace('_', ' ', $order->returnRequest->reason)) }}</p>
            @if($order->returnRequest->seller_note)
                <p>Seller response: {{ $order->returnRequest->seller_note }}</p>
            @endif
            <x-return-attachment-gallery :attachments="$order->returnRequest->attachments ?? []" gallery-id="buyer-return-attachments-{{ $order->id }}" />
            @if($order->returnRequest->dispute_status === 'open')
                <p class="return-dispute-note">The seller rejected this request. It has been sent to Admin for dispute review.</p>
            @elseif($order->returnRequest->dispute_status === 'resolved' && $order->returnRequest->admin_notes)
                <p>Admin resolution: {{ $order->returnRequest->admin_notes }}</p>
            @endif
        </div>
    @elseif(in_array($order->status, ['delivered', 'completed'], true))
        <details class="return-request-panel">
            <summary>Request return or refund</summary>
            <form method="POST" action="{{ route('buyer.orders.returns.store', $order) }}" class="return-request-form" enctype="multipart/form-data">
                @csrf
                <label class="form-label" for="return-reason-{{ $order->id }}">Reason</label>
                <select class="form-control" id="return-reason-{{ $order->id }}" name="reason" required>
                    <option value="">Choose a reason</option>
                    <option value="damaged">Item arrived damaged</option>
                    <option value="wrong_item">Wrong item received</option>
                    <option value="wrong_size">Wrong size</option>
                    <option value="not_as_described">Item not as described</option>
                    <option value="other">Other</option>
                </select>
                <label class="form-label" for="return-quantity-{{ $order->id }}">Quantity to return</label>
                <input class="form-control" type="number" id="return-quantity-{{ $order->id }}" name="quantity" min="1" max="{{ $order->quantity }}" value="{{ $order->quantity }}" required>
                <label class="form-label" for="return-details-{{ $order->id }}">What happened?</label>
                <textarea class="form-control" id="return-details-{{ $order->id }}" name="details" rows="3" maxlength="2000" required></textarea>
                <label class="form-label" for="return-attachments-{{ $order->id }}">Photos or videos (up to 5)</label>
                <input class="form-control" type="file" id="return-attachments-{{ $order->id }}" name="attachments[]" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm" multiple>
                <button class="btn btn-coral btn-sm" type="submit">Submit return request</button>
            </form>
        </details>
    @endif
</div>
@empty
<div class="blade-inline-16">No orders found.</div>
@endforelse

@if($orders->hasPages())
<div class="dashboard-pagination blade-inline-17">{{ $orders->withQueryString()->links() }}</div>
@endif

<!-- Feedback Modal -->
<div class="modal-overlay" id="feedbackModal">
    <div class="modal">
        <div class="blade-inline-18">Rate Your Order</div>
        <form method="POST" id="feedbackForm">
            @csrf
            <div class="form-group">
                <label class="form-label">Rating</label>
                <div class="star-rating" id="starRating">
                    @for($i=1;$i<=5;$i++)
                    <span class="star" data-val="{{ $i }}" onclick="setRating({{ $i }})">★</span>
                    @endfor
                </div>
                <input type="hidden" name="rating" id="ratingInput" required>
            </div>
            <div class="form-group">
                <label class="form-label">Review (optional)</label>
                <textarea name="feedback" class="form-control" rows="3" placeholder="Share your experience..."></textarea>
            </div>
            <div class="blade-inline-19">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('feedbackModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-coral">Submit</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
@vite('resources/js/views/buyer-orders.js')
@vite('resources/js/components/return-attachment-lightbox.js')
@endsection
