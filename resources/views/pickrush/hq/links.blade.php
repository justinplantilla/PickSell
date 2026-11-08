@extends('pickrush.layouts.app')
@section('title', 'Province links')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Province links</h1>
        <p>Neighboring province hubs, by land or ferry. A province hub only sends trips to its own stations and to these neighbors; parcels for farther provinces relay hub by hub along the fastest chain. Turn a link off when the road or ferry is closed: parcels are routed around it from then on.{{ $canEdit ? '' : ' Only a super admin can change links.' }}</p>
    </div>
</div>

@if($disconnected->isNotEmpty())
    <div class="pr-alert pr-alert-bad">
        <strong>No route to {{ $disconnected->count() }} {{ \Illuminate\Support\Str::plural('hub', $disconnected->count()) }}:</strong>
        {{ $disconnected->pluck('name')->join(', ') }}. Parcels to or from them go to the exception bin until a link reaches them.
    </div>
@endif

@if($canEdit)
    <section class="pr-card pr-section">
        <h2>Add a link</h2>
        <form method="POST" action="{{ route('pickrush.links.store') }}" class="pr-form-grid">
            @csrf
            @foreach(['hub_one' => 'Province hub', 'hub_other' => 'Neighbor'] as $field => $label)
                <div class="pr-field"><label for="{{ $field }}">{{ $label }}</label>
                    <select id="{{ $field }}" name="{{ $field }}" class="pr-select" required>
                        <option value="">Pick a hub…</option>
                        @foreach($provinces as $hub)<option value="{{ $hub->id }}" @selected((int) old($field) === $hub->id)>{{ $hub->name }}</option>@endforeach
                    </select>
                    @error($field)<span class="pr-field-error">{{ $message }}</span>@enderror
                </div>
            @endforeach
            <div class="pr-field"><label for="mode">By</label>
                <select id="mode" name="mode" class="pr-select">@foreach(\App\Pickrush\Models\HubLink::MODES as $value => $label)<option value="{{ $value }}" @selected(old('mode') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="pr-field"><label for="travel_hours">Travel hours</label>
                <input id="travel_hours" name="travel_hours" type="number" step="0.5" min="0.5" class="pr-input" value="{{ old('travel_hours') }}" required>
                <small class="pr-muted">Door to door, including ferry waiting time.</small></div>
            <div class="pr-field" style="justify-content:flex-end"><button type="submit" class="pr-btn pr-btn-primary">Add link</button></div>
        </form>
    </section>
@endif

<section class="pr-section">
    <form method="GET" class="pr-inline" style="margin-bottom:.75rem">
        <input name="q" value="{{ $q }}" class="pr-input" placeholder="Find a province hub…" aria-label="Find a province hub">
        <button type="submit" class="pr-btn pr-btn-outline">Search</button>
        @if($q !== '')<a href="{{ route('pickrush.links.index') }}">Clear</a>@endif
    </form>
    <h2>Links ({{ $links->count() }})</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Between</th><th>By</th><th class="pr-num">Hours</th><th>Status</th>@if($canEdit)<th></th>@endif</tr></thead>
            <tbody>
            @forelse($links as $link)
                <tr>
                    <td>{{ $link->hubA->name }} ↔ {{ $link->hubB->name }}</td>
                    @if($canEdit)
                        <td colspan="4">
                            <form method="POST" action="{{ route('pickrush.links.update', $link) }}" class="pr-inline">
                                @csrf @method('PUT')
                                <select name="mode" class="pr-select" aria-label="By">@foreach(\App\Pickrush\Models\HubLink::MODES as $value => $label)<option value="{{ $value }}" @selected($link->mode === $value)>{{ $label }}</option>@endforeach</select>
                                <input name="travel_hours" type="number" step="0.5" min="0.5" class="pr-input" style="width:6rem" value="{{ rtrim(rtrim(number_format($link->travel_hours, 1, '.', ''), '0'), '.') }}" aria-label="Travel hours" required>
                                <label class="pr-check"><input type="checkbox" name="is_active" value="1" @checked($link->is_active)> In use</label>
                                <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm">Save</button>
                            </form>
                        </td>
                    @else
                        <td>{{ \App\Pickrush\Models\HubLink::MODES[$link->mode] }}</td>
                        <td class="pr-num">{{ rtrim(rtrim(number_format($link->travel_hours, 1, '.', ''), '0'), '.') }}</td>
                        <td><span @class(['pr-badge', 'pr-badge-ok' => $link->is_active, 'pr-badge-bad' => ! $link->is_active])>{{ $link->is_active ? 'In use' : 'Off' }}</span></td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="5" class="pr-muted">No links{{ $q !== '' ? ' match' : ' yet' }}.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
