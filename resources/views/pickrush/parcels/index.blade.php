@extends('pickrush.layouts.app')
@section('title', 'Parcels')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Parcels</h1>
        <p>Search by tracking number or recipient. You see the parcels your hub handles{{ \App\Pickrush\Support\Actor::staff()->isCompany() ? '; HQ sees the whole network' : '' }}.</p>
    </div>
</div>
<form method="GET" class="pr-filters" role="search">
    <input type="search" name="q" value="{{ request('q') }}" class="pr-input" style="flex:1" placeholder="Tracking number or recipient name" aria-label="Search parcels" autofocus>
    <button type="submit" class="pr-btn pr-btn-outline">Search</button>
</form>
<div class="pr-table-wrap">
    <table class="pr-table">
        <thead><tr><th>Tracking no.</th><th>Status</th><th>Route</th><th>Now at</th><th>Updated</th></tr></thead>
        <tbody>
        @forelse($parcels as $parcel)
            <tr>
                <td><a href="{{ route('pickrush.parcels.show', $parcel) }}">{{ $parcel->tracking_no }}</a>@if($parcel->needs_address_fix) <span class="pr-badge pr-badge-bad">Address to fix</span>@endif</td>
                <td><span class="pr-badge">{{ $parcel->current_status?->label() }}</span></td>
                <td>{{ $parcel->originHub?->name ?? '?' }} → {{ $parcel->destHub?->name ?? '?' }}</td>
                <td>{{ $parcel->currentHub?->name ?? '—' }}</td>
                <td>{{ $parcel->updated_at->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="pr-muted">No parcels found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pr-pagination">{{ $parcels->links() }}</div>
@endsection
