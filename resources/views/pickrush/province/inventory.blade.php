@extends('pickrush.layouts.app')
@section('title', 'Inventory on hand')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Inventory on hand</h1>
        <p>{{ $total }} parcels in the center, longest-waiting first. Over {{ $agingHours }} hours is aging.</p>
    </div>
</div>

<nav class="pr-tabs" aria-label="Filter">
    <a href="{{ route('pickrush.province.inventory') }}" @class(['is-active' => ! $aging])>All ({{ $total }})</a>
    <a href="{{ route('pickrush.province.inventory', ['aging' => 1]) }}" @class(['is-active' => $aging])>Aging ({{ $agingCount }})</a>
</nav>

<div class="pr-table-wrap">
    <table class="pr-table">
        <thead><tr><th>Tracking no.</th><th>Status</th><th>Next</th><th>Sorted</th><th class="pr-num">Dwell</th></tr></thead>
        <tbody>
        @forelse($parcels as $parcel)
            <tr>
                <td><a href="{{ route('pickrush.parcels.show', $parcel) }}">{{ $parcel->tracking_no }}</a>@if($parcel->is_return) <span class="pr-badge pr-badge-warn">Return</span>@endif</td>
                <td><span @class(['pr-badge', 'pr-badge-bad' => $parcel->current_status->value === 'on_hold'])>{{ $parcel->current_status->label() }}</span></td>
                <td>{{ $parcel->nextHub?->name ?? '—' }}</td>
                <td>{{ $parcel->sorted_at ? $parcel->sorted_at->format('M d h:i A') : 'Not yet' }}</td>
                <td class="pr-num"><span @class(['pr-badge', 'pr-badge-warn' => $parcel->dwell_hours >= $agingHours && $parcel->dwell_hours < 48, 'pr-badge-bad' => $parcel->dwell_hours >= 48])>{{ $parcel->dwell_hours }} h</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="pr-muted">Nothing on hand.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
