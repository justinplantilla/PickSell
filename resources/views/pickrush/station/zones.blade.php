@extends('pickrush.layouts.app')
@section('title', 'Zones')

@section('content')
@php $isManager = \App\Pickrush\Support\Actor::staff()->role === \App\Pickrush\Enums\StaffRole::HubManager; @endphp
<div class="pr-page-head">
    <div>
        <h1>Zones</h1>
        <p>Group barangays into zones, each a realistic day's route for one rider. Dispatch suggests the zone's rider for every parcel.</p>
    </div>
    @if($isManager)<a href="{{ route('pickrush.station.zones.create') }}" class="pr-btn pr-btn-primary">New zone</a>@endif
</div>

@if($unzoned->isNotEmpty())
    <div class="pr-alert pr-alert-warn">
        <strong>{{ $unzoned->count() }} of {{ $barangayTotal }} barangays are not in any zone.</strong> Parcels for them land in the "No zone" queue.
        <div class="pr-muted" style="margin-top:.3rem">{{ $unzoned->take(12)->pluck('name')->join(', ') }}{{ $unzoned->count() > 12 ? '…' : '' }}</div>
    </div>
@elseif($barangayTotal)
    <div class="pr-alert pr-alert-ok">Every barangay is in a zone.</div>
@endif

<div class="pr-table-wrap">
    <table class="pr-table">
        <thead><tr><th>Zone</th><th class="pr-num">Barangays</th><th>Default rider</th><th>Backup rider</th><th class="pr-num">Load cap</th>@if($isManager)<th></th>@endif</tr></thead>
        <tbody>
        @forelse($zones as $zone)
            <tr>
                <td><strong>{{ $zone->name }}</strong></td>
                <td class="pr-num">{{ $zone->barangay_links_count }}</td>
                <td>{{ $zone->defaultRider?->full_name ?? '—' }}</td>
                <td>{{ $zone->backupRider?->full_name ?? '—' }}</td>
                <td class="pr-num">{{ $zone->max_parcels ?? '—' }}</td>
                @if($isManager)<td><a href="{{ route('pickrush.station.zones.edit', $zone) }}" class="pr-btn pr-btn-outline pr-btn-sm">Edit</a></td>@endif
            </tr>
        @empty
            <tr><td colspan="6" class="pr-muted">No zones yet. With one or two riders, start with a single zone for the whole municipality.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
