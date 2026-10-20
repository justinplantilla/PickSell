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
