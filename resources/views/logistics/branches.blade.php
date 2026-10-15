@extends('logistics.layout')
@section('title', 'Province Hubs')
@section('no_intro', true)
@section('content')
<div class="page-heading">
    <div>
        <h1>Province Hubs</h1>
        <p>Each province hub gets its own portal and operator. The operator then creates the town station hubs in their province.</p>
    </div>
    <form method="GET" class="page-heading-actions" role="search">
        <input class="form-control" name="search" value="{{ $search }}" placeholder="Search hub or province" aria-label="Search province hubs">
        <button class="btn btn-outline" type="submit">Search</button>
        @if($search !== '')<a class="btn btn-outline" href="{{ route('logistics.branches') }}">Clear</a>@endif
    </form>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">{{ $search !== '' ? 'Results for “'.$search.'”' : 'All Province Hubs' }}</span>
        <span class="cell-meta">{{ $branches->count() }} {{ \Illuminate\Support\Str::plural('hub', $branches->count()) }}</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Hub</th><th>Operator</th><th>Stations</th><th>Portal</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($branches as $hub)
                <tr>
                    <td><span class="cell-title">{{ $hub->name }}</span><span class="cell-meta">{{ $hub->municipality?->name }}, {{ $hub->municipality?->province }}</span></td>
                    <td>
                        @if($hub->assignedOperator)
                            {{ $hub->assignedOperator->full_name }}<span class="cell-meta">{{ $hub->assignedOperator->email }}</span>
                        @else
                            <a class="status-pill status-pill--warning" href="{{ route('logistics.branches.edit', $hub) }}">Operator needed — assign</a>
                        @endif
                    </td>
                    <td>{{ $hub->children->count() }}</td>
                    <td>@if($hub->portalHost())<a class="portal-link" href="{{ $hub->portalUrl() }}" target="_blank" rel="noopener">{{ $hub->portalHost() }} ↗</a>@else — @endif</td>
                    <td><span class="status-pill status-pill--{{ $hub->status }}">{{ ucfirst($hub->status) }}</span></td>
                    <td><div class="cell-actions"><a class="btn btn-outline btn-sm" href="{{ route('logistics.branches.edit', $hub) }}">Edit</a></div></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state">
                    @if($search !== '')
                        <strong>No hubs match “{{ $search }}”</strong><p>Try another hub name or province.</p>
                    @else
                        <strong>No province hubs yet</strong><p>Use the form below to create your first one.</p>
                    @endif
                </div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card" id="create-hub">
    <div class="card-header"><span class="card-title">Create Province Hub</span></div>
    <form method="POST" action="{{ route('logistics.branches.store') }}" data-confirm-message="Create this province hub and its operator account?">
        @csrf
        <input type="hidden" name="hub_type" value="province_hub">
        <div class="form-section">
            <h2>1. Hub details</h2>
            <p>Where the sorting center is and the web address its operator will use.</p>
            <div class="branch-form-grid">
                <div><label for="hub-name">Hub name *</label><input class="form-control" id="hub-name" name="name" value="{{ old('name') }}" required placeholder="Laguna Province Hub"></div>
                <div>
                    <label for="hub-subdomain">Portal subdomain *</label>
                    <input class="form-control" id="hub-subdomain" name="subdomain" value="{{ old('subdomain') }}" required maxlength="{{ \App\Support\LogisticsDomains::MAX_SUBDOMAIN_LENGTH }}" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="laguna"
                        data-subdomain-input data-preview-target="hub-subdomain-preview" data-host-template="https://{{ \App\Support\LogisticsDomains::provinceHost('{subdomain}') }}">
                    <small>Portal address: <code id="hub-subdomain-preview"></code></small>
                </div>
                <div><label for="hub-province">Province *</label><select class="form-control" id="hub-province" name="province" data-selected="{{ old('province') }}" required><option value="">Loading provinces…</option></select></div>
                <div><label for="hub-municipality">Municipality / City *</label><select class="form-control" id="hub-municipality" name="municipality" data-selected="{{ old('municipality') }}" required disabled><option value="">Select a province first</option></select></div>
                <div class="full"><label for="hub-address">Address</label><input class="form-control" id="hub-address" name="address" value="{{ old('address') }}" placeholder="Operations center street address"></div>
            </div>
        </div>
        <div class="form-section">
            <h2>2. Operator account</h2>
            <p>The person who runs this hub. They can only sign in to this hub's portal.</p>
            @include('logistics.partials.operator-fields')
        </div>
        <div class="form-actions"><button class="btn btn-coral" type="submit">Create Province Hub</button></div>
    </form>
</div>
@endsection
@section('scripts')
@vite('resources/js/views/logistics-branches.js')
@endsection
