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
            ['Verification Required', 'verification_required', $queueCounts['verification_required']],
        ],
        'Sorting' => [
            ['Awaiting Sort', 'awaiting_sort', $queueCounts['awaiting_sort']],
            ['Sorted', 'sorted', $queueCounts['sorted']],
            ['Destination Exception', 'destination_exception', $queueCounts['destination_exception']],
        ],
        'Dispatch' => [
            ['Assigned', 'assigned_to_rider', $queueCounts['assigned']],
            ['Out for Delivery', 'out_for_delivery', $queueCounts['out_for_delivery']],
        ],
        'Exceptions' => [
            ['Failed Delivery', 'delivery_failed', $queueCounts['failed_delivery']],
            ['Missing Scan', 'missing_scan', $queueCounts['missing_scan']],
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
                <option value="awaiting_scan" {{ $status === 'awaiting_scan' ? 'selected' : '' }}>Awaiting Scan</option>
                <option value="verification_required" {{ $status === 'verification_required' ? 'selected' : '' }}>Verification Required</option>
                <option value="awaiting_sort" {{ $status === 'awaiting_sort' ? 'selected' : '' }}>Awaiting Sort</option>
                <option value="sorted" {{ $status === 'sorted' ? 'selected' : '' }}>Sorted</option>
                <option value="assigned_to_rider" {{ $status === 'assigned_to_rider' ? 'selected' : '' }}>Assigned to Rider</option>
                <option value="out_for_delivery" {{ $status === 'out_for_delivery' ? 'selected' : '' }}>Out for Delivery</option>
                <option value="delivery_failed" {{ $status === 'delivery_failed' ? 'selected' : '' }}>Failed Delivery</option>
                <option value="delivered" {{ $status === 'delivered' ? 'selected' : '' }}>Delivered</option>
                <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
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
                    <th>Current Status / Next Valid Action</th>
                    <th>Destination Area / Seller</th>
                    <th>Courier</th>
                    <th>Last Scan / Last Updated</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($orders as $order)
                @php
                    $eligibleRiders = $eligibleCouriers->get($order->id, collect());
                    $openException = $order->logisticsExceptions->firstWhere('status', 'open');
                    $latestDeliveryEvent = $order->delivery?->logs?->last();
                    $area = $order->buyer
                        ? trim(collect([$order->buyer->barangay, $order->buyer->municipality, $order->buyer->province])->filter()->join(', '))
                        : 'Destination unavailable';
                    $destinationNeedsReview = !$order->destination_branch_id
                        || !$order->destination_barangay_id
                        || !$order->buyer
                        || !$order->buyer->barangay
                        || !$order->buyer->municipality
                        || !$order->buyer->province
                        || $order->destinationBranch?->municipality_id !== $order->destinationBarangay?->municipality_id;
                    $nextAction = match ($order->status) {
                        'ready_for_pickup' => 'Approve pickup',
                        'picked_up' => in_array($order->tracking_status, ['Pickup approved by logistics', 'Handed over to courier'], true) ? 'Scan parcel' : 'Verify pickup',
                        'at_sorting_center' => $order->tracking_status !== 'Received and scanned at sorting center'
                            ? 'Verify scan'
                            : ($destinationNeedsReview ? 'Verify destination' : 'Sort parcel'),
                        'sorted' => $eligibleRiders->isNotEmpty() ? 'Assign rider' : 'Rider unavailable for destination',
                        'assigned_to_rider' => 'Rider to start delivery',
                        'out_for_delivery' => 'Await delivery outcome',
                        'delivery_failed' => $order->delivery?->status === 'return_to_sender'
                            ? 'Confirm return to sender'
                            : 'Resolve delivery exception',
                        'delivered' => 'Await completion',
                        'completed' => 'No further action',
                        default => 'Review parcel',
                    };
                    $defaultExceptionType = match (true) {
                        $order->status === 'delivery_failed' => 'failed_delivery',
                        $destinationNeedsReview => 'wrong_destination',
                        $order->status === 'picked_up' && !in_array($order->tracking_status, ['Pickup approved by logistics', 'Handed over to courier'], true) => 'missing_scan',
                        $order->status === 'sorted' && $eligibleRiders->isEmpty() => 'rider_unavailable',
                        default => null,
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
                        <strong class="parcel-next-action">{{ $nextAction }}</strong>
                    </td>
                    <td>
                        <strong>{{ $area }}</strong>
                        <span class="parcel-row-meta">From {{ $order->seller?->business_name ?? $order->seller?->full_name ?? 'Unavailable' }}</span>
                    </td>
                    <td>{{ $order->courier?->full_name ?? 'Unassigned' }}</td>
                    <td>
                        @if($order->parcelScans->isNotEmpty())
                            {{ $order->parcelScans->first()->scanned_at->format('M j, Y g:i A') }}
                            <span class="parcel-row-meta">{{ ucfirst(str_replace('_', ' ', $order->parcelScans->first()->scan_type)) }}{{ $order->parcelScans->first()->location ? ' · ' . $order->parcelScans->first()->location : '' }}</span>
                        @else
                            —
                        @endif
                        <span class="parcel-row-meta">Updated <time datetime="{{ $order->updated_at->toIso8601String() }}">{{ $order->updated_at->diffForHumans() }}</time></span>
                    </td>
                    <td>
                        @if($order->status === 'ready_for_pickup')
                            <form method="POST" action="{{ route('logistics.parcels.approve-pickup', $order) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-success" type="submit">Approve Pickup</button>
                            </form>
                        @elseif($order->status === 'picked_up' && in_array($order->tracking_status, ['Pickup approved by logistics', 'Handed over to courier'], true))
                            <form method="POST" action="{{ route('logistics.parcels.scan', $order) }}">
                                @csrf @method('PATCH')
                                <details class="parcel-action-details">
                                    <summary class="btn btn-outline">Scan Parcel</summary>
                                    <div class="parcel-action-fields">
                                        <label>Scan type
                                            <select class="filter-select" name="scan_type">
                                                <option value="sorting_center_received">Sorting center received</option>
                                                <option value="label_checked">Label checked</option>
                                                <option value="condition_checked">Package condition checked</option>
                                            </select>
                                        </label>
                                        <label>Location <input class="filter-select" type="text" name="location" maxlength="150" placeholder="Hub or scan location"></label>
                                        <label>Notes <textarea class="filter-select" name="notes" maxlength="5000" rows="2" placeholder="Optional handling notes"></textarea></label>
                                        <button class="btn btn-coral" type="submit">Save Scan</button>
                                    </div>
                                </details>
                            </form>
                        @elseif($order->status === 'at_sorting_center' && $order->tracking_status === 'Received and scanned at sorting center' && !$destinationNeedsReview)
                            <form method="POST" action="{{ route('logistics.parcels.sort', $order) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline" type="submit">Sort Parcel</button>
                            </form>
                        @elseif($order->status === 'at_sorting_center' && $order->tracking_status === 'Received and scanned at sorting center' && $destinationNeedsReview)
                            <span class="parcel-action-hint">Correct the destination before sorting.</span>
                        @elseif($order->status === 'sorted' && $eligibleRiders->isNotEmpty())
                            <form method="POST" action="{{ route('logistics.parcels.assign', $order) }}" class="parcel-assign-form">
                                @csrf @method('PATCH')
                                <select class="filter-select" name="courier_id" required aria-label="Choose a courier for {{ $order->order_number }}">
                                    <option value="">Assign rider</option>
                                    @foreach($eligibleRiders as $courier)
                                        <option value="{{ $courier->id }}">{{ $courier->full_name }} ({{ $courier->delivery_area ?? 'Area not set' }})</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-coral" type="submit">Assign</button>
                            </form>
                        @elseif($order->status === 'delivery_failed' && $order->delivery?->status === 'return_to_sender')
                            <form method="POST" action="{{ route('logistics.parcels.returned', $order) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline" type="submit">Confirm Returned</button>
                            </form>
                        @elseif($order->status === 'delivery_failed')
                            <span class="parcel-action-hint">
                                {{ $order->delivery?->status === 'return_to_sender' ? 'Return received; confirm it above.' : 'Review the failed attempt and coordinate the next step.' }}
                                @if($order->delivery)
                                    <span class="parcel-row-meta">Attempt {{ $order->delivery->delivery_attempts }}{{ $latestDeliveryEvent?->note ? ' · '.$latestDeliveryEvent->note : '' }}</span>
                                @endif
                            </span>
                        @elseif($order->status === 'sorted')
                            <span class="parcel-action-hint">No approved rider covers this destination.</span>
                        @else
                            <span class="parcel-action-hint">No action available</span>
                        @endif
                        @if($openException)
                            <div class="parcel-exception-summary">
                                <strong>Open {{ str_replace('_', ' ', $openException->type) }} exception</strong>
                                <span>{{ $openException->description }}</span>
                                <form method="POST" action="{{ route('logistics.exceptions.resolve', $openException) }}">
                                    @csrf @method('PATCH')
                                    <label for="exception-resolution-{{ $openException->id }}">Resolution</label>
                                    <textarea class="filter-select" id="exception-resolution-{{ $openException->id }}" name="resolution" minlength="5" maxlength="5000" rows="2" required></textarea>
                                    <button class="btn btn-outline" type="submit">Resolve Exception</button>
                                </form>
                            </div>
                        @else
                            <details class="parcel-exception-details">
                                <summary>Open operational exception</summary>
                                <form method="POST" action="{{ route('logistics.parcels.exceptions.store', $order) }}">
                                    @csrf
                                    <label for="exception-type-{{ $order->id }}">Exception type</label>
                                    <select class="filter-select" id="exception-type-{{ $order->id }}" name="type" required>
                                        <option value="" {{ $defaultExceptionType === null ? 'selected' : '' }} disabled>Choose exception type</option>
                                        <option value="failed_delivery" {{ $defaultExceptionType === 'failed_delivery' ? 'selected' : '' }}>Failed delivery</option>
                                        <option value="missing_scan" {{ $defaultExceptionType === 'missing_scan' ? 'selected' : '' }}>Missing scan</option>
                                        <option value="wrong_destination" {{ $defaultExceptionType === 'wrong_destination' ? 'selected' : '' }}>Wrong destination</option>
                                        <option value="rider_unavailable" {{ $defaultExceptionType === 'rider_unavailable' ? 'selected' : '' }}>Rider unavailable</option>
                                    </select>
                                    <label for="exception-description-{{ $order->id }}">What needs attention?</label>
                                    <textarea class="filter-select" id="exception-description-{{ $order->id }}" name="description" minlength="5" maxlength="5000" rows="2" required></textarea>
                                    <button class="btn btn-outline" type="submit">Send to Admin</button>
                                </form>
                            </details>
                        @endif
                        @if($order->logisticsExceptions->isNotEmpty())
                            <div class="parcel-exception-history" aria-label="Recent exception history">
                                @foreach($order->logisticsExceptions->take(2) as $parcelException)
                                    <span>{{ ucfirst($parcelException->status) }} {{ str_replace('_', ' ', $parcelException->type) }} · {{ $parcelException->created_at->diffForHumans() }}</span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="parcel-empty">No parcels found for this queue.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $orders->withQueryString()->links() }}
@endsection
