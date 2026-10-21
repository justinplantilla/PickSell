@extends('pickrush.layouts.app')
@section('title', 'Trips')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Trips</h1>
        <p>Parcels leave for {{ $province?->name }} on a trip: seller parcels for other cities, and returns going back to sellers.</p>
    </div>
    <form method="POST" action="{{ route('pickrush.station.trips.store') }}">
        @csrf
        <button type="submit" class="pr-btn pr-btn-primary">Start a trip to {{ $province?->name }}</button>
    </form>
</div>

@if($drafts->isNotEmpty())
    <div class="pr-alert pr-alert-warn">
        <strong>Trips being loaded:</strong>
        @foreach($drafts as $draft)
            <a href="{{ route('pickrush.station.trips.show', $draft) }}">{{ $draft->manifest_no }}</a> ({{ $draft->parcel_count }} parcels)@if(! $loop->last), @endif
        @endforeach
    </div>
@endif

<section class="pr-section">
    <h2>Ready to leave ({{ $ready->count() }})</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Tracking no.</th><th>Going to</th><th>At station since</th></tr></thead>
            <tbody>
            @forelse($ready as $parcel)
                <tr>
                    <td><a href="{{ route('pickrush.parcels.show', $parcel) }}">{{ $parcel->tracking_no }}</a>@if($parcel->is_return) <span class="pr-badge pr-badge-warn">Return</span>@endif</td>
                    <td>{{ $parcel->is_return ? 'Seller: '.$parcel->originHub?->name : $parcel->destHub?->name ?? 'Address to fix' }}</td>
                    <td>{{ $parcel->updated_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="pr-muted">Nothing waiting to leave.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="pr-section">
    <h2>Recent trips</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Trip</th><th>Route</th><th class="pr-num">Parcels</th><th>Status</th><th>Updated</th></tr></thead>
            <tbody>
            @forelse($recent as $trip)
                <tr>
                    <td><a href="{{ route('pickrush.station.trips.show', $trip) }}">{{ $trip->manifest_no }}</a></td>
                    <td>{{ $trip->fromHub->name }} → {{ $trip->toHub->name }}</td>
                    <td class="pr-num">{{ $trip->parcel_count }}</td>
                    <td><span class="pr-badge">{{ ucfirst($trip->status) }}</span></td>
                    <td>{{ $trip->updated_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="pr-muted">No trips yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
