@extends('logistics.layout')
@section('title', 'Province Dashboard')
@section('no_intro', true)
@section('content')
<div class="page-heading">
    <div>
        <h1>{{ $hub->name }}</h1>
        <p>Receive linehaul trucks from other provinces and dispatch parcels bound for them. Your station hubs handle pickup and last-mile delivery.</p>
    </div>
    <div class="page-heading-actions">
        <a class="btn btn-coral" href="{{ route('province-hub.parcels') }}">Open Sorting Center</a>
    </div>
</div>

@if($stationHubs->isEmpty())
    <div class="callout callout--info"><div><strong>Add your first station hub.</strong> Parcels are only routed to towns that have an active station. <a href="{{ route('province-hub.stations') }}#create-station">Create a station hub</a></div></div>
@endif

<div class="stats">
    <a class="stat stat-link" href="{{ route('province-hub.parcels', ['status' => 'incoming_linehaul']) }}"><strong>{{ number_format($queueCounts['incoming_linehaul']) }}</strong><span>Incoming Linehaul</span><small>Trucks heading here — receive on arrival</small></a>
    <a class="stat stat-link" href="{{ route('province-hub.parcels', ['status' => 'ready_to_dispatch']) }}"><strong>{{ number_format($queueCounts['ready_to_dispatch']) }}</strong><span>Ready to Dispatch</span><small>Sorted at your stations, going to other provinces</small></a>
    <a class="stat stat-link" href="{{ route('province-hub.parcels', ['status' => 'outbound_linehaul']) }}"><strong>{{ number_format($queueCounts['outbound_linehaul']) }}</strong><span>Outbound Linehaul</span><small>Dispatched, not yet received</small></a>
    <a class="stat stat-link stat-alert" href="{{ route('province-hub.parcels', ['status' => 'delivery_failed']) }}"><strong>{{ number_format($queueCounts['delivery_failed']) }}</strong><span>Failed Deliveries</span><small>Handled by the station hubs</small></a>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Needs Your Action</span>
        <span class="cell-meta">Oldest first</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Parcel</th><th>Route</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($actionable as $order)
                <tr>
                    <td><span class="cell-title">{{ $order->order_number }}</span><span class="cell-meta">Updated {{ $order->updated_at->diffForHumans() }}</span></td>
                    <td><span class="route-path">{{ $order->originHub?->name ?? '—' }} <span class="route-arrow">→</span> {{ $order->destinationHub?->name ?? '—' }}</span><span class="cell-meta">{{ $order->originBranch?->name ?? '—' }} → {{ $order->destinationBranch?->name ?? '—' }}</span></td>
                    <td><x-order-status-badge :status="$order->status" /></td>
                    <td>
                        <div class="cell-actions">
                            @if($order->status === 'in_transit' && (int) $order->destination_hub_id === (int) $hub->id)
                                <form method="POST" action="{{ route('province-hub.receive-linehaul', $order) }}" data-confirm-message="Confirm {{ $order->order_number }} arrived at {{ $hub->name }}?">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-success btn-sm" type="submit">Receive</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('province-hub.dispatch-linehaul', $order) }}" data-confirm-message="Dispatch {{ $order->order_number }} to {{ $order->destinationHub?->name }}?">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-coral btn-sm" type="submit">Dispatch</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty-state"><strong>All caught up</strong><p>No linehaul is waiting to be received or dispatched.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Station Hubs ({{ $stationHubs->count() }})</span>
        <a class="btn btn-outline btn-sm" href="{{ route('province-hub.stations') }}">Manage stations</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Station</th><th>Operator</th><th>Active Riders</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($stationHubs as $station)
                <tr>
                    <td><span class="cell-title">{{ $station->name }}</span><span class="cell-meta">{{ $station->municipality?->name }}</span></td>
                    <td>{{ $station->assignedOperator?->full_name ?? '—' }}</td>
                    <td>
                        @if($station->active_riders_count === 0)
                            <span class="status-pill status-pill--warning">No riders</span>
                        @else
                            {{ $station->active_riders_count }}
                        @endif
                    </td>
                    <td><span class="status-pill status-pill--{{ $station->status }}">{{ ucfirst($station->status) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty-state"><strong>No station hubs yet</strong><p>Stations receive parcels from sellers and dispatch riders to buyers.</p><a class="btn btn-coral" href="{{ route('province-hub.stations') }}#create-station">Create a station hub</a></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
