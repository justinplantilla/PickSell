@extends('logistics.layout')
@section('title', 'Company Dashboard')
@section('no_intro', true)
@section('content')
<div class="page-heading">
    <div>
        <h1>Company Logistics</h1>
        <p>Your delivery network at a glance. Province hubs handle linehaul between provinces; their station hubs handle pickup, sorting and last-mile riders.</p>
    </div>
    <div class="page-heading-actions">
        <a class="btn btn-coral" href="{{ route('logistics.branches') }}#create-hub">+ New Province Hub</a>
    </div>
</div>

@if($hubs->isEmpty())
    <div class="callout callout--info">
        <div><strong>Start your network.</strong> Create a province hub and its operator account. That operator then adds the town station hubs in their province.
            <a href="{{ route('logistics.branches') }}#create-hub">Create the first province hub</a></div>
    </div>
@endif
@if($hubsNeedingOperator->isNotEmpty())
    <div class="callout">
        <div><strong>{{ $hubsNeedingOperator->count() }} {{ \Illuminate\Support\Str::plural('hub', $hubsNeedingOperator->count()) }} cannot be used yet</strong> because no operator account is assigned:
            @foreach($hubsNeedingOperator as $hub)<a href="{{ route('logistics.branches.edit', $hub) }}">{{ $hub->name }}</a>@if(!$loop->last), @endif @endforeach</div>
    </div>
@endif
@if($unroutedOrders > 0)
    <div class="callout">
        <div><strong>{{ number_format($unroutedOrders) }} {{ \Illuminate\Support\Str::plural('parcel', $unroutedOrders) }} have no station hub.</strong> The seller's or buyer's town has no active station yet, so these parcels do not appear in any hub portal. Ask the province hub to add a station for that town.</div>
    </div>
@endif

<div class="stats">
    <a class="stat stat-link" href="{{ route('logistics.branches') }}"><strong>{{ $stats['province_hubs'] }}</strong><span>Province Hubs</span><small>{{ $stats['station_hubs'] }} station {{ \Illuminate\Support\Str::plural('hub', $stats['station_hubs']) }} under them</small></a>
    <a class="stat stat-link" href="{{ route('logistics.reports.deliveries') }}"><strong>{{ number_format($stats['in_pipeline']) }}</strong><span>Parcels in the Network</span><small>Pickup to out-for-delivery</small></a>
    <a class="stat stat-link" href="{{ route('logistics.reports.deliveries', ['status' => 'in_transit']) }}"><strong>{{ number_format($stats['in_transit']) }}</strong><span>On Linehaul</span><small>Between province hubs</small></a>
    <a class="stat stat-link stat-alert" href="{{ route('logistics.reports.deliveries', ['status' => 'delivery_failed']) }}"><strong>{{ number_format($stats['failed']) }}</strong><span>Failed Deliveries</span><small>Need follow-up at a station</small></a>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Province Hubs</span>
        <a class="btn btn-outline btn-sm" href="{{ route('logistics.branches') }}">Manage hubs</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Province Hub</th><th>Operator</th><th>Stations</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($hubs as $hub)
                <tr>
                    <td><span class="cell-title">{{ $hub->name }}</span><span class="cell-meta">{{ $hub->municipality?->name }}, {{ $hub->municipality?->province }}</span></td>
                    <td>
                        @if($hub->assignedOperator)
                            {{ $hub->assignedOperator->full_name }}<span class="cell-meta">{{ $hub->assignedOperator->email }}</span>
                        @else
                            <span class="status-pill status-pill--warning">Operator needed</span>
                        @endif
                    </td>
                    <td>{{ $hub->children->count() }}</td>
                    <td><span class="status-pill status-pill--{{ $hub->status }}">{{ ucfirst($hub->status) }}</span></td>
                    <td><div class="cell-actions"><a class="btn btn-outline btn-sm" href="{{ route('logistics.branches.edit', $hub) }}">Edit</a></div></td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state"><strong>No province hubs yet</strong><p>Province hubs are the backbone of your network.</p><a class="btn btn-coral" href="{{ route('logistics.branches') }}#create-hub">Create a province hub</a></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
