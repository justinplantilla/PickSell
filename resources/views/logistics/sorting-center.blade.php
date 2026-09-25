@extends('logistics.layout')
@section('title', 'Sorting Center')
@section('content')
<div class="page-heading">
    <h1>Sorting Center</h1>
    <p>Receive parcel, scan, read destination, sort by area, and assign the correct rider.</p>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Parcel Workflow</span>
        <form method="GET">
            <select class="filter-select" name="status" onchange="this.form.submit()">
                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Parcels</option>
                <option value="ready_for_pickup" {{ $status === 'ready_for_pickup' ? 'selected' : '' }}>Pickup Requests</option>
                <option value="picked_up" {{ $status === 'picked_up' ? 'selected' : '' }}>Picked Up</option>
                <option value="at_sorting_center" {{ $status === 'at_sorting_center' ? 'selected' : '' }}>At Sorting Center</option>
                <option value="assigned_to_rider" {{ $status === 'assigned_to_rider' ? 'selected' : '' }}>Assigned to Rider</option>
                <option value="out_for_delivery" {{ $status === 'out_for_delivery' ? 'selected' : '' }}>Out for Delivery</option>
                <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
            </select>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Parcel</th>
                    <th>Delivery Address</th>
                    <th>Destination Area</th>
                    <th>Status</th>
                    <th>Assigned Rider</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($orders as $order)
                @php
                    $area = $order->buyer ? trim(collect([$order->buyer->municipality, $order->buyer->province])->filter()->join(', ')) : 'Area unavailable';
                @endphp
                <tr>
                    <td>
                        <strong>{{ $order->order_number }}</strong>
                        <div>{{ $order->waybill_number ?? 'No waybill' }}</div>
                    </td>
                    <td>{{ $order->buyer->street ?? 'Address unavailable' }}, {{ $order->buyer->barangay ?? '' }}</td>
                    <td>{{ $area }}</td>
                    <td>
                        <span class="badge badge-{{ $order->status }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                        <div>{{ $order->tracking_status ?? 'Awaiting processing' }}</div>
                    </td>
                    <td>{{ $order->courier->full_name ?? 'Unassigned' }}</td>
                    <td>
                        @if($order->status !== 'completed')
                            @if($order->status === 'ready_for_pickup')
                                <form method="POST" action="{{ route('logistics.parcels.approve-pickup', $order) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-success" type="submit">Approve Pickup</button>
                                </form>
                            @elseif($order->status === 'picked_up')
                                <form method="POST" action="{{ route('logistics.parcels.scan', $order) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-outline" type="submit">Scan Parcel</button>
                                </form>
                            @elseif($order->status === 'at_sorting_center')
                                <form method="POST" action="{{ route('logistics.parcels.assign', $order) }}">
                                    @csrf @method('PATCH')
                                    <select class="filter-select" name="courier_id" required>
                                        <option value="">Assign rider</option>
                                        @foreach($couriers as $courier)
                                            <option value="{{ $courier->id }}">{{ $courier->full_name }} ({{ $courier->delivery_area ?? 'Area not set' }})</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-coral" type="submit">Assign</button>
                                </form>
                            @else
                                <span>In progress</span>
                            @endif
                        @else
                            <span>Completed</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No parcels found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $orders->links() }}
@endsection
