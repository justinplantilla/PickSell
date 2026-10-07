@extends('courier.layout')
@section('styles')
@vite('resources/css/views/courier-dashboard.css')
@endsection
@section('title', 'Delivery Tasks')
@section('content')
<h1 class="blade-inline-1">Delivery Tasks</h1>
<p class="blade-inline-2">Assigned area: <strong class="blade-inline-3">{{ auth()->user()->delivery_area ?? 'Area not set' }}</strong></p>

<div class="stats">
    <div class="stat"><strong>{{ $stats['assigned'] }}</strong><span>Active Assignments</span></div>
    <div class="stat"><strong>{{ $stats['in_transit'] }}</strong><span>Out for Delivery</span></div>
    <div class="stat"><strong>{{ $stats['delivered'] }}</strong><span>Delivered</span></div>
    <div class="stat"><strong>₱{{ number_format($stats['earnings'], 2) }}</strong><span>Completed Commission</span></div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Assigned Parcels</span>
        <form method="GET"><select name="status" class="filter-select" data-submit-on-change>
            <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All</option>
            <option value="assigned_to_rider" {{ $status === 'assigned_to_rider' ? 'selected' : '' }}>Assigned</option>
            <option value="out_for_delivery" {{ $status === 'out_for_delivery' ? 'selected' : '' }}>Out for Delivery</option>
            <option value="delivered" {{ $status === 'delivered' ? 'selected' : '' }}>Delivered</option>
            <option value="delivery_failed" {{ $status === 'delivery_failed' ? 'selected' : '' }}>Delivery Failed</option>
            <option value="returned" {{ $status === 'returned' ? 'selected' : '' }}>Returned</option>
        </select></form>
    </div>
    <div class="blade-inline-4">
        <table>
            <thead><tr><th>Parcel</th><th>Buyer / Address</th><th>Seller</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($orders as $order)
            <tr>
                <td><strong>{{ $order->order_number }}</strong><div class="blade-inline-5">{{ $order->waybill_number ?? 'No waybill' }}</div></td>
                <td>
                    <strong>{{ $order->buyer->full_name ?? '—' }}</strong>
                    <div class="blade-inline-6">{{ $order->buyer->house_no }} {{ $order->buyer->street }}, {{ $order->buyer->barangay }}, {{ $order->buyer->municipality }}, {{ $order->buyer->province }}</div>
                    <div class="blade-inline-7">{{ $order->buyer->contact_no ?? '' }}</div>
                </td>
                <td>{{ $order->seller->business_name ?? $order->seller->full_name ?? '—' }}</td>
                <td><span class="badge badge-{{ $order->status }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span><div class="blade-inline-8">{{ $order->tracking_status ?? 'Assigned' }}</div></td>
                <td>
                    @if($order->status === 'assigned_to_rider')
                    <form method="POST" action="{{ route('courier.orders.status', $order) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="out_for_delivery"><button class="btn btn-primary" type="submit">Start Delivery</button></form>
                    @elseif($order->status === 'out_for_delivery')
                    <form method="POST" action="{{ route('courier.orders.status', $order) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="delivered"><button class="btn btn-success" type="submit">Mark Delivered</button></form>
                    <form method="POST" action="{{ route('courier.orders.status', $order) }}" class="courier-failure-form">@csrf @method('PATCH')<input type="hidden" name="status" value="delivery_failed"><label for="failure-reason-{{ $order->id }}">Delivery issue</label><textarea id="failure-reason-{{ $order->id }}" name="failure_reason" required minlength="5" maxlength="1000" rows="2" placeholder="Explain why delivery failed"></textarea><button class="btn btn-danger" type="submit">Report Failed</button></form>
                    @elseif($order->status === 'delivered' || $order->status === 'completed')
                    <span class="blade-inline-9">{{ $order->status === 'completed' ? 'Completed' : 'Delivered' }} {{ optional($order->delivered_at)->format('M d, Y') }}</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="blade-inline-10">No parcels assigned to you yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())<div class="dashboard-pagination">{{ $orders->withQueryString()->links() }}</div>@endif
</div>
@endsection
