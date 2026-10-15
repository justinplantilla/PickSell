@extends('logistics.layout')
@section('styles')
@vite('resources/css/views/logistics-parcels.css')
@endsection
@section('title', 'Sorting Center')
@section('no_intro', true)
@section('content')
@php
    $queueSections = [
        'Inbound' => [
            ['Incoming Linehaul', 'incoming_linehaul'],
            ['Received Here', 'received_here'],
        ],
        'Outbound' => [
            ['Ready to Dispatch', 'ready_to_dispatch'],
            ['Outbound Linehaul', 'outbound_linehaul'],
        ],
        'Exceptions' => [
            ['Failed Deliveries', 'delivery_failed'],
        ],
    ];
    $queueHints = [
        'incoming_linehaul' => 'Trucks from other province hubs heading here. Receive each parcel when it arrives.',
        'received_here' => 'Received from linehaul and waiting for the destination station to sort and assign a rider.',
        'ready_to_dispatch' => 'Sorted at one of your stations and bound for another province. Dispatch them on the next truck.',
        'outbound_linehaul' => 'Dispatched from here, not yet received by the destination hub.',
        'delivery_failed' => 'Failed delivery attempts. The destination station follows these up.',
        'all' => 'Every parcel that starts or ends in this province.',
    ];
@endphp

<div class="page-heading">
    <div>
        <h1>Sorting Center</h1>
        <p>{{ $queueHints[$status] }}</p>
    </div>
    @if($status !== 'all')
        <div class="page-heading-actions"><a class="btn btn-outline" href="{{ route('province-hub.parcels') }}">Show all parcels</a></div>
    @endif
</div>

<section class="logistics-control-board logistics-control-board--3" aria-label="Linehaul queues">
    @foreach($queueSections as $section => $queues)
        <div class="queue-group">
            <h2>{{ $section }}</h2>
            <div class="queue-items">
                @foreach($queues as [$label, $filter])
                    <a class="queue-item {{ $status === $filter ? 'is-active' : '' }}" href="{{ route('province-hub.parcels', ['status' => $filter]) }}" @if($status === $filter) aria-current="page" @endif>
                        <span>{{ $label }}</span>
                        <strong>{{ number_format($queueCounts[$filter]) }}</strong>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</section>

<div class="card">
    <div class="card-header">
        <span class="card-title">{{ $status === 'all' ? 'All Parcels' : collect($queueSections)->flatten(1)->firstWhere(1, $status)[0] }}</span>
        <span class="cell-meta">{{ number_format($orders->total()) }} {{ \Illuminate\Support\Str::plural('parcel', $orders->total()) }}</span>
    </div>
    <div class="parcel-table-wrap">
        <table class="parcel-control-table">
            <thead>
                <tr><th>Parcel</th><th>Route</th><th>Status</th><th>Buyer Area</th><th>Last Scan</th><th>Action</th></tr>
            </thead>
            <tbody>
            @forelse($orders as $order)
                @php($lastScan = $order->parcelScans->sortByDesc('scanned_at')->first())
                <tr>
                    <td><strong>{{ $order->order_number }}</strong><span class="parcel-row-meta">{{ $order->waybill_number ?? 'No waybill' }}</span></td>
                    <td>
                        <span class="route-path">{{ $order->originHub?->name ?? '—' }} <span class="route-arrow">→</span> {{ $order->destinationHub?->name ?? '—' }}</span>
                        <span class="parcel-row-meta">{{ $order->originBranch?->name ?? 'Origin station pending' }} → {{ $order->destinationBranch?->name ?? 'Destination station pending' }}</span>
                    </td>
                    <td><x-order-status-badge :status="$order->status" /><span class="parcel-row-meta">{{ $order->tracking_status }}</span></td>
                    <td>{{ collect([$order->buyer?->municipality, $order->buyer?->province])->filter()->join(', ') ?: '—' }}</td>
                    <td>
                        @if($lastScan)
                            {{ $lastScan->scanned_at->diffForHumans() }}
                            <span class="parcel-row-meta">{{ ucfirst(str_replace('_', ' ', $lastScan->scan_type)) }}{{ $lastScan->location ? ' · '.$lastScan->location : '' }}</span>
                        @else — @endif
                    </td>
                    <td>
                        @if($order->status === 'in_transit' && (int) $order->destination_hub_id === (int) $hub->id)
                            <form method="POST" action="{{ route('province-hub.receive-linehaul', $order) }}" data-confirm-message="Confirm {{ $order->order_number }} arrived at {{ $hub->name }}?">
                                @csrf @method('PATCH')
                                <button class="btn btn-success" type="submit">Receive</button>
                            </form>
                        @elseif($order->status === 'sorted' && (int) $order->origin_hub_id === (int) $hub->id && $order->awaitingLinehaul())
                            <form method="POST" action="{{ route('province-hub.dispatch-linehaul', $order) }}" data-confirm-message="Dispatch {{ $order->order_number }} to {{ $order->destinationHub?->name }}?">
                                @csrf @method('PATCH')
                                <button class="btn btn-coral" type="submit">Dispatch to {{ $order->destinationHub?->name }}</button>
                            </form>
                        @elseif($order->status === 'in_transit')
                            <span class="parcel-action-hint">On the way to {{ $order->destinationHub?->name }}</span>
                        @elseif(in_array($order->status, ['at_sorting_center', 'sorted'], true) && (int) $order->destination_hub_id === (int) $hub->id && $order->destination_hub_received_at)
                            <span class="parcel-action-hint">Waiting for {{ $order->destinationBranch?->name ?? 'the destination station' }}</span>
                        @else
                            <span class="parcel-action-hint">Handled by the stations</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state"><strong>Nothing in this queue</strong><p>{{ $status === 'all' ? 'No parcels have passed through this province yet.' : 'You are all caught up here.' }}</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $orders->withQueryString()->links() }}
@endsection
