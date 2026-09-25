@extends('seller.layout')
@section('styles')
@vite('resources/css/views/seller-reports.css')
@endsection
@section('title', 'Reports')

@section('content')
<div class="card">
    <div class="card-header">
        <span class="card-title">Financial & Performance Report</span>
        <div class="report-actions">
        <form method="GET" class="blade-inline-1">
            <label class="blade-inline-2">From</label>
            <input type="date" name="from" value="{{ $from }}" class="form-control blade-inline-3">
            <label class="blade-inline-4">To</label>
            <input type="date" name="to" value="{{ $to }}" class="form-control blade-inline-5">
            <button type="submit" class="btn btn-coral btn-sm">Generate</button>
        </form>
        <a href="{{ route('seller.reports.pdf', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline btn-sm" target="_blank">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h6"/></svg>
            Generate PDF
        </a>
        </div>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card blue">
        <div class="stat-card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
        </div>
        <div class="stat-card-num">₱{{ number_format($totalSales, 2) }}</div>
        <div class="stat-card-label">Total Sales</div>
    </div>
    <div class="stat-card coral">
        <div class="stat-card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.36C18 2.06 15.94 0 13.36 0c-1.3 0-2.48.52-3.36 1.36C9.12.52 7.94 0 6.64 0 4.06 0 2 2.06 2 4.64c0 .48.11.92.18 1.36H0v14c0 1.1.9 2 2 2h20c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm0 14H4V8h16v10z"/></svg>
        </div>
        <div class="stat-card-num">{{ $totalOrders }}</div>
        <div class="stat-card-label">Total Orders</div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6h-6z"/></svg>
        </div>
        <div class="stat-card-num">₱{{ number_format($totalProfit, 2) }}</div>
        <div class="stat-card-label">Net Profit</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"/></svg>
        </div>
        <div class="stat-card-num">₱{{ $totalOrders > 0 ? number_format($totalSales / $totalOrders, 2) : '0.00' }}</div>
        <div class="stat-card-label">Avg. Order Value</div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><span class="card-title">Daily Sales</span></div>
        <div class="card-body"><div id="dailyChart" data-seller-daily-chart data-sales='@json($dailySales)' data-days='@json($days)'></div></div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Top Products</span></div>
        <div class="card-body blade-inline-6">
            @if($topProducts->isEmpty())
                <div class="blade-inline-7">No data for this period.</div>
            @else
            <table>
                <thead><tr><th>#</th><th>Product</th><th>Orders</th><th>Sales</th></tr></thead>
                <tbody>
                @foreach($topProducts as $i => $p)
                <tr>
                    <td class="blade-inline-8">{{ $i+1 }}</td>
                    <td>{{ $p['name'] }}</td>
                    <td>{{ $p['count'] }}</td>
                    <td>₱{{ number_format($p['sales'], 2) }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
@vite('resources/js/views/seller-reports.js')
@endsection
