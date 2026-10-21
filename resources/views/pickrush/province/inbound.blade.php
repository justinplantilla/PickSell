@extends('pickrush.layouts.app')
@section('title', 'Inbound')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Inbound</h1>
        <p>Open the arriving trip and scan each parcel off the vehicle. Close the check when the vehicle is empty: anything not scanned is reported missing against the sending hub.</p>
    </div>
</div>

<section class="pr-section">
    <h2>Trips arriving ({{ $trips->count() }})</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Trip</th><th>From</th><th>Vehicle</th><th class="pr-num">Parcels</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($trips as $trip)
                <tr>
                    <td><strong>{{ $trip->manifest_no }}</strong><div class="pr-muted">left {{ $trip->dispatched_at?->diffForHumans() }}</div></td>
                    <td>{{ $trip->fromHub->name }}</td>
                    <td>{{ $trip->vehicle_plate }} · {{ $trip->driver_name }}</td>
                    <td class="pr-num">{{ $trip->parcel_count }}</td>
                    <td><span class="pr-badge {{ $trip->status === 'received' ? 'pr-badge-warn' : 'pr-badge-brand' }}">{{ $trip->status === 'received' ? 'Checking' : 'On the road' }}</span></td>
                    <td><a href="{{ route('pickrush.province.trips.show', $trip) }}" class="pr-btn pr-btn-primary pr-btn-sm">Check trip</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="pr-muted">No trips on the way.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<div class="pr-two">
    <section class="pr-section">
        <h2>Receive without the trip list</h2>
        @include('pickrush.partials.scan-box', ['action' => route('pickrush.province.inbound.scan'), 'button' => 'Receive'])
        <form method="POST" action="{{ route('pickrush.province.inbound.mis-sorted') }}" class="pr-card">
            @csrf
            <h3>Arrived here by mistake?</h3>
            <p class="pr-muted">A parcel that was going to another hub: receive it here and it will be rerouted. It is logged against the hub that sent it.</p>
            <div class="pr-inline">
                <input name="tracking_no" class="pr-input" placeholder="Tracking number" required aria-label="Tracking number of the mis-sorted parcel">
                <input name="note" class="pr-input" placeholder="Note (optional)" aria-label="Note">
                <button type="submit" class="pr-btn pr-btn-outline">Receive as mis-sorted</button>
            </div>
        </form>
    </section>
    <section class="pr-section">
        <h2>Parcels on the way ({{ $onTheWay->count() }})</h2>
        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead><tr><th>Tracking no.</th><th>From</th><th>Since</th></tr></thead>
                <tbody>
                @forelse($onTheWay as $parcel)
                    <tr><td><a href="{{ route('pickrush.parcels.show', $parcel) }}">{{ $parcel->tracking_no }}</a></td><td>{{ $parcel->originHub?->name }}</td><td>{{ $parcel->updated_at->diffForHumans() }}</td></tr>
                @empty
                    <tr><td colspan="3" class="pr-muted">Nothing in transit to this hub.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
