@extends('logistics.layout')
@section('title', 'Station Hubs')
@section('no_intro', true)
@section('content')
@php($province = $hub->municipality?->province)
<div class="page-heading">
    <div>
        <h1>Station Hubs</h1>
        <p>One station per town in {{ $province ?? 'this province' }}. Parcels from and to a town are routed to its active station.</p>
    </div>
    <div class="page-heading-actions"><a class="btn btn-coral" href="#create-station">+ New Station Hub</a></div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Stations ({{ $stations->count() }})</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Station</th><th>Operator</th><th>Active Riders</th><th>Portal</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($stations as $station)
                @php($activeRiders = $station->riderAssignments->where('status', 'active')->count())
                <tr>
                    <td><span class="cell-title">{{ $station->name }}</span><span class="cell-meta">{{ $station->municipality?->name }}</span></td>
                    <td>{{ $station->assignedOperator?->full_name ?? '—' }}<span class="cell-meta">{{ $station->assignedOperator?->email }}</span></td>
                    <td>
                        @if($activeRiders === 0)
                            <span class="status-pill status-pill--warning" title="Parcels for this town cannot be assigned until the station adds riders">No riders</span>
                        @else
                            {{ $activeRiders }}
                        @endif
                    </td>
                    <td>@if($station->portalHost())<a class="portal-link" href="{{ $station->portalUrl() }}" target="_blank" rel="noopener">{{ $station->portalHost() }} ↗</a>@else — @endif</td>
                    <td><span class="status-pill status-pill--{{ $station->status }}">{{ ucfirst($station->status) }}</span></td>
                    <td>
                        <div class="cell-actions">
                            @php($nextStatus = $station->status === 'active' ? 'inactive' : 'active')
                            <form method="POST" action="{{ route('province-hub.stations.status', $station) }}"
                                data-confirm-message="{{ $nextStatus === 'inactive' ? 'Deactivate '.$station->name.'? Its operator loses portal access and new parcels for '.$station->municipality?->name.' will not be routed to it.' : 'Reactivate '.$station->name.'?' }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $nextStatus }}">
                                <button class="btn btn-outline btn-sm" type="submit">{{ $nextStatus === 'inactive' ? 'Deactivate' : 'Reactivate' }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state"><strong>No station hubs yet</strong><p>Create one below for each town you deliver to.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card" id="create-station">
    <div class="card-header"><span class="card-title">Create Station Hub</span></div>
    <form method="POST" action="{{ route('province-hub.stations.store') }}" data-confirm-message="Create this station hub and its operator account?">
        @csrf
        <div class="form-section">
            <h2>1. Station details</h2>
            <p>The town this station serves in {{ $province ?? 'this province' }}, and its portal address.</p>
            <div class="branch-form-grid">
                <div><label for="station-municipality">Municipality / City *</label><input class="form-control" id="station-municipality" name="municipality" value="{{ old('municipality') }}" required placeholder="Santa Cruz"><small>Must match the town name buyers and sellers use in their addresses.</small></div>
                <div><label for="station-name">Station name *</label><input class="form-control" id="station-name" name="name" value="{{ old('name') }}" required placeholder="Santa Cruz Station Hub"></div>
                <div>
                    <label for="station-subdomain">Portal subdomain *</label>
                    <input class="form-control" id="station-subdomain" name="subdomain" value="{{ old('subdomain') }}" required maxlength="{{ \App\Support\LogisticsDomains::MAX_SUBDOMAIN_LENGTH }}" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="santa-cruz"
                        data-subdomain-input data-slug-source="station-municipality" data-preview-target="station-subdomain-preview" data-host-template="https://{{ \App\Support\LogisticsDomains::stationHost('{subdomain}') }}">
                    <small>Portal address: <code id="station-subdomain-preview"></code></small>
                </div>
                <div><label for="station-address">Station address</label><input class="form-control" id="station-address" name="address" value="{{ old('address') }}" placeholder="Street address"></div>
            </div>
        </div>
        <div class="form-section">
            <h2>2. Operator account</h2>
            <p>The person who runs this station. They can only sign in to this station's portal.</p>
            @include('logistics.partials.operator-fields')
        </div>
        <div class="form-actions"><button class="btn btn-coral" type="submit">Create Station Hub</button></div>
    </form>
</div>
@endsection
