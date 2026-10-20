@extends('pickrush.layouts.app')
@section('title', 'Riders')

@section('content')
@php $isManager = \App\Pickrush\Support\Actor::staff()->role === \App\Pickrush\Enums\StaffRole::HubManager; @endphp
<div class="pr-page-head">
    <div>
        <h1>Riders</h1>
        <p>Accounts are created here after an in-person interview. There is no public sign-up.</p>
    </div>
    @if($isManager)<a href="{{ route('pickrush.station.riders.create') }}" class="pr-btn pr-btn-primary">Add rider</a>@endif
</div>

<nav class="pr-tabs" aria-label="Status">
    <a href="{{ route('pickrush.station.riders.index') }}" @class(['is-active' => ! $status])>All</a>
    @foreach(['pending' => 'Pending', 'under_review' => 'Verified, to approve', 'active' => 'Active', 'suspended' => 'Suspended', 'deactivated' => 'Deactivated', 'rejected' => 'Rejected'] as $value => $label)
        <a href="{{ route('pickrush.station.riders.index', ['status' => $value]) }}" @class(['is-active' => $status === $value])>{{ $label }}</a>
    @endforeach
</nav>

<div class="pr-table-wrap">
    <table class="pr-table">
        <thead><tr><th>Rider</th><th>Mobile</th><th>Vehicle</th><th>Zone</th><th>Status</th><th>Shift</th><th class="pr-num">Limits</th></tr></thead>
        <tbody>
        @forelse($riders as $rider)
            <tr>
                <td><a href="{{ route('pickrush.station.riders.show', $rider) }}"><strong>{{ $rider->full_name }}</strong></a>@if($rider->on_probation) <span class="pr-badge">Probation</span>@endif</td>
                <td>{{ $rider->mobile_no }}</td>
                <td>{{ ucfirst((string) $rider->vehicle_type) }} {{ $rider->plate_no }}</td>
                <td>{{ $rider->zone?->name ?? '—' }}</td>
                <td>@include('pickrush.station.partials.rider-status', ['status' => $rider->status])</td>
                <td>{{ $rider->shift_status === 'on_shift' ? 'On shift' : 'Off duty' }}</td>
                <td class="pr-num">₱{{ number_format((float) $rider->cod_limit) }} · {{ $rider->max_parcels }} parcels</td>
            </tr>
        @empty
            <tr><td colspan="7" class="pr-muted">No riders here yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
