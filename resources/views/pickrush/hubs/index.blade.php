@extends('pickrush.layouts.app')
@section('title', 'Hubs')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Hubs</h1>
        <p>Every province hub and station hub in the network. All hubs are active; the readiness column shows which still need a manager.</p>
    </div>
</div>

<form method="GET" class="pr-filters" role="search">
    <input type="search" name="q" value="{{ $filters['q'] }}" class="pr-input" placeholder="Hub, province or code" aria-label="Search hubs">
    <select name="type" class="pr-select" aria-label="Hub type">
        <option value="all">All types</option>
        <option value="province" @selected($filters['type'] === 'province')>Province hubs</option>
        <option value="station" @selected($filters['type'] === 'station')>Station hubs</option>
    </select>
    <select name="readiness" class="pr-select" aria-label="Readiness">
        <option value="all">Any readiness</option>
        <option value="no_manager" @selected($filters['readiness'] === 'no_manager')>No real manager yet</option>
    </select>
    <button type="submit" class="pr-btn pr-btn-outline">Filter</button>
</form>

<div class="pr-table-wrap">
    <table class="pr-table">
        <thead><tr><th>Hub</th><th>Type</th><th>Province</th><th>Sorts through</th><th>Readiness</th></tr></thead>
        <tbody>
        @forelse($hubs as $row)
            <tr>
                <td><a href="{{ route('pickrush.hubs.show', $row) }}"><strong>{{ $row->name }}</strong></a><div class="pr-muted">{{ $row->code }}</div></td>
                <td><span class="pr-badge {{ $row->isProvince() ? 'pr-badge-brand' : '' }}">{{ ucfirst($row->type->value) }}</span></td>
                <td>{{ $row->province_name }}</td>
                <td>{{ $row->parent?->name ?? '—' }}</td>
                <td>
                    @if($row->has_manager)<span class="pr-badge pr-badge-ok">Ready</span>
                    @else<span class="pr-badge pr-badge-warn">No manager</span>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="pr-muted">No hubs match these filters.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pr-pagination">{{ $hubs->links() }}</div>
@endsection
