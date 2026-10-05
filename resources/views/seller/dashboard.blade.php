@extends('seller.layout')
@section('styles')
@vite('resources/css/views/seller-dashboard.css')
@endsection
@section('title', 'Dashboard')

@section('content')
@php
    $periodLabel = ['7D' => '7 days', '30D' => '30 days', '6M' => '6 months'][$range];
    $rangeLabels = ['7D' => '7 Days', '30D' => '30 Days', '6M' => '6 Months'];
@endphp
<div class="seller-dashboard-shell">
    <div class="dashboard-status-band">
        <div class="store-greeting">
            <span class="eyebrow">Seller overview</span>
            <h2>{{ $greeting }}, {{ $storeName }}</h2>
            <div class="greeting-meta">
                <time class="store-date" datetime="{{ $currentDate->toDateString() }}">{{ $currentDate->format('l, F j, Y') }}</time>
                @if($storeStatus)
                    <span class="store-status store-status-{{ $storeStatus['tone'] }}">{{ $storeStatus['label'] }}</span>
                @endif
            </div>
        </div>
        <a href="{{ route('seller.inventory', ['action' => 'create']) }}" class="btn btn-coral dashboard-cta">+ Add Product</a>
    </div>

    <div class="action-panel">
        <div class="section-heading">
            <span class="panel-title">Needs your attention</span>
            <p class="panel-description">Quick actions to keep orders moving and products available.</p>
        </div>
        <div class="action-list">
            @if($actionRequiredOrders > 0)
            <div class="action-item action-item--orders">
                <div class="action-copy">
                    <span class="action-count">{{ $actionRequiredOrders }}</span>
                    <div>
                        <strong>Unfulfilled orders</strong>
                        <small>New and in-progress orders need preparation or handoff.</small>
                    </div>
                </div>
                <a href="{{ route('seller.orders', ['status' => 'action_required']) }}" class="btn btn-outline btn-sm dashboard-action-link">Process orders &rarr;</a>
            </div>
            @endif
            <div class="action-item action-item--orders" data-seller-return-alert @if($actionRequiredReturns === 0) hidden @endif>
                <div class="action-copy">
                    <span class="action-count" data-pending-return-count>{{ $actionRequiredReturns }}</span>
                    <div>
                        <strong><span data-pending-return-count-text>{{ $actionRequiredReturns }} return request{{ $actionRequiredReturns === 1 ? '' : 's' }} awaiting response</span></strong>
                        <small>Review the buyer's request and decide on the return.</small>
                    </div>
                </div>
                <a href="{{ route('seller.returns', ['status' => 'requested']) }}" class="btn btn-outline btn-sm dashboard-action-link">Review &rarr;</a>
            </div>
            @if($lowStockCount > 0)
            <div class="action-item action-item--stock">
                <div class="action-copy">
                    <span class="action-count">{{ $lowStockCount }}</span>
                    <div>
                        <strong>Inventory below threshold</strong>
                        <small>Active products with 5 or fewer units remaining.</small>
                    </div>
                </div>
                <a href="{{ route('seller.inventory', ['filter' => 'low-stock']) }}" class="btn btn-outline btn-sm dashboard-action-link">Restock &rarr;</a>
            </div>
            @endif
            <x-dashboard-empty-state icon="complete" title="You're all caught up" description="No orders need processing and no active products are below the stock threshold." data-return-action-empty :hidden="$actionRequiredOrders > 0 || $actionRequiredReturns > 0 || $lowStockCount > 0" />
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-block">
            <div class="kpi-title">Business health</div>
            <div class="kpi-list">
                <a href="{{ route('seller.orders', ['status' => 'completed']) }}" class="kpi-item kpi-link">
                    <span>Total sales</span>
                    <strong>₱{{ number_format($totalSales, 2) }}</strong>
                    <small>{{ $salesDelta > 0 ? '↑' : ($salesDelta < 0 ? '↓' : '→') }} {{ number_format(abs($salesDelta), 1) }}% vs previous {{ $periodLabel }}</small>
                </a>
                <a href="{{ route('seller.orders') }}" class="kpi-item kpi-link">
                    <span>Total orders</span>
                    <strong>{{ $totalOrders }}</strong>
                    <small>{{ $ordersDelta > 0 ? '↑' : ($ordersDelta < 0 ? '↓' : '→') }} {{ number_format(abs($ordersDelta), 1) }}% vs previous {{ $periodLabel }}</small>
                </a>
            </div>
        </div>

        <div class="kpi-block">
            <div class="kpi-title">Order pipeline</div>
            <div class="kpi-list">
                <a href="{{ route('seller.orders', ['status' => 'completed']) }}" class="kpi-item kpi-link">
                    <span>Completed orders</span>
                    <strong>{{ $completedOrders }}</strong>
                </a>
                <a href="{{ route('seller.orders', ['status' => 'pending']) }}" class="kpi-item kpi-link">
                    <span>Pending orders</span>
                    <strong>{{ $pendingOrders }}</strong>
                </a>
            </div>
        </div>

        <div class="kpi-block">
            <div class="kpi-title">Catalog health</div>
            <div class="kpi-list">
                <a href="{{ route('seller.inventory', ['status' => 'active']) }}" class="kpi-item kpi-link">
                    <span>Active products</span>
                    <strong>{{ $activeProducts }}</strong>
                </a>
                <a href="{{ route('seller.inventory', ['filter' => 'low-stock']) }}" class="kpi-item kpi-link">
                    <span>Low-stock products</span>
                    <strong>{{ $lowStockCount }}</strong>
                    <small>5 or fewer units remaining</small>
                </a>
            </div>
        </div>
    </div>

    <div class="content-grid">
        <div class="panel chart-panel">
            <div class="panel-header">
                <div>
                    <span class="panel-title">Sales and order trends</span>
                    <p class="panel-description">{{ $metric === 'sales' ? 'Completed sales' : 'Order count' }} across the last {{ $periodLabel }}.</p>
                </div>
                <div class="segmented-control">
                    @foreach(['7D', '30D', '6M'] as $option)
                        <a href="{{ route('seller.dashboard', ['range' => $option, 'metric' => $metric]) }}" class="range-pill {{ $range === $option ? 'active' : '' }}" aria-label="Show {{ $rangeLabels[$option] }}" aria-current="{{ $range === $option ? 'true' : 'false' }}">{{ $rangeLabels[$option] }}</a>
                    @endforeach
                </div>
            </div>
            <div class="chart-toolbar">
                <div class="toolbar-group">
                    <a href="{{ route('seller.dashboard', ['range' => $range, 'metric' => 'sales']) }}" class="metric-pill {{ $metric === 'sales' ? 'active' : '' }}" aria-current="{{ $metric === 'sales' ? 'true' : 'false' }}">Sales (PHP)</a>
                    <a href="{{ route('seller.dashboard', ['range' => $range, 'metric' => 'orders']) }}" class="metric-pill {{ $metric === 'orders' ? 'active' : '' }}" aria-current="{{ $metric === 'orders' ? 'true' : 'false' }}">Orders (count)</a>
                </div>
            </div>
            @if(array_sum($chartData['values']) > 0)
                <div class="chart-visual" data-chart-visual data-chart-state="loading">
                    <div class="chart-skeleton" role="status" aria-label="Loading analytics chart">
                        <div class="chart-skeleton-bars">
                            @foreach([34, 57, 43, 76, 52, 88, 66] as $height)
                                <span style="height: {{ $height }}%"></span>
                            @endforeach
                        </div>
                        <span class="chart-skeleton-label">Preparing chart...</span>
                    </div>
                    <div id="salesChart" data-seller-sales-chart data-chart-metric="{{ $metric }}" data-chart-values='@json($chartData["values"])' data-chart-labels='@json($chartData["labels"])' aria-label="{{ $metric === 'sales' ? 'Sales' : 'Order count' }} for the last {{ $periodLabel }}"></div>
                    <div class="chart-fallback">
                        <x-dashboard-empty-state icon="analytics" title="Chart unavailable" description="The analytics chart could not be loaded. Refresh to try again." />
                    </div>
                </div>
            @else
                <x-dashboard-empty-state icon="analytics" title="No activity for this period" description="Sales and order activity will appear here when you receive orders." />
            @endif
        </div>

        <div class="panel breakdown-panel">
            <div class="panel-header">
                <div>
                    <span class="panel-title">Orders by status</span>
                    <p class="panel-description">A snapshot of your current order pipeline.</p>
                </div>
            </div>
            @if($totalOrders === 0)
                <x-dashboard-empty-state icon="orders" title="No order activity yet" description="Order status distribution will appear when your first order arrives." />
            @else
                <div class="status-list">
                    @foreach($orderBreakdown as $status)
                    <a href="{{ route('seller.orders', ['status' => $status['filter']]) }}" class="status-row status-row-link">
                        <div class="status-row-heading">
                            <span>{{ $status['label'] }}</span>
                            <strong>{{ $status['count'] }}</strong>
                        </div>
                        <div class="status-track" role="img" aria-label="{{ $status['label'] }}: {{ $status['count'] }} orders, {{ number_format($status['percentage'], 1) }} percent">
                            <span class="status-bar status-bar-{{ $status['tone'] }}" style="width: {{ $status['percentage'] }}%"></span>
                        </div>
                        <small class="status-description">{{ $status['description'] }} · {{ number_format($status['percentage'], 1) }}%</small>
                    </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="content-grid lower-grid">
        <div class="panel recent-orders-panel">
            <div class="panel-header">
                <div>
                    <span class="panel-title">Recent orders</span>
                    <p class="panel-description">Your latest orders, buyers, totals, and fulfillment status.</p>
                </div>
                <a href="{{ route('seller.orders') }}" class="btn btn-outline btn-sm">View all</a>
            </div>
            @if($recentOrders->isEmpty())
                <x-dashboard-empty-state icon="orders" title="No recent orders" description="Customer orders will appear here as soon as your store receives them." />
            @else
                <div class="recent-orders-table">
                    @foreach($recentOrders as $order)
                        <a href="{{ route('seller.orders.show', $order->id) }}" class="order-row" aria-label="View order {{ $order->order_number }} for {{ $order->buyer->full_name ?? 'Customer' }}">
                            <span class="order-number">{{ $order->order_number }}</span>
                            <div class="order-meta">
                                <span>{{ $order->buyer->full_name ?? 'Customer' }}</span>
                                <span>₱{{ number_format($order->amount, 2) }}</span>
                            </div>
                            <x-order-status-badge :status="$order->status" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="panel inventory-panel">
            <div class="panel-header">
                <div>
                    <span class="panel-title">Low-stock inventory</span>
                    <p class="panel-description">Active products with 5 or fewer units in stock.</p>
                </div>
            </div>
            @if($lowStockProducts->isEmpty())
                <x-dashboard-empty-state icon="catalog" title="Catalog fully stocked" description="All active products have more than {{ \App\Models\Product::LOW_STOCK_THRESHOLD }} units available." />
            @else
                <div class="inventory-list">
                    @foreach($lowStockProducts as $product)
                        <div class="inventory-item">
                            <span>{{ $product->name }}</span>
                            <span class="inventory-item-stock"><strong>{{ $product->stock }} {{ $product->stock === 1 ? 'unit' : 'units' }} left</strong><x-stock-status-badge :product="$product" /></span>
                        </div>
                    @endforeach
                    @if($lowStockCount > $lowStockProducts->count())
                        <a href="{{ route('seller.inventory', ['filter' => 'low-stock']) }}" class="inventory-more">+ {{ $lowStockCount - $lowStockProducts->count() }} more</a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
@vite('resources/js/views/seller-dashboard.js')
@endsection
