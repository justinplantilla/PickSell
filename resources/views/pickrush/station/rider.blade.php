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
        <p>{{ $rider->mobile_no }} · created by {{ $rider->creator?->full_name }} {{ $rider->created_at->diffForHumans() }} · <a href="{{ route('pickrush.station.riders.index') }}">All riders</a></p>
    </div>
    @include('pickrush.station.partials.rider-status', ['status' => $rider->status])
</div>

@if($rider->status_reason)<div class="pr-alert pr-alert-warn">Reason: {{ $rider->status_reason }}</div>@endif

{{-- Next step in onboarding --}}
@if($rider->status === 'pending')
    <div class="pr-alert pr-alert-warn">
        <strong>Step 2: a second staff member verifies identity, licence validity and that the OR/CR plate matches.</strong>
        @if($rider->created_by === $me->id)<div>You created this account, so someone else must verify it.</div>
        @else
            <form method="POST" action="{{ route('pickrush.station.riders.verify', $rider) }}" class="pr-actions">@csrf
                <button class="pr-btn pr-btn-primary" type="submit">I checked the documents in person: verify</button></form>
        @endif
    </div>
@elseif($rider->status === 'under_review')
    <div class="pr-alert pr-alert-warn">
        <strong>Step 3: verified by {{ $rider->verifier?->full_name }}. The hub manager approves the account.</strong>
        @if($isManager)
            <form method="POST" action="{{ route('pickrush.station.riders.approve', $rider) }}" class="pr-actions">@csrf
                <button class="pr-btn pr-btn-primary" type="submit">Approve rider</button></form>
        @endif
    </div>
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
                    <td>{{ $label }}@if(in_array($type, $required, true)) <span class="pr-muted">(required)</span>@endif</td>
                    <td>
                        @forelse($docs as $doc)
                            <a href="{{ $documentLinks[$doc->id] }}" target="_blank" rel="noopener">View</a>
                            @if($doc->expiry_date)<span @class(['pr-badge', 'pr-badge-bad' => $doc->isExpired()])>{{ $doc->isExpired() ? 'Expired' : 'Expires' }} {{ $doc->expiry_date->format('M d, Y') }}</span>@endif
                        @empty
                            <span class="pr-muted">Not uploaded</span>
                        @endforelse
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @if($isManager)
            <form method="POST" action="{{ route('pickrush.station.riders.documents.store', $rider) }}" enctype="multipart/form-data" class="pr-form-grid" style="margin-top:1rem">
                @csrf
                <div class="pr-field"><label for="doc_type">Document</label>
                    <select id="doc_type" name="doc_type" class="pr-select">@foreach(config('pickrush.rider_documents') as $type => $label)<option value="{{ $type }}">{{ $label }}</option>@endforeach</select></div>
                <div class="pr-field"><label for="file">File (JPG, PNG, PDF)</label><input id="file" type="file" name="file" accept=".jpg,.jpeg,.png,.pdf" required></div>
                <div class="pr-field"><label for="expiry_date">Expiry date</label><input id="expiry_date" type="date" name="expiry_date" class="pr-input"></div>
                <div class="pr-field" style="justify-content:flex-end"><button class="pr-btn pr-btn-outline" type="submit">Upload</button></div>
            </form>
            <p class="pr-muted">Files are private. Links open for 10 minutes and every view is logged.</p>
        @endif
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
