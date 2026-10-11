@extends('logistics.layout')
@section('styles')
@vite('resources/css/views/logistics-dashboard.css')
@endsection
@section('title', 'Dashboard')
@section('content')
<h1 class="blade-inline-1">Logistics Dashboard</h1><p class="blade-inline-2">Branch workload and the parcels that need the next action.</p>
<div class="stats logistics-dashboard-stats">
    <a class="stat logistics-stat-link" href="{{ route('logistics.applications', ['status' => 'pending']) }}"><strong>{{ $stats['pending_couriers'] }}</strong><span>Pending Courier Applications</span></a>
    <a class="stat logistics-stat-link" href="{{ route('logistics.riders') }}"><strong>{{ $stats['approved_couriers'] }}</strong><span>Active Riders</span></a>
    <a class="stat logistics-stat-link" href="{{ route('logistics.parcels') }}"><strong>{{ $stats['to_sort'] }}</strong><span>Parcels To Sort</span></a>
    <a class="stat logistics-stat-link" href="{{ route('logistics.parcels', ['status' => 'assigned_to_rider']) }}"><strong>{{ $stats['assigned'] }}</strong><span>Assigned Parcels</span></a>
    <a class="stat logistics-stat-link logistics-stat-alert" href="{{ route('logistics.parcels', ['status' => 'delivery_failed']) }}"><strong>{{ $stats['failed'] }}</strong><span>Failed Deliveries</span></a>
    <a class="stat logistics-stat-link {{ $stats['missing_scan'] > 0 ? 'logistics-stat-alert' : '' }}" href="{{ route('logistics.parcels', ['status' => 'missing_scan']) }}"><strong>{{ $stats['missing_scan'] }}</strong><span>Scans Missing for 48+ Hours</span></a>
</div>
<div class="card"><div class="card-header"><span class="card-title">Recent Parcel Activity</span><a class="btn btn-coral" href="{{ route('logistics.parcels') }}">Open Sorting Center</a></div><div class="blade-inline-3"><table><thead><tr><th>Order</th><th>Destination</th><th>Tracking Status</th><th>Rider</th><th>Order Status</th></tr></thead><tbody>@forelse($orders as $order)<tr><td>{{ $order->order_number }}<div class="blade-inline-4">{{ $order->waybill_number ?? 'No waybill' }}</div></td><td>{{ $order->buyer->municipality ?? '—' }}, {{ $order->buyer->province ?? '—' }}</td><td>{{ $order->tracking_status ?? 'Awaiting sorting' }}</td><td>{{ $order->courier->full_name ?? 'Unassigned' }}</td><td><span class="badge badge-{{ $order->status }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span></td></tr>@empty<tr><td colspan="5" class="blade-inline-5">No parcel activity yet.</td></tr>@endforelse</tbody></table></div></div>
@endsection
