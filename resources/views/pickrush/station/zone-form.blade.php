@extends('pickrush.layouts.app')
@section('title', $zone->exists ? 'Edit '.$zone->name : 'New zone')

@section('content')
@php $selected = old('barangays', $selected); @endphp
<div class="pr-page-head">
    <div>
        <h1>{{ $zone->exists ? $zone->name : 'New zone' }}</h1>
        <p>Tick the barangays for this zone. A barangay already in another zone moves here; parcels already with a rider keep their rider.</p>
    </div>
</div>

<form method="POST" action="{{ $zone->exists ? route('pickrush.station.zones.update', $zone) : route('pickrush.station.zones.store') }}" class="pr-card">
    @csrf
    @if($zone->exists) @method('PUT') @endif
    <div class="pr-form-grid">
        <div class="pr-field"><label for="name">Zone name</label><input id="name" name="name" class="pr-input" value="{{ old('name', $zone->name) }}" placeholder="e.g. Zone A" required></div>
        <div class="pr-field"><label for="default_rider_id">Default rider</label>
            <select id="default_rider_id" name="default_rider_id" class="pr-select">
                <option value="">None</option>
                @foreach($riders as $rider)<option value="{{ $rider->id }}" @selected((int) old('default_rider_id', $zone->default_rider_id) === $rider->id)>{{ $rider->full_name }}{{ $rider->isActive() ? '' : ' ('.$rider->status.')' }}</option>@endforeach
            </select></div>
        <div class="pr-field"><label for="backup_rider_id">Backup rider</label>
            <select id="backup_rider_id" name="backup_rider_id" class="pr-select">
                <option value="">None</option>
                @foreach($riders as $rider)<option value="{{ $rider->id }}" @selected((int) old('backup_rider_id', $zone->backup_rider_id) === $rider->id)>{{ $rider->full_name }}</option>@endforeach
            </select></div>
        <div class="pr-field"><label for="max_parcels">Load cap (parcels at a time)</label><input id="max_parcels" name="max_parcels" type="number" min="1" class="pr-input" value="{{ old('max_parcels', $zone->max_parcels) }}"></div>
    </div>

    <h2 style="margin-top:1.25rem">Barangays</h2>
    <div class="pr-filters">
        <input type="search" class="pr-input" placeholder="Filter barangays" aria-label="Filter barangays"
               oninput="const q=this.value.toLowerCase();document.querySelectorAll('[data-brgy]').forEach(l=>l.style.display=l.dataset.brgy.includes(q)?'':'none')">
        <button type="button" class="pr-btn pr-btn-outline" onclick="document.querySelectorAll('[data-brgy]:not([style*=none]) input:not(:checked)').forEach(b=>{ if(!b.dataset.taken) b.checked=true })">Tick all unzoned shown</button>
    </div>
    <div class="pr-checklist">
        @forelse($barangays as $b)
            @php $elsewhere = $b->zone && (! $zone->exists || $b->zone->id !== $zone->id); @endphp
            <label data-brgy="{{ strtolower($b->name) }}">
                <input type="checkbox" name="barangays[]" value="{{ $b->code }}" @checked(in_array($b->code, $selected, true)) @if($elsewhere) data-taken="1" @endif>
                <span>{{ $b->name }}@if($elsewhere)<br><small>in {{ $b->zone->name }}</small>@endif</span>
            </label>
        @empty
            <p class="pr-muted">No barangays found for this municipality. Ask HQ to import the location list.</p>
        @endforelse
    </div>

    <div class="pr-actions"><button type="submit" class="pr-btn pr-btn-primary">Save zone</button>
        <a href="{{ route('pickrush.station.zones.index') }}" class="pr-btn pr-btn-outline">Cancel</a></div>
</form>
@endsection
