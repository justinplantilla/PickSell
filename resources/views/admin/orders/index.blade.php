@extends('admin.layout')
@section('title', 'Orders')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-list.css', 'resources/css/views/admin-orders.css'])
@endsection

@section('content')
@php
    $canExport = auth()->user()->can(\App\Auth\Permission::REPORTS_EXPORT);
    $canBulk = auth()->user()->can(\App\Auth\Permission::ORDERS_OVERRIDE_STATUS);
    $exportQuery = request()->except('page');
@endphp
<div class="card list-page">
    <div class="card-header">
        <div>
            <span class="card-title">Marketplace orders</span>
            <p class="oversight-description">Every order across sellers, most recently updated first. Click a row for a quick look.</p>
        </div>
        @if($canExport && $matchingCount > 0)
            <a href="{{ route('admin.orders.export', $exportQuery) }}" class="btn btn-outline btn-sm list-export">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v3h16v-3"/></svg>
                Export {{ number_format($matchingCount) }}
            </a>
        @endif
    </div>

    <nav class="oversight-tabs" aria-label="Order stage">
        <a href="{{ route('admin.orders') }}" class="oversight-tab {{ $stage === 'all' ? 'is-active' : '' }}" @if($stage === 'all') aria-current="page" @endif>All <span class="oversight-tab-count">{{ number_format($totalOrders) }}</span></a>
        @foreach($stages as $key => $definition)
            <a href="{{ route('admin.orders', ['stage' => $key]) }}" class="oversight-tab {{ $stage === $key ? 'is-active' : '' }}" @if($stage === $key) aria-current="page" @endif>{{ $definition['label'] }} <span class="oversight-tab-count">{{ number_format($stageCounts[$key]) }}</span></a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.orders') }}" class="list-toolbar" role="search" aria-label="Filter orders"
          data-saved-filter-scope="orders" data-saved-filter-user="{{ auth()->id() }}" data-saved-filter-mode="menu">
        @if($stage !== 'all')<input type="hidden" name="stage" value="{{ $stage }}">@endif
        @if(request()->boolean('stuck'))<input type="hidden" name="stuck" value="1">@endif
        @if(request()->boolean('issues'))<input type="hidden" name="issues" value="1">@endif
        <label class="list-search">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span class="sr-only">Search orders</span>
            <input type="search" name="search" value="{{ $search }}" placeholder="Search order, waybill, product, buyer or seller…">
        </label>
        <select name="status" class="filter-select" aria-label="Status" data-submit-on-change>
            <option value="">Any status</option>
            @if(count($statuses) > 1)<option value="{{ implode(',', $statuses) }}" selected>{{ count($statuses) }} statuses</option>@endif
            @foreach(\App\Models\Order::STATUS_LIFECYCLE as $value)
                <option value="{{ $value }}" @selected($statuses === [$value])>{{ \App\Services\Orders\OrderLifecycleService::label($value) }}</option>
            @endforeach
        </select>
        <select name="date" class="filter-select" aria-label="Date" data-submit-on-change>
            <option value="any">Any date</option>
            @foreach(\App\Http\Controllers\AdminOrderController::DATE_FILTERS as $value => $label)
                <option value="{{ $value }}" @selected($date === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <details class="list-popover" @if($errors->any()) open @endif>
            <summary class="btn btn-outline btn-sm">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                More filters @if($moreFiltersCount)<span class="list-count-pill">{{ $moreFiltersCount }}</span>@endif
            </summary>
            <div class="list-popover-panel">
                @foreach([
                    'seller' => ['Seller', 'All sellers', $options['sellers'], fn ($u) => $u->business_name ?? $u->full_name],
                    'buyer' => ['Buyer', 'All buyers', $options['buyers'], fn ($u) => $u->full_name],
                ] as $key => [$label, $placeholder, $choices, $labelOf])
                    <label class="list-field">
                        <span>{{ $label }}</span>
                        <select name="{{ $key }}" class="filter-select">
                            <option value="">{{ $placeholder }}</option>
                            @foreach($choices as $choice)
                                <option value="{{ $choice->id }}" @selected($filters[$key] === $choice->id)>{{ $labelOf($choice) }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
                <div class="list-popover-actions">
                    <button type="submit" class="btn btn-coral btn-sm">Apply filters</button>
                </div>
            </div>
        </details>
        <div class="list-views" data-saved-filter-mount></div>
        <button type="submit" class="sr-only">Search</button>
    </form>

    @if($chips)
        <div class="list-chips" aria-label="Active filters">
            @foreach($chips as $chip)
                <a href="{{ $chip['url'] }}" class="list-chip">{{ $chip['label'] }} <span aria-hidden="true">✕</span><span class="sr-only">(remove filter)</span></a>
            @endforeach
            <a href="{{ route('admin.orders', $stage !== 'all' ? ['stage' => $stage] : []) }}" class="list-chips-clear">Clear all</a>
        </div>
    @endif

    @if(session('bulkSkippedCount', 0) > 0)
        <div class="alert alert-warning list-alert" role="status">
            {{ session('bulkSkippedCount') }} order(s) were skipped:
            <ul>@foreach(session('bulkSkipped', []) as $skipped)<li>{{ $skipped }}</li>@endforeach</ul>
            @if(session('bulkSkippedCount') > count(session('bulkSkipped', [])))<p>Additional skipped orders are omitted from this summary.</p>@endif
        </div>
    @endif

    @if($orders->isEmpty())
        <div class="oversight-empty"><strong>No orders found</strong>{{ $chips ? 'Nothing matches these filters.' : 'Orders will appear here once buyers check out.' }}</div>
    @else
        <div class="oversight-table-wrap">
            <table class="list-table" data-list-table>
                <thead>
                    <tr>
                        @if($canBulk)<th class="list-check"><input type="checkbox" data-select-page aria-label="Select all orders on this page"></th>@endif
                        <th>Order</th>
                        <th>Buyer / Seller</th>
                        <th class="list-amount">Amount</th>
                        <th>Status</th>
                        <th>Age</th>
                        <th class="list-chevron"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($orders as $order)
                    @php
                        $stuck = in_array($order->status, \App\Services\Admin\AdminDashboardService::IN_PROGRESS_STATUSES, true)
                            && $order->updated_at->lt(now()->subHours($stuckAfterHours));
                    @endphp
                    <tr class="list-row {{ $stuck ? 'is-stale' : '' }}" data-list-row data-preview-url="{{ route('admin.orders.preview', $order) }}">
                        @if($canBulk)
                            <td class="list-check"><input type="checkbox" value="{{ $order->id }}" data-select-row aria-label="Select {{ $order->order_number }}"></td>
                        @endif
                        <td class="list-cell-main" data-label="Order">
                            <a href="{{ route('admin.orders.show', $order) }}" class="list-title" data-row-link>{{ $order->order_number }}</a>
                            <span class="oversight-meta">{{ $order->product_name }} × {{ $order->quantity }}</span>
                            @if($order->waybill_number)
                                <span class="list-code">
                                    <code>{{ $order->waybill_number }}</code>
                                    <button type="button" class="list-copy" data-copy="{{ $order->waybill_number }}" aria-label="Copy waybill {{ $order->waybill_number }}" title="Copy waybill">
                                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/></svg>
                                    </button>
                                </span>
                            @endif
                        </td>
                        <td class="list-cell-wide" data-label="Buyer / Seller">
                            <span class="list-line">{{ $order->buyer?->full_name ?? '—' }}</span>
                            <span class="oversight-meta">{{ $order->seller?->business_name ?? $order->seller?->full_name ?? '—' }}</span>
                        </td>
                        <td class="list-amount list-cell-aside" data-label="Amount"><span class="list-money"><span aria-hidden="true">₱</span><span class="sr-only">PHP </span><span>{{ number_format($order->amount, 2) }}</span></span></td>
                        <td class="list-status list-cell-sub" data-label="Status">
                            <x-admin-order-status :status="$order->status" />
                            @if($order->tracking_status && strcasecmp(trim($order->tracking_status), \App\Services\Orders\OrderLifecycleService::label($order->status)) !== 0)<span class="oversight-meta">{{ $order->tracking_status }}</span>@endif
                        </td>
                        <td class="list-age list-cell-sub-aside" data-label="Age">
                            <time datetime="{{ $order->updated_at->toIso8601String() }}" title="Last updated {{ $order->updated_at->format('M d, Y h:i A') }}">{{ $order->updated_at->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true) }}</time>
                            @if($stuck)<span class="oversight-flag" title="No update for {{ $stuckAfterHours }}h+"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M12 7v5l3 2"/><circle cx="12" cy="12" r="9"/></svg>Stuck</span>@endif
                        </td>
                        <td class="list-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())<div class="dashboard-pagination">{{ $orders->links() }}</div>@endif
    @endif
</div>

@if($canBulk && $matchingCount > 0)
    <div class="list-bulkbar" data-bulkbar data-matching-count="{{ $matchingCount }}" data-noun="order" hidden>
        <div class="list-bulkbar-summary">
            <strong data-bulk-count>0 selected</strong>
            @if($matchingCount > $orders->count())
                <button type="button" class="list-link-button" data-select-all-matching>Select all {{ number_format($matchingCount) }} matching</button>
            @endif
        </div>
        <div class="list-bulkbar-actions">
            <button type="button" class="btn btn-coral btn-sm" data-bulk-open="bulk-status-dialog">Change status</button>
            @if($canExport)
                <a href="{{ route('admin.orders.export', $exportQuery) }}" class="btn btn-outline btn-sm" data-bulk-export data-ids-param="order_ids" data-export-base="{{ route('admin.orders.export', $exportQuery) }}">Export selected</a>
            @endif
            <button type="button" class="list-link-button" data-bulk-clear>Clear</button>
        </div>
    </div>

    <dialog class="list-dialog" id="bulk-status-dialog" data-bulk-dialog aria-labelledby="bulk-order-title">
        <form method="POST" action="{{ route('admin.orders.bulk-status') }}" class="bulk-action-form" data-no-confirm>
            @csrf
            <h2 id="bulk-order-title">Change status</h2>
            <p class="oversight-description" data-bulk-scope>Applies to the selected orders.</p>
            <div data-bulk-ids data-ids-name="order_ids[]"></div>
            <input type="hidden" name="stage" value="{{ $stage }}">
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="filter_status" value="{{ implode(',', $statuses) }}">
            <input type="hidden" name="date" value="{{ $date }}">
            @foreach($filters as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            @if(request()->boolean('stuck'))<input type="hidden" name="stuck" value="1">@endif
            @if(request()->boolean('issues'))<input type="hidden" name="issues" value="1">@endif
            <input type="hidden" name="matching_count" value="{{ $matchingCount }}" data-bulk-matching>
            <label class="form-label" for="bulk-order-status">Target status</label>
            <select id="bulk-order-status" name="to_status" class="form-control" required>
                @foreach(\App\Models\Order::STATUS_LIFECYCLE as $value)
                    <option value="{{ $value }}">{{ \App\Services\Orders\OrderLifecycleService::label($value) }}</option>
                @endforeach
            </select>
            <label class="form-label" for="bulk-order-reason">Reason shown to buyer and seller (required)</label>
            <textarea id="bulk-order-reason" name="reason" class="form-control" minlength="10" maxlength="1000" required></textarea>
            <label class="account-confirm"><input type="checkbox" name="confirm" value="1" required> <span data-bulk-confirm-text>Apply to the selected orders.</span> Only permitted lifecycle transitions will run; others will be skipped.</label>
            <div class="list-dialog-actions">
                <button type="button" class="btn btn-outline btn-sm" data-bulk-cancel>Cancel</button>
                <button type="submit" class="btn btn-coral btn-sm">Apply</button>
            </div>
        </form>
    </dialog>
@endif

<div class="list-drawer-backdrop" data-drawer-backdrop hidden></div>
<aside class="list-drawer" data-list-drawer hidden aria-labelledby="list-drawer-title" role="dialog" aria-modal="true">
    <button type="button" class="list-drawer-close" data-drawer-close aria-label="Close order preview">&times;</button>
    <div data-drawer-body><p class="oversight-empty">Loading…</p></div>
</aside>
@endsection

@section('scripts')
@vite('resources/js/components/admin-list.js')
@endsection
