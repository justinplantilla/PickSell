@extends('pickrush.layouts.app')
@section('title', 'Dispatch')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Dispatch</h1>
        <p>Tick parcels, then assign them to the suggested rider or pick one. Nothing is assigned without you. Pickups sent to a rider arrive in the rider app as a batch to accept.</p>
    </div>
</div>

@php $keep = array_filter(['seller' => $seller]); @endphp
<nav class="pr-tabs" aria-label="Queue">
    <a href="{{ route('pickrush.station.dispatch', $keep) }}" @class(['is-active' => $view === 'deliveries'])>Deliveries &amp; returns ({{ $counts['deliveries'] }})</a>
    <a href="{{ route('pickrush.station.dispatch', ['view' => 'no_zone'] + $keep) }}" @class(['is-active' => $view === 'no_zone'])>No zone ({{ $counts['no_zone'] }})</a>
    <a href="{{ route('pickrush.station.dispatch', ['view' => 'pickups'] + $keep) }}" @class(['is-active' => $view === 'pickups'])>Seller pickups ({{ $counts['pickups'] }})</a>
    <a href="{{ route('pickrush.station.dispatch', ['view' => 'assigned'] + $keep) }}" @class(['is-active' => $view === 'assigned'])>With riders</a>
</nav>

<form method="GET" class="pr-filters" role="search">
    @if($view !== 'deliveries')<input type="hidden" name="view" value="{{ $view }}">@endif
    <input type="search" name="seller" value="{{ $seller }}" list="dispatch-sellers" class="pr-input" style="flex:1;max-width:360px"
           placeholder="Store / seller name" aria-label="Filter by store or seller name">
    <datalist id="dispatch-sellers">
        @foreach($sellers as $s)<option value="{{ $s->name }}">{{ $s->count }} to pick up</option>@endforeach
    </datalist>
    <button type="submit" class="pr-btn pr-btn-outline">Filter</button>
    @if($seller !== '')<a href="{{ route('pickrush.station.dispatch', $view === 'deliveries' ? [] : ['view' => $view]) }}" class="pr-btn pr-btn-ghost">Clear</a>@endif
</form>

@if($view === 'pickups' && $seller === '' && $sellers->count() > 1)
    <p class="pr-muted" style="margin:0 0 .75rem">
        Sellers waiting:
        @foreach($sellers as $s)
            <a href="{{ route('pickrush.station.dispatch', ['view' => 'pickups', 'seller' => $s->name]) }}" class="pr-badge" style="text-decoration:none">{{ $s->name }} · {{ $s->count }}</a>
        @endforeach
    </p>
@endif

@if($view === 'assigned')
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Tracking no.</th><th>Rider</th><th>Task</th><th>Status</th><th>Since</th><th>Take back</th></tr></thead>
            <tbody>
            @forelse($assigned as $task)
                <tr>
                    <td><a href="{{ route('pickrush.parcels.show', $task->parcel) }}">{{ $task->parcel->tracking_no }}</a></td>
                    <td>{{ $task->rider->full_name }}</td>
                    <td>
                        {{ ucfirst($task->type) }}@if($task->type === 'delivery') · attempt {{ $task->attempt_no }}@endif
                        @if($task->type === 'pickup')<div class="pr-muted">{{ $task->parcel->shipper_name }}@if($task->batch) · #{{ $task->batch->batch_no }}@endif</div>@endif
                    </td>
                    <td>
                        @if($task->status === 'out_for_delivery')<span class="pr-badge pr-badge-brand">Out for delivery</span>
                        @elseif($task->batch?->status === 'offered')<span class="pr-badge pr-badge-warn">Waiting for rider to accept</span>
                        @elseif($task->batch?->status === 'accepted')<span class="pr-badge pr-badge-ok">Accepted by rider</span>
                        @else<span class="pr-badge">Assigned</span>@endif
                    </td>
                    <td>{{ $task->assigned_at->diffForHumans() }}</td>
                    <td>
                        @if($task->status === 'assigned')
                            <form method="POST" action="{{ route('pickrush.station.dispatch.unassign', $task) }}" class="pr-inline">
                                @csrf
                                <input name="reason" class="pr-input" placeholder="Reason" required aria-label="Reason" style="width:140px">
                                <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm">Take back</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="pr-muted">No open rider tasks.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@else
    <form method="POST" action="{{ route('pickrush.station.dispatch.assign') }}">
        @csrf
        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead><tr><th><input type="checkbox" aria-label="Select all" onclick="document.querySelectorAll('[data-pick]').forEach(b => b.checked = this.checked)"></th>
                    <th>Tracking no.</th><th>{{ $view === 'pickups' ? 'Item' : 'Deliver to' }}</th><th class="pr-num">COD</th><th>Zone</th><th>Suggested rider</th></tr></thead>
                <tbody>
                @if($view === 'pickups')
                    @forelse($stops as $i => $stop)
                        @php $first = $stop->first()->parcel; @endphp
                        <tr class="pr-stop-row">
                            <td><input type="checkbox" aria-label="Select all from {{ $first->shipper_name }}" onclick="document.querySelectorAll('[data-stop=&quot;{{ $i }}&quot;]').forEach(b => b.checked = this.checked)"></td>
                            <td colspan="5">
                                <strong>{{ $first->shipper_name }}</strong> · {{ $stop->count() }} {{ Str::plural('parcel', $stop->count()) }}
                                @if($first->shipper_mobile)<span class="pr-muted"> · {{ $first->shipper_mobile }}</span>@endif
                                <div class="pr-muted">{{ $first->pickup_address ?? 'No pickup address' }}</div>
                            </td>
                        </tr>
                        @foreach($stop as $row)
                            @php
                                $p = $row->parcel;
                                $last = $p->latestAssignment;
                                $declined = $last?->fail_reason === 'rider_declined' ? $last : null;
                                $missed = $last?->type === 'pickup' && $last->status === 'failed' ? $last : null;
                            @endphp
                            <tr>
                                <td><input type="checkbox" name="parcel_ids[]" value="{{ $p->id }}" data-pick data-stop="{{ $i }}" aria-label="Select {{ $p->tracking_no }}"></td>
                                <td>
                                    <a href="{{ route('pickrush.parcels.show', $p) }}">{{ $p->tracking_no }}</a>
                                    @if($declined)<span class="pr-badge pr-badge-warn" title="{{ $declined->fail_note }}">Declined by {{ $declined->rider?->full_name }}</span>@endif
                                    @if($missed)<span class="pr-badge pr-badge-bad" title="{{ $missed->fail_note }}">Not collected: {{ config('pickrush.pickup_failure_reasons.'.$missed->fail_reason, $missed->fail_reason) }} ({{ $missed->rider?->full_name }})</span>@endif
                                </td>
                                <td>{{ $p->item_description ?? '—' }}</td>
                                <td class="pr-num">—</td>
                                <td>—</td>
                                <td><span class="pr-muted">Pick a rider</span></td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="6" class="pr-muted">{{ $seller !== '' ? 'No pickups waiting from "'.$seller.'".' : 'Nothing waiting here.' }}</td></tr>
                    @endforelse
                @else
                @forelse($rows as $row)
                    @php $p = $row->parcel; $s = $row->suggestion; @endphp
                    <tr>
                        <td><input type="checkbox" name="parcel_ids[]" value="{{ $p->id }}" data-pick aria-label="Select {{ $p->tracking_no }}"></td>
                        <td>
                            <a href="{{ route('pickrush.parcels.show', $p) }}">{{ $p->tracking_no }}</a>
                            @if($row->task === 'return')<span class="pr-badge pr-badge-warn">Return to seller</span>@endif
                            @if($p->attempt_count)<span class="pr-badge pr-badge-bad">Attempt {{ $p->attempt_count + 1 }}</span>@endif
                        </td>
                        <td>
                            @if($row->task === 'pickup' || $row->task === 'return')
                                {{ $p->shipper_name }}<div class="pr-muted">{{ $p->pickup_address }}</div>
                            @else
                                {{ $p->consignee_name }}<div class="pr-muted">{{ $p->delivery_address }}</div>
                            @endif
                        </td>
                        <td class="pr-num">{{ $row->task === 'delivery' ? '₱'.number_format((float) $p->cod_amount, 2) : '—' }}</td>
                        <td>{{ $s['zone']?->name ?? ($row->task === 'pickup' ? '—' : 'No zone') }}</td>
                        <td>
                            @if($row->task === 'pickup')<span class="pr-muted">Pick a rider</span>
                            @elseif($s['rider'])<strong>{{ $s['rider']->full_name }}</strong><div class="pr-muted">{{ $s['note'] }}</div>
                            @else<span class="pr-badge pr-badge-warn">None</span><div class="pr-muted">{{ $s['note'] }}</div>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="pr-muted">Nothing waiting here.</td></tr>
                @endforelse
                @endif
                </tbody>
            </table>
        </div>

        @if($rows->isNotEmpty())
            <div class="pr-sticky-bar">
                <strong>{{ $view === 'pickups' ? 'Send ticked pickups as one batch to' : 'Assign ticked parcels to' }}</strong>
                <select name="rider_id" class="pr-select" aria-label="Rider">
                    @unless($view === 'pickups')<option value="">Each parcel's suggested rider</option>@endunless
                    @foreach($riders as $r)
                        <option value="{{ $r->rider->id }}" @disabled($r->blocker)>
                            {{ $r->rider->full_name }} · {{ $r->open }}/{{ $r->rider->max_parcels }} parcels · ₱{{ number_format($r->cod, 0) }}/{{ number_format((float) $r->rider->cod_limit, 0) }} COD{{ $r->blocker ? ' · unavailable' : '' }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="pr-btn pr-btn-primary">Assign</button>
            </div>
        @endif
    </form>
@endif

<section class="pr-section" style="margin-top:1.25rem">
    <h2>Riders</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Rider</th><th>Zone</th><th class="pr-num">Load</th><th class="pr-num">COD carried</th><th>Availability</th></tr></thead>
            <tbody>
            @forelse($riders as $r)
                <tr>
                    <td><a href="{{ route('pickrush.station.riders.show', $r->rider) }}">{{ $r->rider->full_name }}</a></td>
                    <td>{{ $r->rider->zone?->name ?? '—' }}</td>
                    <td class="pr-num">{{ $r->open }} / {{ $r->rider->max_parcels }}</td>
                    <td class="pr-num">₱{{ number_format($r->cod, 2) }} / ₱{{ number_format((float) $r->rider->cod_limit, 2) }}</td>
                    <td>@if($r->blocker)<span class="pr-badge pr-badge-warn">Unavailable</span> <span class="pr-muted">{{ $r->blocker }}</span>@else<span class="pr-badge pr-badge-ok">Available</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="pr-muted">No active riders. <a href="{{ route('pickrush.station.riders.index') }}">Manage riders</a></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
