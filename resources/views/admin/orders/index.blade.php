@extends('admin.layout')
@section('title', 'Orders')
@section('styles')
@vite('resources/css/views/admin-oversight.css')
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Marketplace orders</span>
            <p class="oversight-description">Every order across sellers, most recently updated first. Open an order for its full lifecycle.</p>
        </div>
    </div>

    <nav class="oversight-tabs" aria-label="Order stage">
        <a href="{{ route('admin.orders') }}" class="oversight-tab {{ $stage === 'all' ? 'is-active' : '' }}" @if($stage === 'all') aria-current="page" @endif>All <span class="oversight-tab-count">{{ number_format($totalOrders) }}</span></a>
        @foreach($stages as $key => $definition)
            <a href="{{ route('admin.orders', ['stage' => $key]) }}" class="oversight-tab {{ $stage === $key ? 'is-active' : '' }}" @if($stage === $key) aria-current="page" @endif>{{ $definition['label'] }} <span class="oversight-tab-count">{{ number_format($stageCounts[$key]) }}</span></a>
        @endforeach
    </nav>

    <form method="GET" class="oversight-filters oversight-note" role="search" aria-label="Filter orders" data-saved-filter-scope="orders" data-saved-filter-user="{{ auth()->id() }}">
        @if($stage !== 'all')<input type="hidden" name="stage" value="{{ $stage }}">@endif
        <input type="search" name="search" value="{{ $search }}" class="search-input" placeholder="Order ID, number, waybill or product" aria-label="Search orders">
        <select name="status" class="filter-select" aria-label="Status" data-submit-on-change>
            <option value="">Any status</option>
            @foreach(\App\Models\Order::STATUS_LIFECYCLE as $value)
                <option value="{{ $value }}" @selected($statuses === [$value])>{{ \App\Services\Orders\OrderLifecycleService::label($value) }}</option>
            @endforeach
        </select>
        @foreach([
            'seller' => ['All sellers', $options['sellers'], fn ($u) => $u->business_name ?? $u->full_name],
            'buyer' => ['All buyers', $options['buyers'], fn ($u) => $u->full_name],
            'courier' => ['All couriers', $options['couriers'], fn ($u) => $u->full_name],
            'branch' => ['All sorting centers', $options['branches'], fn ($b) => $b->name],
        ] as $key => [$placeholder, $choices, $labelOf])
            <select name="{{ $key }}" class="filter-select" aria-label="{{ $key === 'branch' ? 'Sorting center' : ucfirst($key) }}" data-submit-on-change>
                <option value="">{{ $placeholder }}</option>
                @foreach($choices as $choice)
                    <option value="{{ $choice->id }}" @selected($filters[$key] === $choice->id)>{{ $labelOf($choice) }}</option>
                @endforeach
            </select>
        @endforeach
        <select name="date" class="filter-select" aria-label="Date" data-submit-on-change>
            <option value="any">Any date</option>
            @foreach(\App\Http\Controllers\AdminOrderController::DATE_FILTERS as $value => $label)
                <option value="{{ $value }}" @selected($date === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-outline btn-sm">Search</button>
        @if($search !== '' || $statuses || array_filter($filters) || $date !== 'any' || $activeFilter)
            <a href="{{ route('admin.orders') }}" class="btn btn-outline btn-sm">Reset</a>
        @endif
    </form>

    @if($activeFilter)
        <p class="oversight-description oversight-note">Showing: <strong>{{ $activeFilter }}</strong> · <a href="{{ route('admin.orders') }}">Clear filter</a></p>
    @endif

    @can(\App\Auth\Permission::ORDERS_OVERRIDE_STATUS)
    @if($matchingCount > 0)
        <details class="bulk-action-panel oversight-note">
            <summary class="btn btn-outline btn-sm">Bulk order action · {{ number_format($matchingCount) }} matching</summary>
            <form method="POST" action="{{ route('admin.orders.bulk-status') }}" class="bulk-action-form">
                @csrf
                <input type="hidden" name="stage" value="{{ $stage }}">
                <input type="hidden" name="search" value="{{ $search }}">
                <input type="hidden" name="filter_status" value="{{ implode(',', $statuses) }}">
                <input type="hidden" name="date" value="{{ $date }}">
                @foreach($filters as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                @if(request()->boolean('stuck'))<input type="hidden" name="stuck" value="1">@endif
                @if(request()->boolean('issues'))<input type="hidden" name="issues" value="1">@endif
                <input type="hidden" name="matching_count" value="{{ $matchingCount }}">
                <label class="form-label" for="bulk-order-status">Target status</label>
                <select id="bulk-order-status" name="to_status" class="form-control" required>
                    @foreach(\App\Models\Order::STATUS_LIFECYCLE as $value)
                        <option value="{{ $value }}">{{ \App\Services\Orders\OrderLifecycleService::label($value) }}</option>
                    @endforeach
                </select>
                <label class="form-label" for="bulk-order-reason">Reason shown to buyer and seller (required)</label>
                <textarea id="bulk-order-reason" name="reason" class="form-control" minlength="10" maxlength="1000" required></textarea>
                <label class="account-confirm"><input type="checkbox" name="confirm" value="1" required> Apply to all {{ number_format($matchingCount) }} orders matching these filters. Only permitted lifecycle transitions will run; others will be skipped.</label>
                <button type="submit" class="btn btn-coral">Apply to filtered orders</button>
            </form>
        </details>
    @endif
    @endcan
    @if(session('bulkSkippedCount', 0) > 0)
        <div class="alert alert-warning" role="status">
            {{ session('bulkSkippedCount') }} order(s) were skipped:
            <ul>@foreach(session('bulkSkipped', []) as $skipped)<li>{{ $skipped }}</li>@endforeach</ul>
            @if(session('bulkSkippedCount') > count(session('bulkSkipped', [])))<p>Additional skipped orders are omitted from this summary.</p>@endif
        </div>
    @endif

    @if($orders->isEmpty())
        <div class="oversight-empty"><strong>No orders found</strong>{{ $activeFilter ? 'Nothing matches this filter right now.' : 'Try different filters.' }}</div>
    @else
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>Order</th><th>Buyer</th><th>Seller</th><th>Amount</th><th>Status</th><th>Courier</th><th>Updated</th><th>Action</th></tr></thead>
                <tbody>
                @foreach($orders as $order)
                    <tr>
                        <td><strong>{{ $order->order_number }}</strong><span class="oversight-meta">{{ $order->product_name }} × {{ $order->quantity }}@if($order->waybill_number) · {{ $order->waybill_number }}@endif</span></td>
                        <td>{{ $order->buyer?->full_name ?? '—' }}</td>
                        <td>{{ $order->seller?->business_name ?? $order->seller?->full_name ?? '—' }}</td>
                        <td class="oversight-number">₱{{ number_format($order->amount, 2) }}</td>
                        <td><x-admin-order-status :status="$order->status" />@if($order->tracking_status)<span class="oversight-meta">{{ $order->tracking_status }}</span>@endif</td>
                        <td>{{ $order->courier?->full_name ?? '—' }}</td>
                        <td><time datetime="{{ $order->updated_at->toIso8601String() }}" title="{{ $order->updated_at->format('M d, Y h:i A') }}">{{ $order->updated_at->diffForHumans() }}</time></td>
                        <td><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline btn-sm">View<span class="sr-only"> {{ $order->order_number }}</span></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())<div class="dashboard-pagination">{{ $orders->links() }}</div>@endif
    @endif
</div>
@endsection
