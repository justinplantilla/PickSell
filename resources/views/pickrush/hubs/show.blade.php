@extends('pickrush.layouts.app')
@section('title', $hub->name)

@section('content')
<div class="pr-page-head">
    <div>
        <h1>{{ $hub->name }}</h1>
        <p>{{ ucfirst($hub->type->value) }} hub · {{ $hub->code }} · <a href="{{ route('pickrush.hubs.index') }}">All hubs</a></p>
    </div>
    <span class="pr-badge {{ $hub->is_active ? 'pr-badge-ok' : 'pr-badge-bad' }}">{{ $hub->is_active ? 'Active' : 'Inactive' }}</span>
</div>

@if($gaps)
    <div class="pr-alert pr-alert-warn" role="status">
        <strong>Not ready yet</strong>
        <ul>@foreach($gaps as $gap)<li>{{ $gap }}</li>@endforeach</ul>
    </div>
@endif

<div class="pr-two">
    <section class="pr-card">
        <h2>Details</h2>
        <dl class="pr-dl">
            <dt>Province</dt><dd>{{ $hub->province_name }}</dd>
            @if($hub->municipality_name)<dt>City / municipality</dt><dd>{{ $hub->municipality_name }}</dd>@endif
            <dt>Sorts through</dt><dd>{{ $hub->parent?->name ?? '—' }}</dd>
            <dt>PSGC code</dt><dd>{{ $hub->psgc_code }}</dd>
            <dt>Address</dt><dd>{{ $hub->address ?? 'Not set' }}</dd>
        </dl>
    </section>
    <section class="pr-card">
        <h2>Staff</h2>
        @forelse($hub->staff as $member)
            <div style="margin-bottom:.5rem">
                <strong>{{ $member->full_name }}</strong> · {{ $member->role->label() }}
                @if($member->is_sample)<span class="pr-badge">Sample</span>@endif
                @unless($member->is_active)<span class="pr-badge pr-badge-warn">Inactive</span>@endunless
            </div>
        @empty
            <p class="pr-muted">No staff assigned.</p>
        @endforelse
    </section>
</div>

@if(\App\Pickrush\Support\Actor::staff()->role === \App\Pickrush\Enums\StaffRole::SuperAdmin)
    <section class="pr-card" style="margin-top:1rem">
        <h2>Edit hub</h2>
        <form method="POST" action="{{ route('pickrush.hubs.update', $hub) }}" class="pr-form-grid">
            @csrf @method('PUT')
            <div class="pr-field" style="grid-column:1/-1"><label for="address">Address</label><input id="address" name="address" class="pr-input" value="{{ old('address', $hub->address) }}"></div>
            <div class="pr-field"><label for="lat">Latitude</label><input id="lat" name="lat" type="number" step="any" class="pr-input" value="{{ old('lat', $hub->lat) }}"></div>
            <div class="pr-field"><label for="lng">Longitude</label><input id="lng" name="lng" type="number" step="any" class="pr-input" value="{{ old('lng', $hub->lng) }}"></div>
            <label class="pr-check"><input type="checkbox" name="is_active" value="1" @checked($hub->is_active)> Active (parcels can be routed here)</label>
            <div class="pr-field"><button type="submit" class="pr-btn pr-btn-primary">Save hub</button></div>
        </form>
        <p class="pr-muted"><a href="{{ route('pickrush.hq.staff.create', ['hub_id' => $hub->id]) }}">Add staff to this hub</a></p>
    </section>
@endif

@if($stations->isNotEmpty())
    <section class="pr-card" style="margin-top:1rem">
        <h2>Stations sorting through this hub ({{ $stations->count() }})</h2>
        <p>
            @foreach($stations as $station)
                <a href="{{ route('pickrush.hubs.show', $station) }}">{{ $station->name }}</a>@if(! $loop->last) · @endif
            @endforeach
        </p>
    </section>
@endif
@endsection
