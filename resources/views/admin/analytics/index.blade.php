@extends('admin.layout')
@section('title', 'Reports')
@section('styles')
@vite('resources/css/views/admin-oversight.css')
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Marketplace analytics</span>
            <p class="oversight-description">Operational health for orders created in the selected period. Sales and commission totals are in Financial Reports.</p>
        </div>
        <form method="GET" class="oversight-filters">
            <label>From <input type="date" name="from" value="{{ $from }}" class="form-control"></label>
            <label>To <input type="date" name="to" value="{{ $to }}" class="form-control"></label>
            <button type="submit" class="btn btn-coral btn-sm">Apply</button>
        </form>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-card-num oversight-number">{{ number_format($ordersPlaced) }}</div><div class="stat-card-label">Orders placed</div></div>
    <div class="stat-card"><div class="stat-card-num oversight-number">{{ number_format($completedOrders) }}</div><div class="stat-card-label">Completed (buyer confirmed)</div></div>
    <div class="stat-card"><div class="stat-card-num oversight-number">{{ $deliverySuccessRate !== null ? $deliverySuccessRate . '%' : '—' }}</div><div class="stat-card-label">Delivery success rate</div></div>
    <div class="stat-card"><div class="stat-card-num oversight-number">{{ $avgDeliveryHours !== null ? $avgDeliveryHours . 'h' : '—' }}</div><div class="stat-card-label">Avg. order-to-delivery time</div></div>
    <div class="stat-card"><div class="stat-card-num oversight-number">{{ $returnRate !== null ? $returnRate . '%' : '—' }}</div><div class="stat-card-label">Return rate · {{ number_format($returnsOpened) }} {{ $returnsOpened === 1 ? 'return' : 'returns' }}</div></div>
    <div class="stat-card"><div class="stat-card-num oversight-number">{{ number_format($complaintsOpened) }}</div><div class="stat-card-label">Complaints filed</div></div>
</div>

<div class="oversight-grid-2">
    <section class="card" aria-labelledby="lifecycle-title">
        <div class="card-header"><span class="card-title" id="lifecycle-title">Orders by current status</span></div>
        @if($ordersPlaced === 0)
            <div class="oversight-empty"><strong>No orders in this period</strong></div>
        @else
            <ul class="oversight-bars">
                @foreach($lifecycle as $status => $count)
                    @php $label = ucfirst(str_replace('_', ' ', $status)); @endphp
                    <li class="oversight-bar-row" title="{{ $label }}: {{ number_format($count) }} {{ $count === 1 ? 'order' : 'orders' }}">
                        <span class="oversight-bar-label">{{ $label }}</span>
                        <span class="oversight-bar-track" aria-hidden="true"><span class="oversight-bar-fill" style="width: {{ $count ? max(1, round($count / $lifecycleMax * 100, 1)) : 0 }}%; {{ $count ? '' : 'min-width:0' }}"></span></span>
                        <span class="oversight-bar-value">{{ number_format($count) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="card" aria-labelledby="category-title">
        <div class="card-header"><span class="card-title" id="category-title">Completed sales by category</span></div>
        @if($categories->isEmpty())
            <div class="oversight-empty"><strong>No completed orders in this period</strong></div>
        @else
            <ul class="oversight-bars">
                @foreach($categories as $category => $row)
                    <li class="oversight-bar-row" title="{{ $category }}: ₱{{ number_format($row['sales'], 2) }} from {{ number_format($row['orders']) }} {{ $row['orders'] === 1 ? 'order' : 'orders' }}">
                        <span class="oversight-bar-label">{{ $category }}</span>
                        <span class="oversight-bar-track" aria-hidden="true"><span class="oversight-bar-fill" style="width: {{ max(1, round($row['sales'] / $categoryMax * 100, 1)) }}%"></span></span>
                        <span class="oversight-bar-value">₱{{ number_format($row['sales'], 0) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

<section class="card" aria-labelledby="users-title">
    <div class="card-header"><span class="card-title" id="users-title">New accounts in this period</span></div>
    <div class="oversight-table-wrap">
        <table>
            <thead><tr><th>Buyers</th><th>Sellers</th><th>Riders</th><th>Logistics partners</th></tr></thead>
            <tbody><tr>
                <td class="oversight-number">{{ number_format($newUsers['buyer'] ?? 0) }}</td>
                <td class="oversight-number">{{ number_format($newUsers['seller'] ?? 0) }}</td>
                <td class="oversight-number">{{ number_format($newUsers['courier'] ?? 0) }}</td>
                <td class="oversight-number">{{ number_format($newUsers['logistics'] ?? 0) }}</td>
            </tr></tbody>
        </table>
    </div>
</section>
@endsection
