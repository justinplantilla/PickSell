@extends('logistics.layout')
@section('title', 'Station Dashboard')
@section('no_intro', true)
@section('content')
<div class="page-heading">
    <div>
        <h1>{{ $station->name }}</h1>
        <p>Approve seller pickups, scan and sort parcels as they arrive, then assign riders for last-mile delivery in {{ $station->municipality?->name ?? 'your town' }}.</p>
    </div>
    <div class="page-heading-actions">
        <a class="btn btn-coral" href="{{ route('station-hub.parcels') }}">Open Station Operations</a>
    </div>
</div>

@php($pendingApplications = $station->pendingCourierApplications()->count())
@if($pendingApplications > 0)
    <div class="callout callout--info"><div><strong>{{ $pendingApplications }} rider {{ \Illuminate\Support\Str::plural('application', $pendingApplications) }} from {{ $station->municipality?->name }} waiting for your review.</strong> <a href="{{ route('station-hub.applications') }}">Review applications</a></div></div>
@endif
@if($activeRiders === 0)
    <div class="callout"><div><strong>No riders are assigned to this station yet,</strong> so sorted parcels cannot be delivered. <a href="{{ route('station-hub.riders') }}">Assign riders</a></div></div>
@elseif($stats['rider_unavailable'] > 0)
    <div class="callout"><div><strong>{{ $stats['rider_unavailable'] }} sorted {{ \Illuminate\Support\Str::plural('parcel', $stats['rider_unavailable']) }}</strong> are going to barangays no rider covers. <a href="{{ route('station-hub.parcels', ['status' => 'rider_unavailable']) }}">Review them</a> or <a href="{{ route('station-hub.riders') }}">extend rider coverage</a>.</div></div>
@endif

<div class="stats">
    <a class="stat stat-link" href="{{ route('station-hub.parcels', ['status' => 'ready_for_pickup']) }}"><strong>{{ $stats['pending_pickup'] }}</strong><span>Pickup Requests</span><small>Approve so a rider collects them</small></a>
    <a class="stat stat-link" href="{{ route('station-hub.parcels', ['status' => 'awaiting_scan']) }}"><strong>{{ $stats['to_scan'] }}</strong><span>Awaiting Scan</span><small>Scan when they reach the station</small></a>
    <a class="stat stat-link" href="{{ route('station-hub.parcels', ['status' => 'awaiting_sort']) }}"><strong>{{ $stats['to_sort'] }}</strong><span>Awaiting Sort</span><small>Scanned, needs a destination</small></a>
    <a class="stat stat-link" href="{{ route('station-hub.parcels', ['status' => 'sorted']) }}"><strong>{{ $stats['to_assign'] }}</strong><span>Ready for Rider</span><small>Sorted for delivery here</small></a>
    <a class="stat stat-link" href="{{ route('station-hub.parcels', ['status' => 'out_for_delivery']) }}"><strong>{{ $stats['on_road'] }}</strong><span>With Riders</span><small>Assigned or out for delivery</small></a>
    <a class="stat stat-link stat-alert" href="{{ route('station-hub.parcels', ['status' => 'delivery_failed']) }}"><strong>{{ $stats['failed'] }}</strong><span>Failed Deliveries</span><small>Retry or return to sender</small></a>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Recent Parcel Activity</span>
        <a class="btn btn-outline btn-sm" href="{{ route('station-hub.parcels') }}">View all</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Parcel</th><th>Status</th><th>Direction</th><th>Buyer Area</th><th>Rider</th></tr></thead>
            <tbody>
            @forelse($orders as $order)
                <tr>
                    <td><span class="cell-title">{{ $order->order_number }}</span><span class="cell-meta">Updated {{ $order->updated_at->diffForHumans() }}</span></td>
                    <td><x-order-status-badge :status="$order->status" /></td>
                    <td>
                        @if((int) $order->destination_branch_id === (int) $station->id && (int) $order->origin_branch_id === (int) $station->id)
                            Local
                        @elseif((int) $order->destination_branch_id === (int) $station->id)
                            Incoming<span class="cell-meta">from {{ $order->originBranch?->name ?? 'another station' }}</span>
                        @else
                            Outgoing<span class="cell-meta">to {{ $order->destinationBranch?->name ?? 'destination pending' }}</span>
                        @endif
                    </td>
                    <td>{{ collect([$order->buyer?->barangay, $order->buyer?->municipality])->filter()->join(', ') ?: '—' }}</td>
                    <td>{{ $order->courier?->full_name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state"><strong>No parcels yet</strong><p>Pickup requests from sellers in {{ $station->municipality?->name ?? 'your town' }} will show up here.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
