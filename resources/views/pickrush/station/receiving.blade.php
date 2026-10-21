@extends('pickrush.layouts.app')
@section('title', 'Receiving')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Receiving</h1>
        <p>Scan every parcel that comes in: seller drop-offs, pickups riders bring back, and trips from the province hub.</p>
    </div>
</div>

@include('pickrush.partials.scan-box', ['action' => route('pickrush.station.receiving.scan'), 'button' => 'Receive'])

@if($trips->isNotEmpty())
    <section class="pr-section">
        <h2>Trips arriving</h2>
        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead><tr><th>Trip</th><th>From</th><th>Vehicle</th><th class="pr-num">Parcels</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach($trips as $trip)
                    <tr>
                        <td><strong>{{ $trip->manifest_no }}</strong><div class="pr-muted">{{ $trip->dispatched_at?->format('M d h:i A') }}</div></td>
                        <td>{{ $trip->fromHub->name }}</td>
                        <td>{{ $trip->vehicle_plate }} · {{ $trip->driver_name }}</td>
                        <td class="pr-num">{{ $trip->parcel_count }}</td>
                        <td><span class="pr-badge {{ $trip->status === 'received' ? 'pr-badge-warn' : 'pr-badge-brand' }}">{{ $trip->status === 'received' ? 'Checking' : 'On the road' }}</span></td>
                        <td><a href="{{ route('pickrush.station.trips.show', $trip) }}" class="pr-btn pr-btn-outline pr-btn-sm">Check trip</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

<div class="pr-two">
    <section class="pr-section">
        <h2>On the way here ({{ $inTransit->count() }})</h2>
        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead><tr><th>Tracking no.</th><th>Deliver to</th><th>Since</th></tr></thead>
                <tbody>
                @forelse($inTransit as $parcel)
                    <tr>
                        <td><a href="{{ route('pickrush.parcels.show', $parcel) }}">{{ $parcel->tracking_no }}</a>@if($parcel->is_return) <span class="pr-badge pr-badge-warn">Return</span>@endif</td>
                        <td>{{ $parcel->is_return ? $parcel->shipper_name : $parcel->consignee_name }}</td>
                        <td>{{ $parcel->updated_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="pr-muted">Nothing in transit to this station.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="pr-section">
        <h2>Seller parcels to collect ({{ $firstMile->count() }})</h2>
        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead><tr><th>Tracking no.</th><th>Seller</th><th>Pickup</th></tr></thead>
                <tbody>
                @forelse($firstMile as $parcel)
                    <tr>
                        <td><a href="{{ route('pickrush.parcels.show', $parcel) }}">{{ $parcel->tracking_no }}</a></td>
                        <td>{{ $parcel->shipper_name }}<div class="pr-muted">{{ $parcel->pickup_address }}</div></td>
                        <td>
                            @if($parcel->current_status->value === 'picked_up')<span class="pr-badge pr-badge-brand">With rider</span>
                            @elseif($parcel->openAssignment)<span class="pr-badge">{{ $parcel->openAssignment->rider->full_name }}</span>
                            @else<a href="{{ route('pickrush.station.dispatch', ['view' => 'pickups']) }}">Assign rider</a>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="pr-muted">No seller pickups waiting.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
