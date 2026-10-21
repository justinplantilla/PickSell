@extends('pickrush.layouts.app')
@section('title', 'Dispatch')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Dispatch</h1>
        <p>Tick parcels, then assign them to the suggested rider or pick one. Nothing is assigned without you.</p>
    </div>
</div>

<nav class="pr-tabs" aria-label="Queue">
    <a href="{{ route('pickrush.station.dispatch') }}" @class(['is-active' => $view === 'deliveries'])>Deliveries &amp; returns ({{ $counts['deliveries'] }})</a>
    <a href="{{ route('pickrush.station.dispatch', ['view' => 'no_zone']) }}" @class(['is-active' => $view === 'no_zone'])>No zone ({{ $counts['no_zone'] }})</a>
    <a href="{{ route('pickrush.station.dispatch', ['view' => 'pickups']) }}" @class(['is-active' => $view === 'pickups'])>Seller pickups ({{ $counts['pickups'] }})</a>
    <a href="{{ route('pickrush.station.dispatch', ['view' => 'assigned']) }}" @class(['is-active' => $view === 'assigned'])>With riders</a>
</nav>

@if($view === 'assigned')
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Tracking no.</th><th>Rider</th><th>Task</th><th>Status</th><th>Since</th><th>Take back</th></tr></thead>
            <tbody>
            @forelse($assigned as $task)
                <tr>
                    <td><a href="{{ route('pickrush.parcels.show', $task->parcel) }}">{{ $task->parcel->tracking_no }}</a></td>
                    <td>{{ $task->rider->full_name }}</td>
                    <td>{{ ucfirst($task->type) }}@if($task->type === 'delivery') · attempt {{ $task->attempt_no }}@endif</td>
                    <td><span class="pr-badge {{ $task->status === 'out_for_delivery' ? 'pr-badge-brand' : '' }}">{{ $task->status === 'out_for_delivery' ? 'Out for delivery' : 'Assigned' }}</span></td>
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
                    <th>Tracking no.</th><th>{{ $view === 'pickups' ? 'Collect from' : 'Deliver to' }}</th><th class="pr-num">COD</th><th>Zone</th><th>Suggested rider</th></tr></thead>
                <tbody>
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
                </tbody>
            </table>
        </div>

        @if($rows->isNotEmpty())
            <div class="pr-sticky-bar">
                <strong>Assign ticked parcels to</strong>
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
