@extends('logistics.layout')
@section('styles')
@vite('resources/css/views/logistics-parcels.css')
@endsection
@section('title', 'Sorting Center')
@section('content')
@php
    $queueSections = [
        'Incoming' => [
            ['Awaiting Scan', 'awaiting_scan', $queueCounts['awaiting_scan']],
            ['Scanned', 'scanned', $queueCounts['scanned']],
            ['Verification Required', 'verification_required', $queueCounts['verification_required']],
        ],
        'Sorting' => [
            ['Awaiting Sort', 'awaiting_sort', $queueCounts['awaiting_sort']],
            ['Sorted', 'sorted', $queueCounts['sorted']],
            ['Destination Exception', 'destination_exception', $queueCounts['destination_exception']],
        ],
        'Dispatch' => [
            ['Awaiting Rider', 'awaiting_rider', $queueCounts['awaiting_rider']],
            ['Assigned', 'assigned_to_rider', $queueCounts['assigned']],
            ['Out for Delivery', 'out_for_delivery', $queueCounts['out_for_delivery']],
        ],
        'Exceptions' => [
            ['Failed Delivery', 'delivery_failed', $queueCounts['failed_delivery']],
            ['Missing Scan', 'missing_scan', $queueCounts['missing_scan']],
            ['Wrong Destination', 'destination_exception', $queueCounts['destination_exception']],
            ['Rider Unavailable', 'rider_unavailable', $queueCounts['rider_unavailable']],
        ],
    ];
@endphp

<div class="page-heading">
    <h1>Sorting Center Control Board</h1>
    <p>Track incoming parcels, sorting, dispatch, and operational exceptions.</p>
</div>

<section class="logistics-control-board" aria-label="Parcel queue summary">
    @foreach($queueSections as $section => $queues)
        <div class="queue-group">
            <h2>{{ $section }}</h2>
            <div class="queue-items">
                @foreach($queues as [$label, $filter, $count])
                    <a class="queue-item {{ $status === $filter ? 'is-active' : '' }}" href="{{ route('logistics.parcels', ['status' => $filter]) }}" @if($status === $filter) aria-current="page" @endif>
                        <span>{{ $label }}</span>
                        <strong>{{ number_format($count) }}</strong>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</section>

<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Parcel Workflow</span>
            <div class="parcel-workflow-hint">Receive → Scan → Read destination → Sort → Assign rider.</div>
        </div>
        <form method="GET">
            <select class="filter-select" name="status" data-submit-on-change aria-label="Filter parcels by status">
                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Parcels</option>
                <option value="ready_for_pickup" {{ $status === 'ready_for_pickup' ? 'selected' : '' }}>Pickup Requests</option>
                <option value="picked_up" {{ $status === 'picked_up' ? 'selected' : '' }}>Picked Up</option>
                <option value="at_sorting_center" {{ $status === 'at_sorting_center' ? 'selected' : '' }}>At Sorting Center</option>
                <option value="sorted" {{ $status === 'sorted' ? 'selected' : '' }}>Sorted</option>
                <option value="assigned_to_rider" {{ $status === 'assigned_to_rider' ? 'selected' : '' }}>Assigned to Rider</option>
                <option value="out_for_delivery" {{ $status === 'out_for_delivery' ? 'selected' : '' }}>Out for Delivery</option>
                <option value="delivery_failed" {{ $status === 'delivery_failed' ? 'selected' : '' }}>Failed Delivery</option>
                <option value="delivered" {{ $status === 'delivered' ? 'selected' : '' }}>Delivered</option>
                <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="verification_required" {{ $status === 'verification_required' ? 'selected' : '' }}>Verification Required</option>
                <option value="destination_exception" {{ $status === 'destination_exception' ? 'selected' : '' }}>Destination Exception</option>
                <option value="missing_scan" {{ $status === 'missing_scan' ? 'selected' : '' }}>Missing Scan</option>
                <option value="rider_unavailable" {{ $status === 'rider_unavailable' ? 'selected' : '' }}>Rider Unavailable</option>
            </select>
        </form>
    </div>
    <div class="parcel-table-wrap">
        <table class="parcel-control-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Current Status</th>
                    <th>Seller</th>
                    <th>Destination Area</th>
                    <th>Courier</th>
                    <th>Last Scan</th>
                    <th>Last Updated</th>
                    <th>Next Valid Action</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($orders as $order)
                @php
                    $area = $order->buyer
                        ? trim(collect([$order->buyer->barangay, $order->buyer->municipality, $order->buyer->province])->filter()->join(', '))
                        : 'Destination unavailable';
                    $destinationNeedsReview = !$order->destination_branch_id
                        || !$order->destination_barangay_id
                        || !$order->buyer
                        || !$order->buyer->barangay
                        || !$order->buyer->municipality
                        || !$order->buyer->province;
                    $nextAction = match ($order->status) {
                        'ready_for_pickup' => 'Approve pickup',
                        'picked_up' => in_array($order->tracking_status, ['Pickup approved by logistics', 'Handed over to courier'], true) ? 'Scan parcel' : 'Verify pickup',
                        'at_sorting_center' => $order->tracking_status !== 'Received and scanned at sorting center'
                            ? 'Verify scan'
                            : ($destinationNeedsReview ? 'Verify destination' : 'Sort parcel'),
                        'sorted' => $hasActiveCouriers ? 'Assign rider' : 'Rider unavailable',
                        'assigned_to_rider' => 'Rider to start delivery',
                        'out_for_delivery' => 'Await delivery outcome',
                        'delivery_failed' => 'Resolve delivery exception',
                        'delivered' => 'Await completion',
                        'completed' => 'No further action',
                        default => 'Review parcel',
                    };
                @endphp
                <tr>
                    <td>
                        <strong>{{ $order->order_number }}</strong>
                        <span class="parcel-row-meta">{{ $order->waybill_number ?? 'No waybill' }}</span>
                    </td>
                    <td>
                        <span class="badge badge-{{ $order->status }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                        <span class="parcel-row-meta">{{ $order->tracking_status ?? 'Awaiting processing' }}</span>
                    </td>
                    <td>{{ $order->seller?->business_name ?? $order->seller?->full_name ?? 'Unavailable' }}</td>
                    <td>{{ $area }}</td>
                    <td>{{ $order->courier?->full_name ?? 'Unassigned' }}</td>
                    <td>
                        @if($order->parcelScans->isNotEmpty())
                            {{ $order->parcelScans->first()->scanned_at->format('M j, Y g:i A') }}
                            <span class="parcel-row-meta">{{ ucfirst(str_replace('_', ' ', $order->parcelScans->first()->scan_type)) }}{{ $order->parcelScans->first()->location ? ' · ' . $order->parcelScans->first()->location : '' }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td><time datetime="{{ $order->updated_at->toIso8601String() }}">{{ $order->updated_at->diffForHumans() }}</time></td>
                    <td>{{ $nextAction }}</td>
                    <td>
                        @if($order->status === 'ready_for_pickup')
                            <form method="POST" action="{{ route('logistics.parcels.approve-pickup', $order) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-success" type="submit">Approve Pickup</button>
                            </form>
                        @elseif($order->status === 'picked_up' && in_array($order->tracking_status, ['Pickup approved by logistics', 'Handed over to courier'], true))
                            <form method="POST" action="{{ route('logistics.parcels.scan', $order) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline" type="submit">Scan Parcel</button>
                            </form>
                        @elseif($order->status === 'at_sorting_center' && $order->tracking_status === 'Received and scanned at sorting center')
                            <form method="POST" action="{{ route('logistics.parcels.sort', $order) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline" type="submit">Sort Parcel</button>
                            </form>
                        @elseif($order->status === 'sorted' && $hasActiveCouriers)
                            <form method="POST" action="{{ route('logistics.parcels.assign', $order) }}" class="parcel-assign-form">
                                @csrf @method('PATCH')
                                <select class="filter-select" name="courier_id" required aria-label="Choose a courier for {{ $order->order_number }}">
                                    <option value="">Assign rider</option>
                                    @foreach($couriers as $courier)
                                        <option value="{{ $courier->id }}">{{ $courier->full_name }} ({{ $courier->delivery_area ?? 'Area not set' }})</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-coral" type="submit">Assign</button>
                            </form>
                        @elseif($order->status === 'delivery_failed')
                            <span class="parcel-action-hint">Admin resolution required</span>
                        @else
                            <span class="parcel-action-hint">No action available</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="parcel-empty">No parcels found for this queue.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $orders->withQueryString()->links() }}
@endsection
