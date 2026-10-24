@extends('pickrush.layouts.app')
@section('title', $rider->full_name)

@section('content')
@php
    $me = \App\Pickrush\Support\Actor::staff();
    $isManager = $me->role === \App\Pickrush\Enums\StaffRole::HubManager;
    $uploaded = $rider->documents->pluck('doc_type')->all();
@endphp
<div class="pr-page-head">
    <div>
        <h1>{{ $rider->full_name }}</h1>
        <p>{{ $rider->mobile_no }}@if($rider->email) · {{ $rider->email }}@endif ·
            @if($rider->appliedInApp()) applied in the rider app {{ ($rider->applied_at ?? $rider->created_at)->diffForHumans() }}
            @else added by {{ $rider->creator?->full_name }} {{ $rider->created_at->diffForHumans() }}@endif
            · <a href="{{ route('pickrush.station.riders.index') }}">All riders</a></p>
    </div>
    @include('pickrush.station.partials.rider-status', ['status' => $rider->status])
</div>

@if($rider->status_reason)<div class="pr-alert pr-alert-warn">Reason: {{ $rider->status_reason }}</div>@endif
@if($rider->approved_at)<p class="pr-muted">Approved by {{ $rider->approver?->full_name }} on {{ $rider->approved_at->format('M d, Y') }}.</p>@endif

{{-- Application review: the hub manager decides; the rider has no access until approved. --}}
@if($rider->isApplication())
    @php $missing = array_diff($required, $uploaded); @endphp
    <section class="pr-card" style="margin-bottom:1rem">
        <h2>Application</h2>
        <dl class="pr-dl">
            <dt>Name</dt><dd>{{ $rider->full_name }}</dd>
            <dt>Birthdate</dt><dd>{{ $rider->birthdate?->format('M d, Y') ?? '—' }}@if($rider->birthdate) ({{ $rider->birthdate->age }} years old)@endif</dd>
            <dt>Address</dt><dd>{{ $rider->address ?? '—' }}</dd>
            <dt>Vehicle</dt><dd>{{ \App\Pickrush\Models\Rider::VEHICLE_TYPES[$rider->vehicle_type] ?? ucfirst((string) $rider->vehicle_type) }} · plate {{ $rider->plate_no ?? '—' }}</dd>
            <dt>License no.</dt><dd>{{ $rider->license_no ?? '—' }}</dd>
        </dl>
        <p><strong>Before approving:</strong> open the license and OR/CR below. Check that the license photo matches the applicant and has not expired, and that the plate on the OR/CR matches. Interview the rider at the station if anything is unclear.</p>
        @if($missing)
            <div class="pr-alert pr-alert-warn">Missing: {{ collect($missing)->map(fn ($type) => config("pickrush.rider_documents.{$type}"))->join(', ') }}. Reject with a reason so the rider can resubmit.</div>
        @endif
        @if($isManager)
            <form method="POST" action="{{ route('pickrush.station.riders.approve', $rider) }}" class="pr-actions">@csrf
                <button class="pr-btn pr-btn-primary" type="submit" @disabled($missing)>Approve rider</button></form>
            <p class="pr-muted">To reject, use Account below and give the reason. The rider sees it in the app and can resubmit.</p>
        @else
            <p class="pr-muted">The hub manager approves or rejects applications.</p>
        @endif
    </section>
@endif

<div class="pr-two">
    <section class="pr-card">
        <h2>Today</h2>
        <dl class="pr-dl">
            <dt>Shift</dt><dd>{{ $rider->shift_status === 'on_shift' ? 'On shift' : 'Off duty' }}</dd>
            <dt>Open tasks</dt><dd>{{ $openCount }} / {{ $rider->max_parcels }}</dd>
            <dt>Cash to remit</dt><dd>₱{{ number_format($cashExpected, 2) }} <a href="{{ route('pickrush.station.cash') }}">Cash</a></dd>
            <dt>COD limit</dt><dd>₱{{ number_format((float) $rider->cod_limit, 2) }}@if($rider->on_probation) (probation)@endif</dd>
            <dt>Zone</dt><dd>{{ $rider->zone?->name ?? '—' }}</dd>
        </dl>
        @if($rider->isActive())
            <form method="POST" action="{{ route('pickrush.station.riders.shift', $rider) }}" class="pr-actions">
                @csrf @method('PATCH')
                <input type="hidden" name="shift_status" value="{{ $rider->isOnShift() ? 'off_duty' : 'on_shift' }}">
                <button class="pr-btn pr-btn-outline" type="submit">{{ $rider->isOnShift() ? 'End shift' : 'Start shift' }} for rider</button>
            </form>
            <p class="pr-muted">Riders normally do this in the rider app.</p>
        @endif
        @if($open->isNotEmpty())
            <h3 style="margin-top:1rem">Open tasks</h3>
            <ul>@foreach($open as $task)<li><a href="{{ route('pickrush.parcels.show', $task->parcel) }}">{{ $task->parcel->tracking_no }}</a> · {{ $task->type }} · {{ str_replace('_', ' ', $task->status) }}</li>@endforeach</ul>
        @endif
    </section>

    <section class="pr-card">
        <h2>Documents</h2>
        <table class="pr-table">
            <tbody>
            @foreach(config('pickrush.rider_documents') as $type => $label)
                @php $docs = $rider->documents->where('doc_type', $type); @endphp
                <tr>
                    <td>{{ $label }}</td>
                    <td>
                        @forelse($docs as $doc)
                            <a href="{{ $documentLinks[$doc->id] }}" target="_blank" rel="noopener">{{ $doc->side ? 'View '.$doc->side : 'View' }}</a>
                            @if($doc->expiry_date)<span @class(['pr-badge', 'pr-badge-bad' => $doc->isExpired()])>{{ $doc->isExpired() ? 'Expired' : 'Expires' }} {{ $doc->expiry_date->format('M d, Y') }}</span>@endif
                        @empty
                            <span class="pr-muted">Not uploaded</span>
                        @endforelse
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p class="pr-muted">Uploaded by the rider in the app. Files are private: links work for 10 minutes and every view is logged.</p>
    </section>
</div>

@if($isManager)
    <section class="pr-card" style="margin-top:1rem">
        <h2>Details</h2>
        <form method="POST" action="{{ route('pickrush.station.riders.update', $rider) }}">
            @csrf @method('PUT')
            @include('pickrush.station.partials.rider-fields', ['rider' => $rider])
            <div class="pr-actions"><button type="submit" class="pr-btn pr-btn-outline">Save details</button></div>
        </form>
    </section>

    <div class="pr-two" style="margin-top:1rem">
        <section class="pr-card">
            <h2>Limits</h2>
            <form method="POST" action="{{ route('pickrush.station.riders.limits', $rider) }}" class="pr-form-grid">
                @csrf @method('PATCH')
                <div class="pr-field"><label for="cod_limit">COD limit (₱)</label><input id="cod_limit" name="cod_limit" type="number" step="0.01" min="0" class="pr-input" value="{{ $rider->cod_limit }}" required></div>
                <div class="pr-field"><label for="max_parcels">Parcels a day</label><input id="max_parcels" name="max_parcels" type="number" min="1" class="pr-input" value="{{ $rider->max_parcels }}" required></div>
                <label class="pr-check"><input type="checkbox" name="on_probation" value="1" @checked($rider->on_probation)> On probation</label>
                <div class="pr-field" style="justify-content:flex-end"><button class="pr-btn pr-btn-outline" type="submit">Save limits</button></div>
            </form>
        </section>
        <section class="pr-card">
            <h2>Account</h2>
            <form method="POST" action="{{ route('pickrush.station.riders.status', $rider) }}" class="pr-form-grid">
                @csrf
                <div class="pr-field"><label for="action">Action</label>
                    <select id="action" name="action" class="pr-select">
                        @if(in_array($rider->status, ['pending', 'under_review'], true))<option value="reject">Reject application</option>@endif
                        @if($rider->status === 'active')<option value="suspend">Suspend</option>@endif
                        @if($rider->status === 'suspended')<option value="reactivate">Reactivate</option>@endif
                        @unless(in_array($rider->status, ['deactivated', 'rejected'], true))<option value="deactivate">Deactivate (left Pickrush)</option>@endunless
                    </select></div>
                <div class="pr-field"><label for="reason">Reason</label><input id="reason" name="reason" class="pr-input"></div>
                <div class="pr-field" style="justify-content:flex-end"><button class="pr-btn pr-btn-danger" type="submit">Apply</button></div>
            </form>
        </section>
    </div>
@endif

@if($history->isNotEmpty())
    <section class="pr-card" style="margin-top:1rem">
        <h2>History</h2>
        <ul>@foreach($history as $entry)<li>{{ $entry->created_at->format('M d, Y h:i A') }} · {{ $entry->action }} @if($entry->after) · {{ collect($entry->after)->except(['updated_at', 'created_at'])->map(fn ($v, $k) => "{$k}: ".(is_scalar($v) ? $v : json_encode($v)))->join(', ') }}@endif</li>@endforeach</ul>
    </section>
@endif
@endsection
