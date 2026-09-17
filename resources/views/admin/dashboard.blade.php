@extends('admin.layout')
@section('title', 'Dashboard')

@section('content')
<div class="stat-grid">
    <div class="stat-card coral">
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M6 2v6l2 2-2 2v6h12v-6l-2-2 2-2V2H6zm10 14.5V20H8v-3.5l4-4 4 4zm0-9L12 11.5 8 7.5V4h8v3.5z"/></svg></div>
        <div class="stat-card-num">{{ $stats['pending'] }}</div>
        <div class="stat-card-label">Pending Approvals</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM5.2 5H2V3H0v2h2l3.6 7.59L4.25 15A2 2 0 0 0 6 18h14v-2H6.42a.25.25 0 0 1-.25-.25l.03-.12L7.1 14h9.45c.75 0 1.41-.41 1.75-1.03L21.7 6.5A1 1 0 0 0 20.83 5H5.2z"/></svg></div>
        <div class="stat-card-num">{{ $stats['buyers'] }}</div>
        <div class="stat-card-label">Active Buyers</div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-9 3h2v2h-2V7zm0 4h2v6h-2v-6zM7 7h2v2H7V7zm0 4h2v6H7v-6zm10 6h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg></div>
        <div class="stat-card-num">{{ $stats['sellers'] }}</div>
        <div class="stat-card-label">Active Sellers</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2a3 3 0 0 0 6 0h6a3 3 0 0 0 6 0h2v-5l-3-4zM6 18.5A1.5 1.5 0 1 1 7.5 17 1.5 1.5 0 0 1 6 18.5zm13.5-9 1.96 2.5H17V9.5h2.5zm-1.5 9A1.5 1.5 0 1 1 19.5 17a1.5 1.5 0 0 1-1.5 1.5z"/></svg></div>
        <div class="stat-card-num">{{ $stats['couriers'] }}</div>
        <div class="stat-card-label">Active Couriers</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg></div>
        <div class="stat-card-num">{{ $stats['total'] }}</div>
        <div class="stat-card-label">Total Users</div>
    </div>
    <div class="stat-card red">
        <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM4 12c0-4.42 3.58-8 8-8 1.85 0 3.55.63 4.9 1.69L5.69 16.9A7.902 7.902 0 0 1 4 12zm8 8c-1.85 0-3.55-.63-4.9-1.69L18.31 7.1A7.902 7.902 0 0 1 20 12c0 4.42-3.58 8-8 8z"/></svg></div>
        <div class="stat-card-num">{{ $stats['suspended'] }}</div>
        <div class="stat-card-label">Suspended</div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><span class="card-title">User Distribution</span></div>
        <div class="card-body"><div id="userChart"></div></div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Registration Trend (Last 6 Months)</span></div>
        <div class="card-body"><div id="trendChart"></div></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Pending Applications</span>
        <a href="/admin/registrations" class="btn btn-outline btn-sm">View All</a>
    </div>
    @if($recentApps->isEmpty())
        <div class="card-body" style="color:#888;font-size:0.88rem;">No pending applications.</div>
    @else
    <table>
        <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Applied</th><th>Action</th></tr></thead>
        <tbody>
        @foreach($recentApps as $app)
        <tr>
            <td>{{ $app->full_name }}</td>
            <td><span class="badge badge-{{ $app->role }}">{{ ucfirst($app->role) }}</span></td>
            <td>{{ $app->email }}</td>
            <td>{{ $app->created_at->diffForHumans() }}</td>
            <td><a href="/admin/registrations/{{ $app->id }}" class="btn btn-coral btn-sm">Review</a></td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

<script>
new ApexCharts(document.getElementById('userChart'), {
    chart: { type: 'donut', height: 280 },
    series: [{{ $stats['buyers'] }}, {{ $stats['sellers'] }}, {{ $stats['couriers'] }}],
    labels: ['Buyers', 'Sellers', 'Couriers'],
    colors: ['#2563eb', '#16a34a', '#d97706'],
    legend: { position: 'bottom' },
    plotOptions: { pie: { donut: { size: '60%' } } },
}).render();

new ApexCharts(document.getElementById('trendChart'), {
    chart: { type: 'area', height: 280, toolbar: { show: false } },
    series: [{ name: 'Registrations', data: [4, 7, 5, 12, 9, {{ $stats['total'] }}] }],
    xaxis: { categories: ['Aug','Sep','Oct','Nov','Dec','Jan'] },
    colors: ['#E8472A'],
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
    stroke: { curve: 'smooth', width: 2 },
    dataLabels: { enabled: false },
}).render();
</script>
@endsection
