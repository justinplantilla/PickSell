@extends('admin.layout')
@section('title', 'Generate Reports')

@section('content')
<!-- Date Filter -->
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="{{ $from }}" style="width:160px;">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="{{ $to }}" style="width:160px;">
            </div>
            <button type="submit" class="btn btn-coral">Generate</button>
            <a href="/admin/reports/export?from={{ $from }}&to={{ $to }}&type=sales" class="btn btn-outline">📄 Export Sales PDF</a>
            <a href="/admin/reports/export?from={{ $from }}&to={{ $to }}&type=commission" class="btn btn-outline">📄 Export Commission PDF</a>
        </form>
    </div>
</div>

<!-- Summary Stats -->
<div class="stat-grid">
    <div class="stat-card green">
        <div class="stat-card-icon">💰</div>
        <div class="stat-card-num">₱{{ number_format($data['total_sales']) }}</div>
        <div class="stat-card-label">Total Sales</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-card-icon">🧾</div>
        <div class="stat-card-num">{{ $data['total_orders'] }}</div>
        <div class="stat-card-label">Total Orders</div>
    </div>
    <div class="stat-card coral">
        <div class="stat-card-icon">📊</div>
        <div class="stat-card-num">₱{{ number_format($data['total_commission']) }}</div>
        <div class="stat-card-label">Total Commission</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon">🛒</div>
        <div class="stat-card-num">{{ $data['new_buyers'] }}</div>
        <div class="stat-card-label">New Buyers</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon">🏪</div>
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
                <td style="color:var(--coral);font-weight:700;">₱{{ number_format($s['commission']) }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<script>
new ApexCharts(document.getElementById('salesChart'), {
    chart: { type: 'bar', height: 280, toolbar: { show: false } },
    series: [{ name: 'Sales (₱)', data: {{ json_encode($data['monthly_sales']) }} }],
    xaxis: { categories: {{ json_encode($data['months']) }} },
    colors: ['#E8472A'],
    dataLabels: { enabled: false },
    plotOptions: { bar: { borderRadius: 4 } },
}).render();
</script>
@endsection
