{{-- Order summary loaded into the side panel on the orders list. --}}
<div class="list-preview">
    <p class="list-preview-kicker">Order</p>
    <h2 id="list-drawer-title">{{ $order->order_number }}</h2>
    <div class="list-preview-status">
        <x-admin-order-status :status="$order->status" />
        @if($isStuck)<span class="oversight-flag">Stuck · no update for {{ \App\Services\Admin\AdminDashboardService::STUCK_AFTER_HOURS }}h+</span>@endif
    </div>
    @if($order->tracking_status)<p class="oversight-meta">{{ $order->tracking_status }}</p>@endif

    <dl class="order-fields">
        <div><dt>Product</dt><dd>{{ $order->product_name }} × {{ $order->quantity }}</dd></div>
        <div><dt>Amount</dt><dd class="oversight-number">₱{{ number_format($order->amount, 2) }}</dd></div>
        <div><dt>Buyer</dt><dd>{{ $order->buyer?->full_name ?? '—' }}</dd></div>
        <div><dt>Seller</dt><dd>{{ $order->seller?->business_name ?? $order->seller?->full_name ?? '—' }}</dd></div>
        <div><dt>Rider</dt><dd>{{ $order->courier?->full_name ?? ($order->status === 'sorted' ? 'Not assigned yet' : '—') }}</dd></div>
        <div><dt>Waybill</dt><dd>@if($order->waybill_number)<code>{{ $order->waybill_number }}</code>@else — @endif</dd></div>
        @if($order->originBranch || $order->destinationBranch)
            <div><dt>Route</dt><dd>{{ $order->originBranch?->name ?? '—' }} → {{ $order->destinationBranch?->name ?? '—' }}</dd></div>
        @endif
        <div><dt>Placed</dt><dd>{{ $order->created_at->format('M d, Y h:i A') }}</dd></div>
        <div><dt>Updated</dt><dd>{{ $order->updated_at->format('M d, Y h:i A') }} · {{ $order->updated_at->diffForHumans() }}</dd></div>
    </dl>

    <h3 class="list-preview-heading">Recent activity</h3>
    @if($order->statusHistories->isEmpty())
        <p class="oversight-meta">No status changes recorded yet.</p>
    @else
        <ol class="order-history">
            @foreach($order->statusHistories as $history)
                <li class="{{ $history->source === 'admin' ? 'is-admin' : '' }}">
                    <strong>{{ \App\Services\Orders\OrderLifecycleService::label($history->to_status) }}</strong>
                    <span class="oversight-meta">{{ $history->created_at->format('M d, h:i A') }} · {{ $history->changer?->full_name ?? ucfirst((string) $history->source) }}</span>
                    @if($history->reason)<p class="order-reason">{{ $history->reason }}</p>@endif
                </li>
            @endforeach
        </ol>
    @endif

    <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-coral list-preview-open">Open full order</a>
</div>
