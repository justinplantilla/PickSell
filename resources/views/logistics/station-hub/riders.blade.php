@extends('logistics.layout')
@section('title', 'Riders')
@section('no_intro', true)
@section('content')
@php
    $activeAssignments = $assignments->where('status', 'active');
    $coveredIds = $activeAssignments
        ->filter(fn ($assignment) => $assignment->rider?->status === 'approved')
        ->flatMap(fn ($assignment) => $assignment->barangays->pluck('barangay_id'))
        ->unique();
    $uncovered = $barangays->reject(fn ($barangay) => $coveredIds->contains($barangay->id));
    $riderOptions = $activeAssignments->pluck('rider')->filter()->where('status', 'approved')->merge($availableRiders);
@endphp
<div class="page-heading">
    <div>
        <h1>Riders</h1>
        <p>Riders deliver only to the barangays you assign them. A sorted parcel can be assigned once an approved rider covers its barangay.</p>
    </div>
    <div class="page-heading-actions">
        <form method="POST" action="{{ route('station-hub.barangays.import') }}" data-confirm-message="Load the official barangay list for {{ $station->municipality?->name }} from the PSGC directory?">
            @csrf
            <button class="btn btn-outline" type="submit" title="Adds any missing barangays from the official PSGC list">Load barangays</button>
        </form>
        <a class="btn btn-coral" href="#assign-coverage">+ Assign Coverage</a>
    </div>
</div>

@if($pendingApplications > 0)
    <div class="callout callout--info"><div><strong>{{ $pendingApplications }} rider {{ \Illuminate\Support\Str::plural('application', $pendingApplications) }} waiting for review.</strong> <a href="{{ route('station-hub.applications') }}">Review applications</a></div></div>
@endif

@if($barangays->isNotEmpty())
    @if($uncovered->isEmpty())
        <div class="callout callout--info"><div><strong>Every barangay in {{ $station->municipality?->name }} has a rider.</strong></div></div>
    @else
        <div class="callout"><div><strong>{{ $uncovered->count() }} of {{ $barangays->count() }} barangays have no rider:</strong> {{ $uncovered->pluck('name')->take(12)->join(', ') }}{{ $uncovered->count() > 12 ? ' and '.($uncovered->count() - 12).' more' : '' }}.</div></div>
    @endif
@endif

<div class="card">
    <div class="card-header"><span class="card-title">Station Riders</span><span class="cell-meta">{{ $activeAssignments->count() }} active</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Rider</th><th>Contact</th><th>Barangay Coverage</th><th>Account</th><th></th></tr></thead>
            <tbody>
            @forelse($assignments as $assignment)
                @php($rider = $assignment->rider)
                <tr>
                    <td><span class="cell-title">{{ $rider?->full_name ?? 'Unavailable' }}</span><span class="cell-meta">{{ $rider?->email }}</span></td>
                    <td>{{ $rider?->contact_no ?? '—' }}</td>
                    <td>
                        @forelse($assignment->barangays as $coverage)
                            <span>{{ $coverage->barangay?->name }}{{ $coverage->is_primary ? ' (primary)' : '' }}</span>@if(!$loop->last), @endif
                        @empty
                            <span class="status-pill status-pill--warning">No barangays</span>
                        @endforelse
                    </td>
                    <td>
                        @if($assignment->status !== 'active')
                            <span class="status-pill status-pill--inactive">Unassigned</span>
                        @elseif($rider?->status === 'approved')
                            <span class="status-pill status-pill--active">Active</span>
                        @else
                            <span class="status-pill status-pill--warning">{{ ucfirst($rider?->status ?? 'Unknown') }}</span>
                        @endif
                    </td>
                    <td>
                        <div class="cell-actions">
                            @if($rider && in_array($rider->status, ['approved', 'suspended'], true))
                                @php($nextStatus = $rider->status === 'approved' ? 'suspended' : 'approved')
                                <form method="POST" action="{{ route('station-hub.riders.status', $rider) }}"
                                    data-confirm-message="{{ $nextStatus === 'suspended' ? 'Suspend '.$rider->full_name.'? They will not receive new deliveries until reinstated.' : 'Reinstate '.$rider->full_name.'?' }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $nextStatus }}">
                                    <button class="btn btn-outline btn-sm" type="submit">{{ $nextStatus === 'suspended' ? 'Suspend' : 'Reinstate' }}</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state"><strong>No riders yet</strong><p>Assign an approved rider and the barangays they cover below.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card" id="assign-coverage">
    <div class="card-header"><span class="card-title">Assign Rider Coverage</span></div>
    @if($barangays->isEmpty())
        <div class="card-body"><div class="empty-state"><strong>No barangays loaded for {{ $station->municipality?->name ?? 'this town' }}</strong><p>Riders are assigned per barangay. Load the official list first.</p>
            <form method="POST" action="{{ route('station-hub.barangays.import') }}">@csrf<button class="btn btn-coral" type="submit">Load barangays</button></form>
        </div></div>
    @elseif($riderOptions->isEmpty())
        <div class="card-body"><div class="empty-state"><strong>No riders available</strong><p>Approve courier applications from {{ $station->municipality?->name }} to add riders. Approved couriers already working at another station cannot be added.</p><a class="btn btn-coral" href="{{ route('station-hub.applications') }}">Rider Applications</a></div></div>
    @else
        <form method="POST" action="{{ route('station-hub.riders.assign') }}" data-confirm-message="Assign this barangay to the selected rider?">
            @csrf
            <div class="form-section">
                <p>Pick a rider, then one barangay at a time. Riders already at this station keep their current barangays.</p>
                <div class="branch-form-grid">
                    <div>
                        <label for="rider-id">Rider *</label>
                        <select class="form-control" id="rider-id" name="rider_id" required>
                            <option value="" disabled {{ old('rider_id') ? '' : 'selected' }}>Choose a rider</option>
                            @foreach($riderOptions->unique('id') as $option)
                                <option value="{{ $option->id }}" @selected((int) old('rider_id') === $option->id)>{{ $option->full_name }}@if($activeAssignments->contains('user_id', $option->id)) — at this station @elseif($legacy = $option->branchAssignments?->first()?->branch) — moves from {{ $legacy->name }} @else — new @endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="barangay-id">Barangay *</label>
                        <select class="form-control" id="barangay-id" name="barangay_id" required>
                            <option value="" disabled {{ old('barangay_id') ? '' : 'selected' }}>Choose a barangay</option>
                            @if($uncovered->isNotEmpty())
                                <optgroup label="No rider yet">
                                    @foreach($uncovered as $barangay)<option value="{{ $barangay->id }}" @selected((int) old('barangay_id') === $barangay->id)>{{ $barangay->name }}</option>@endforeach
                                </optgroup>
                            @endif
                            @if($uncovered->count() < $barangays->count())
                                <optgroup label="Already covered">
                                    @foreach($barangays->diff($uncovered) as $barangay)<option value="{{ $barangay->id }}" @selected((int) old('barangay_id') === $barangay->id)>{{ $barangay->name }}</option>@endforeach
                                </optgroup>
                            @endif
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-actions"><button class="btn btn-coral" type="submit">Assign Coverage</button></div>
        </form>
    @endif
</div>
@endsection
