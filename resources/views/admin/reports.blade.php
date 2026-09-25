@extends('admin.layout')
@section('styles')
@vite('resources/css/views/admin-reports.css')
@endsection
@section('title', 'Generate Reports')

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
            <button type="submit" class="btn btn-coral">Generate</button>
            <a href="/admin/reports/export?from={{ $from }}&to={{ $to }}&type=sales" class="btn btn-outline"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-4-6zm1 1.5L18.5 8H15V3.5zM8 13h8v1.5H8V13zm0 3h8v1.5H8V16z"/></svg> Export Sales PDF</a>
            <a href="/admin/reports/export?from={{ $from }}&to={{ $to }}&type=commission" class="btn btn-outline"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-4-6zm1 1.5L18.5 8H15V3.5zM8 13h8v1.5H8V13zm0 3h8v1.5H8V16z"/></svg> Export Commission PDF</a>
        </form>
    </div>
</div>

<!-- Summary Stats -->
<div class="stat-grid">
    <div class="stat-card green">
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg></div>
        <div class="stat-card-num">₱{{ number_format($data['total_sales']) }}</div>
        <div class="stat-card-label">Total Sales</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zM8 17H6v-2h2v2zm0-4H6v-2h2v2zm0-4H6V7h2v2zm4 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V7h2v2zm4 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V7h2v2z"/></svg></div>
        <div class="stat-card-num">{{ $data['total_orders'] }}</div>
        <div class="stat-card-label">Total Orders</div>
    </div>
    <div class="stat-card coral">
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 13h4v8H3v-8zm7-6h4v14h-4V7zm7-4h4v18h-4V3z"/></svg></div>
        <div class="stat-card-num">₱{{ number_format($data['total_commission']) }}</div>
        <div class="stat-card-label">Total Commission</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM5.2 5H2V3H0v2h2l3.6 7.59L4.25 15A2 2 0 0 0 6 18h14v-2H6.42a.25.25 0 0 1-.25-.25l.03-.12L7.1 14h9.45c.75 0 1.41-.41 1.75-1.03L21.7 6.5A1 1 0 0 0 20.83 5H5.2z"/></svg></div>
        <div class="stat-card-num">{{ $data['new_buyers'] }}</div>
        <div class="stat-card-label">New Buyers</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16l1 5v2a3 3 0 0 1-2 2.82V20H5v-6.18A3 3 0 0 1 3 11V9l1-5zm2 2-.6 3v2a1 1 0 0 0 2 0V9h2v2a1 1 0 0 0 2 0V9h2v2a1 1 0 0 0 2 0V9h2v2a1 1 0 0 0 2 0V9l-.6-3H6zM7 15v3h10v-3H7z"/></svg></div>
        <div class="stat-card-num">{{ $data['new_sellers'] }}</div>
        <div class="stat-card-label">New Sellers</div>
    </div>
</div>

<div class="grid-2">
    <!-- Sales Chart -->
    <div class="card">
        <div class="card-header"><span class="card-title">Sales Performance</span></div>
        <div class="card-body"><div id="salesChart"></div></div>
    </div>
    <!-- Top Sellers -->
    <div class="card">
        <div class="card-header"><span class="card-title">Top Sellers</span></div>
        <table>
            <thead><tr><th>Seller</th><th>Sales</th><th>Orders</th><th>Commission</th></tr></thead>
            <tbody>
            @foreach($data['top_sellers'] as $s)
            <tr>
                <td>{{ $s['name'] }}</td>
                <td>₱{{ number_format($s['sales']) }}</td>
                <td>{{ $s['orders'] }}</td>
                <td class="blade-inline-7">₱{{ number_format($s['commission']) }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div data-sales-chart data-sales='@json($data["monthly_sales"])' data-months='@json($data["months"])' hidden></div>
@section('scripts')
@vite('resources/js/views/admin-reports.js')
@endsection
@endsection
