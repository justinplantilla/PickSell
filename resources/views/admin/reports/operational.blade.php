@extends('admin.layout')
@section('styles')
@vite('resources/css/views/admin-reports.css')
@endsection
@section('title', 'Reports')

@section('content')
<nav class="report-tabs" aria-label="Report type">
    <a href="{{ route('admin.reports', array_merge($filters, ['tab' => 'operational'])) }}" class="active" aria-current="page">Operational</a>
    <a href="{{ route('admin.reports', array_merge($filters, ['tab' => 'financial'])) }}">Financial</a>
</nav>

@include('admin.reports._filters', ['tab' => 'operational'])

<div class="stat-grid">
    <div class="stat-card blue"><div class="stat-card-num">{{ number_format($data['order_count']) }}</div><div class="stat-card-label">Orders in report</div></div>
    <div class="stat-card"><div class="stat-card-num">{{ number_format($data['average_fulfillment_hours'], 2) }} h</div><div class="stat-card-label">Average fulfillment time</div></div>
    <div class="stat-card coral"><div class="stat-card-num">{{ number_format($data['delivery_failures']) }}</div><div class="stat-card-label">Delivery failures</div></div>
    <div class="stat-card"><div class="stat-card-num">{{ number_format($data['return_count']) }}</div><div class="stat-card-label">Returns</div></div>
    <div class="stat-card"><div class="stat-card-num">{{ number_format($data['complaint_count']) }}</div><div class="stat-card-label">Complaints</div></div>
    <div class="stat-card"><div class="stat-card-num">{{ number_format($data['sorting_backlog']) }}</div><div class="stat-card-label">Sorting backlog</div></div>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card-header"><span class="card-title">Orders by Status</span></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Status</th><th>Orders</th></tr></thead>
                <tbody>
                    @forelse($data['status_totals'] as $status => $total)
                        <tr><td>{{ \App\Services\Orders\OrderLifecycleService::label($status) }}</td><td>{{ number_format($total) }}</td></tr>
                    @empty
                        <tr><td colspan="2">No orders match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <div class="card-header"><span class="card-title">Rider Workload</span></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Rider</th><th>Assigned orders</th></tr></thead>
                <tbody>
                    @forelse($data['rider_workload'] as $rider)
                        <tr><td>{{ $rider['name'] }}</td><td>{{ number_format($rider['orders']) }}</td></tr>
                    @empty
                        <tr><td colspan="2">No assigned rider orders match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<section class="card">
    <div class="card-header"><span class="card-title">Orders</span></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Order</th><th>Seller</th><th>Rider</th><th>Status</th><th>Created</th><th>Fulfilled</th></tr></thead>
            <tbody>
                @forelse($data['orders'] as $order)
                    <tr>
                        <td>{{ $order->order_number }}</td>
                        <td>{{ $order->seller?->full_name ?? '—' }}</td>
                        <td>{{ $order->courier?->full_name ?? 'Unassigned' }}</td>
                        <td>{{ \App\Services\Orders\OrderLifecycleService::label($order->status) }}</td>
                        <td>{{ $order->created_at?->format('M d, Y H:i') ?? '—' }}</td>
                        <td>{{ $order->completed_at?->format('M d, Y H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No orders match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="report-pagination">{{ $data['orders']->links() }}</div>
</section>
@endsection
