@extends('pickrush.layouts.app')
@section('title', $parcel->tracking_no)

@section('content')
<div class="pr-page-head">
    <div>
        <h1>{{ $parcel->tracking_no }}</h1>
        <p>{{ $parcel->item_description }} · <a href="{{ route('pickrush.parcels.index') }}">All parcels</a></p>
    </div>
    <div>
        <span class="pr-badge pr-badge-brand">{{ $parcel->current_status?->label() }}</span>
        @if($parcel->is_return)<span class="pr-badge pr-badge-warn">Return to seller</span>@endif
    </div>
</div>

@if($parcel->needs_address_fix)
    <div class="pr-alert pr-alert-bad">The pickup or delivery address did not match a station. HQ needs to confirm it before this parcel can be routed.</div>
@endif

<div class="pr-two">
    <section class="pr-card">
        <h2>Shipment</h2>
        <dl class="pr-dl">
            <dt>From</dt><dd>{{ $parcel->shipper_name }} · {{ $parcel->shipper_mobile }}<div class="pr-muted">{{ $parcel->pickup_address }}</div></dd>
            <dt>To</dt><dd>{{ $parcel->consignee_name }} · {{ $parcel->consignee_mobile }}<div class="pr-muted">{{ $parcel->delivery_address }}</div></dd>
            <dt>COD</dt><dd><strong>₱{{ number_format((float) $parcel->cod_amount, 2) }}</strong></dd>
            <dt>Route</dt><dd>{{ $parcel->originHub?->name ?? '?' }} → {{ $parcel->destHub?->name ?? '?' }}</dd>
            <dt>Now at</dt><dd>{{ $parcel->currentHub?->name ?? '—' }}@if($parcel->nextHub) · next: {{ $parcel->nextHub->name }}@endif</dd>
            <dt>Attempts</dt><dd>{{ $parcel->attempt_count }} / {{ config('pickrush.policies.max_delivery_attempts') }}</dd>
            @if($parcel->source === 'picksell')<dt>Source</dt><dd>Picksell order #{{ $parcel->external_ref }}</dd>@endif
        </dl>
        @if($parcel->assignments->isNotEmpty())
            <h3 style="margin-top:1rem">Rider tasks</h3>
            <ul>
                @foreach($parcel->assignments as $task)
                    <li>{{ ucfirst($task->type) }} · {{ $task->rider->full_name }} · {{ str_replace('_', ' ', $task->status) }} · {{ $task->assigned_at->format('M d h:i A') }}
                        @if($task->fail_reason) · {{ config("pickrush.failure_reasons.{$task->fail_reason}") }}@endif</li>
                @endforeach
            </ul>
        @endif
    </section>
    <section class="pr-card">
        <h2>Scan history</h2>
        <ol class="pr-timeline">
            @foreach($parcel->events->reverse() as $event)
                <li @class(['is-current' => $loop->first])>
                    <strong>{{ $event->headline() }}</strong>
                    @if($event->note)<span>{{ $event->note }}</span>@endif
                    <time datetime="{{ $event->occurred_at->toIso8601String() }}">{{ $event->occurred_at->format('M d, Y h:i A') }} · {{ $event->actor_type }}{{ $event->actor_id ? ' #'.$event->actor_id : '' }}</time>
                </li>
            @endforeach
        </ol>
    </section>
</div>
@endsection
