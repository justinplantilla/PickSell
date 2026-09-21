@extends('admin.layout')
@vite('resources/css/views/admin-commission.css')
@section('title', 'Commission Management')

@section('content')
<!-- Date Filter -->
<div class="card blade-inline-1">
    <div class="card-body">
        <form method="GET" class="blade-inline-2">
            <div class="form-group blade-inline-3">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="{{ $from }}" class="blade-inline-4">
            </div>
            <div class="form-group blade-inline-5">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="{{ $to }}" class="blade-inline-6">
            </div>
            <button type="submit" class="btn btn-coral">Filter</button>
        </form>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card green">
        <div class="stat-card-icon">💰</div>
        <div class="stat-card-num">₱{{ number_format($totalSales, 2) }}</div>
        <div class="stat-card-label">Total Sales</div>
    </div>
    <div class="stat-card coral">
        <div class="stat-card-icon">📊</div>
        <div class="stat-card-num">₱{{ number_format($totalCommission, 2) }}</div>
        <div class="stat-card-label">Total Commission ({{ $rate }}%)</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-card-icon">🧾</div>
        <div class="stat-card-num">{{ $orders->count() }}</div>
        <div class="stat-card-label">Completed Orders</div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><span class="card-title">Commission by Seller</span></div>
        <div class="card-body"><div id="commissionChart"></div></div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Commission Rate</span></div>
        <div class="card-body">
            <p class="blade-inline-7">Current platform commission rate applied to all completed orders.</p>
            <div class="blade-inline-8">{{ $rate }}%</div>
            <p class="blade-inline-9">Per completed transaction</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Order Commission Breakdown</span></div>
    @if($orders->isEmpty())
    <div class="card-body blade-inline-10">No completed orders in this period.</div>
    @else
    <table>
        <thead>
            <tr><th>Order #</th><th>Seller</th><th>Product</th><th>Order Amount</th><th>Commission ({{ $rate }}%)</th><th>Date</th></tr>
        </thead>
        <tbody>
        @foreach($orders as $order)
        <tr>
            <td><strong>{{ $order->order_number }}</strong></td>
            <td>{{ $order->seller->full_name ?? '—' }}</td>
            <td>{{ $order->product_name }}</td>
            <td>₱{{ number_format($order->amount, 2) }}</td>
            <td class="blade-inline-11">₱{{ number_format($order->commission, 2) }}</td>
            <td>{{ $order->created_at->format('M d, Y') }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<div data-commission-chart data-commissions='@json($orders->pluck("commission")->values())' data-orders='@json($orders->map(fn($order) => $order->order_number)->values())' hidden></div>
@vite('resources/js/views/admin-commission.js')
@endsection
