@extends('buyer.layout')
@section('title', 'My Orders')

@section('styles')
@vite('resources/css/views/buyer-orders.css')
@endsection

@section('content')
<h2 class="blade-inline-1">My Orders</h2>

<div class="status-tabs">
    @foreach(['all'=>'All','pending'=>'To Ship','processing'=>'Preparing','shipped'=>'In Transit','completed'=>'Delivered','cancelled'=>'Cancelled'] as $val => $label)
    <a href="/buyer/orders?status={{ $val }}" class="status-tab {{ $status === $val ? 'active' : '' }}">{{ $label }}</a>
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
        @if($order->product && $order->product->image)
            <img src="{{ Storage::url($order->product->image) }}" class="blade-inline-6">
        @else
            <div class="blade-inline-7"></div>
        @endif
        <div class="blade-inline-8">
            <div class="blade-inline-9">{{ $order->product_name }}</div>
            <div class="blade-inline-10">Qty: {{ $order->quantity }}</div>
            <div class="blade-inline-11">₱{{ number_format($order->amount, 2) }}</div>
        </div>
        <div class="blade-inline-12">
            @if($order->waybill_number)
            <div class="blade-inline-13">Waybill: <strong>{{ $order->waybill_number }}</strong></div>
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
        $steps = ['pending'=>'To Ship','processing'=>'Preparing','shipped'=>'In Transit','completed'=>'Delivered'];
        $stepKeys = array_keys($steps);
        $currentIdx = array_search($order->status, $stepKeys);
    @endphp
    @if($order->status !== 'cancelled')
    <div class="blade-inline-15">
        <div class="order-track">
            @foreach($steps as $key => $label)
            @php $idx = array_search($key, $stepKeys); $done = $currentIdx !== false && $idx <= $currentIdx; @endphp
            @if(!$loop->first)<div class="track-line {{ $done ? 'done' : '' }}"></div>@endif
            <div class="track-step">
                <div class="track-dot {{ $done ? 'done' : '' }}"></div>
                <div class="track-label {{ $done ? 'done' : '' }}">{{ $label }}</div>
            </div>
            @endforeach
        </div>
    </div>
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
@endsection
