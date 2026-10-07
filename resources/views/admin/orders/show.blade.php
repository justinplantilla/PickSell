@extends('admin.layout')
@section('title', 'Order ' . $order->order_number)
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-orders.css'])
@endsection

@use('App\Services\Orders\OrderLifecycleService', 'Lifecycle')
@php
    $timestamps = Lifecycle::TIMESTAMPS;
    $reachedAt = fn ($status) => $status === 'placed' ? $order->created_at : (isset($timestamps[$status]) ? $order->{$timestamps[$status]} : null);
    // Position on the main path; for exception/legacy statuses, the furthest step actually reached.
    $currentIndex = array_search($order->status, Lifecycle::MAIN_PATH, true);
    if ($currentIndex === false) {
        $reached = array_keys(array_filter(Lifecycle::MAIN_PATH, fn ($status) => $reachedAt($status) !== null));
        $currentIndex = $reached ? max($reached) + 1 : false;
    }
@endphp

@section('content')
<p class="order-back"><a href="{{ route('admin.orders') }}" class="btn btn-outline btn-sm">← Back to orders</a></p>

<div class="order-layout">
    {{-- Order summary: buyer, seller, items, amount, commission --}}
    <section class="card" aria-labelledby="summary-heading">
        <div class="card-header">
            <span class="card-title" id="summary-heading">Order {{ $order->order_number }}</span>
            <span><x-admin-order-status :status="$order->status" />@if($isStuck)<span class="oversight-flag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 8v4l2.5 2.5M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z"/></svg> No update for {{ $order->updated_at->diffForHumans(null, true) }}</span>@endif</span>
        </div>
        <div class="order-summary">
            <dl class="order-fields">
                <div><dt>Buyer</dt><dd>{{ $order->buyer?->full_name ?? '—' }} @can('viewAccount', $order->buyer)<a href="{{ route('admin.users.show', $order->buyer) }}" class="oversight-meta">account</a>@endcan
                    <span class="oversight-meta">{{ $order->buyer?->email }} · {{ $order->buyer?->contact_no }}</span></dd></div>
                <div><dt>Deliver to</dt><dd>{{ collect([$order->destinationBarangay?->name ?? $order->buyer?->barangay, $order->buyer?->municipality, $order->buyer?->province])->filter(fn ($v) => is_string($v) && $v !== '')->join(', ') ?: '—' }}</dd></div>
                <div><dt>Seller</dt><dd>{{ $order->seller?->business_name ?? $order->seller?->full_name ?? '—' }} @can('viewAccount', $order->seller)<a href="{{ route('admin.users.show', $order->seller) }}" class="oversight-meta">account</a>@endcan</dd></div>
                <div><dt>Item</dt><dd>
                    @if($order->product && auth()->user()->can('view', $order->product))
                        <a href="{{ route('admin.products.show', $order->product) }}">{{ $order->product_name }}</a>
                    @else
                        {{ $order->product_name }}
                    @endif
                    × {{ $order->quantity }}<span class="oversight-meta">₱{{ number_format($order->quantity ? $money['amount'] / $order->quantity : 0, 2) }} each</span></dd></div>
                <div><dt>Placed</dt><dd>{{ $order->created_at->format('M d, Y h:i A') }}</dd></div>
            </dl>
            <dl class="order-money" aria-label="Amounts">
                <div><dt>Order amount</dt><dd>₱{{ number_format($money['amount'], 2) }}</dd></div>
                <div><dt>Platform commission ({{ rtrim(rtrim(number_format($money['commission_rate'], 2), '0'), '.') }}%)</dt><dd>₱{{ number_format($money['commission'], 2) }}</dd></div>
                <div><dt>Net to seller</dt><dd>₱{{ number_format($money['net_to_seller'], 2) }}</dd></div>
                <p class="oversight-meta">Commission and rate are recorded when the order is placed.</p>
                <p class="oversight-meta">{{ $money['counts_toward_sales'] ? 'Counts toward completed sales.' : 'Counts toward sales once the order is completed.' }}</p>
            </dl>
        </div>
    </section>

    {{-- Fulfillment timeline --}}
    <section class="card" aria-labelledby="timeline-heading">
        <div class="card-header"><span class="card-title" id="timeline-heading">Fulfillment timeline</span></div>
        <ol class="order-steps">
            @foreach(Lifecycle::MAIN_PATH as $index => $status)
                @php $at = $reachedAt($status); $state = $at ? 'done' : ($currentIndex !== false && $index < $currentIndex ? 'skipped' : 'todo'); @endphp
                <li class="order-step is-{{ $state }} {{ $order->status === $status ? 'is-current' : '' }}" @if($order->status === $status) aria-current="step" @endif>
                    <span class="order-step-dot" aria-hidden="true"></span>
                    <span class="order-step-label">{{ Lifecycle::label($status) }}</span>
                    <span class="oversight-meta">{{ $at ? $at->format('M d, h:i A') : ($state === 'skipped' ? 'Skipped' : '—') }}</span>
                </li>
            @endforeach
        </ol>
        @if(in_array($order->status, Lifecycle::LEGACY, true))
            <p class="oversight-description oversight-note"><strong>Legacy status:</strong> “{{ $order->status }}” predates the current lifecycle. Use Override status to move it to the step that matches where the parcel is.</p>
        @endif
        @if(in_array($order->status, ['delivery_failed', 'returned', 'cancelled'], true))
            <p class="oversight-description oversight-note"><strong>Exception:</strong> this order is {{ Lifecycle::label($order->status) }}.</p>
        @endif

        <h3 class="order-subheading">Status history</h3>
        @if($order->statusHistories->isEmpty())
            <p class="oversight-description order-pad">No recorded status changes (this order predates status history).</p>
        @else
            <ol class="order-history">
                @foreach($order->statusHistories as $change)
                    <li class="{{ $change->source === 'admin' ? 'is-admin' : '' }}">
                        <span><strong>{{ $change->from_status ? Lifecycle::label($change->from_status) . ' → ' : '' }}{{ Lifecycle::label($change->to_status) }}</strong>
                            <span class="order-source">{{ ucfirst($change->source) }}</span></span>
                        <span class="oversight-meta">{{ $change->changer?->full_name ?? 'System' }} · <time datetime="{{ $change->created_at->toIso8601String() }}">{{ $change->created_at->format('M d, Y h:i A') }}</time></span>
                        @if($change->reason)<p class="order-reason">{{ $change->reason }}</p>@endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    {{-- Sorting events + courier assignment --}}
    <div class="order-grid">
        <section class="card" aria-labelledby="sorting-heading">
            <div class="card-header"><span class="card-title" id="sorting-heading">Sorting events</span></div>
            <dl class="order-fields order-pad">
                <div><dt>Waybill</dt><dd>{{ $order->waybill_number ?? 'Not generated yet' }}</dd></div>
                <div><dt>Route</dt><dd>{{ $order->originBranch?->name ?? 'Origin not set' }} → {{ $order->destinationBranch?->name ?? 'Destination not set' }}</dd></div>
                <div><dt>Picked up</dt><dd>{{ $order->picked_up_at?->format('M d, Y h:i A') ?? '—' }}</dd></div>
                <div><dt>Received at hub</dt><dd>{{ $order->sorting_received_at?->format('M d, Y h:i A') ?? '—' }}</dd></div>
                <div><dt>Sorted</dt><dd>{{ $order->sorted_at?->format('M d, Y h:i A') ?? '—' }}</dd></div>
                <div><dt>Tracking note</dt><dd>{{ $order->tracking_status ?? '—' }}</dd></div>
            </dl>
        </section>
        <section class="card" aria-labelledby="courier-heading">
            <div class="card-header"><span class="card-title" id="courier-heading">Courier assignment</span></div>
            <dl class="order-fields order-pad">
                <div><dt>Rider</dt><dd>{{ $order->courier?->full_name ?? 'Unassigned' }}@if($order->courier)<span class="oversight-meta">{{ $order->courier->contact_no }}</span>@endif</dd></div>
                <div><dt>Assigned</dt><dd>{{ $order->assigned_to_rider_at?->format('M d, Y h:i A') ?? '—' }}</dd></div>
                <div><dt>Out for delivery</dt><dd>{{ $order->out_for_delivery_at?->format('M d, Y h:i A') ?? '—' }}</dd></div>
                <div><dt>Delivered</dt><dd>{{ $order->delivered_at?->format('M d, Y h:i A') ?? '—' }}</dd></div>
            </dl>
        </section>
    </div>

    {{-- Admin actions --}}
    @if($overrideTargets || $exceptionActions)
        <section class="card" aria-labelledby="actions-heading">
            <div class="card-header"><span class="card-title" id="actions-heading">Admin actions</span><span class="oversight-description">Every action needs a reason, notifies buyer and seller, and is audited.</span></div>
            <div class="order-actions">
                @if($exceptionActions)
                    <form method="POST" action="{{ route('admin.orders.resolve', $order) }}" class="order-action-form">
                        @csrf @method('PATCH')
                        <h3>Resolve exception</h3>
                        <fieldset class="order-choices">
                            <legend class="sr-only">Resolution</legend>
                            @foreach($exceptionActions as $key => $action)
                                <label><input type="radio" name="action" value="{{ $key }}" required @checked(old('action') === $key)> <strong>{{ $action['label'] }}</strong><span class="oversight-meta">{{ $action['help'] }}</span></label>
                            @endforeach
                        </fieldset>
                        <label class="form-label" for="resolve-reason">Reason</label>
                        <textarea id="resolve-reason" name="reason" class="form-control" rows="2" required minlength="10" maxlength="1000">{{ old('action') ? old('reason') : '' }}</textarea>
                        @if(old('action')) @error('reason')<span class="order-error">{{ $message }}</span>@enderror @endif
                        <button type="submit" class="btn btn-coral btn-sm">Apply resolution</button>
                    </form>
                @endif

                @if($overrideTargets)
                    <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="order-action-form is-override">
                        @csrf @method('PATCH')
                        <h3>Override status</h3>
                        <p class="oversight-meta">Only valid next steps from {{ Lifecycle::label($order->status) }} are offered. Use when a portal action could not be completed.</p>
                        <label class="form-label" for="override-status">Move to</label>
                        <select id="override-status" name="status" class="form-control" required>
                            @foreach($overrideTargets as $target)
                                <option value="{{ $target }}" @selected(old('status') === $target)>{{ Lifecycle::label($target) }}</option>
                            @endforeach
                        </select>
                        <label class="form-label" for="override-reason">Reason</label>
                        <textarea id="override-reason" name="reason" class="form-control" rows="2" required minlength="10" maxlength="1000">{{ old('status') ? old('reason') : '' }}</textarea>
                        @if(old('status')) @error('reason')<span class="order-error">{{ $message }}</span>@enderror @endif
                        <label class="order-confirm"><input type="checkbox" name="confirm" value="1" required> I confirm this override.</label>
                        @if(old('status')) @error('confirm')<span class="order-error">{{ $message }}</span>@enderror @endif
                        <button type="submit" class="btn btn-danger btn-sm">Override status</button>
                    </form>
                @endif
            </div>
        </section>
    @elseif(in_array($order->status, Lifecycle::TERMINAL, true))
        <p class="oversight-description">This order is {{ Lifecycle::label($order->status) }}; no further status changes are possible.</p>
    @endif

    {{-- Complaints / returns / refunds --}}
    <div class="order-grid">
        <section class="card" aria-labelledby="complaints-heading">
            <div class="card-header"><span class="card-title" id="complaints-heading">Complaints</span></div>
            @if($complaints->isEmpty())
                <p class="oversight-description order-pad">No complaints between this buyer and seller since the order was placed.</p>
            @else
                <ul class="order-list">
                    @foreach($complaints as $complaint)
                        <li><span>@can(\App\Auth\Permission::COMPLAINTS_VIEW)<a href="{{ route('admin.complaints.show', $complaint) }}">#{{ $complaint->id }} {{ $complaint->subject }}</a>@else#{{ $complaint->id }} {{ $complaint->subject }}@endcan
                            <span class="oversight-meta">{{ $complaint->created_at->format('M d, Y') }}</span></span>
                            <span class="badge {{ in_array($complaint->status, ['resolved', 'dismissed'], true) ? 'badge-approved' : 'badge-pending' }}">{{ ucfirst(str_replace('_', ' ', $complaint->status)) }}</span></li>
                    @endforeach
                </ul>
            @endif
        </section>
        <section class="card" aria-labelledby="returns-heading">
            <div class="card-header"><span class="card-title" id="returns-heading">Returns &amp; refunds</span></div>
            @if(! $order->returnRequest)
                <p class="oversight-description order-pad">No return requested.</p>
            @else
                @php $return = $order->returnRequest; @endphp
                <dl class="order-fields order-pad">
                    <div><dt>Return</dt><dd>@can('view', $return)<a href="{{ route('admin.returns.show', $return) }}">Request #{{ $return->id }}</a>@else Request #{{ $return->id }}@endcan
                        <x-return-request-status-badge :status="$return->status" /></dd></div>
                    <div><dt>Reason</dt><dd>{{ ucfirst(str_replace('_', ' ', $return->reason)) }}</dd></div>
                    @if($return->dispute_status !== 'not_open')<div><dt>Dispute</dt><dd>{{ ucfirst($return->dispute_status) }}</dd></div>@endif
                    <div><dt>Refund</dt><dd>
                        @if(in_array($return->status, ['approved_for_refund', 'refund_due', 'completed'], true))
                            {{ $return->refund_amount !== null ? '₱' . number_format((float) $return->refund_amount, 2) : 'Amount not set' }}
                            · {{ $return->status === 'completed' ? 'refunded ' . $return->completed_at?->format('M d, Y') : ($return->status === 'approved_for_refund' ? 'approved for refund ' : 'due since ') . $return->refund_due_at?->format('M d, Y') }}
                        @else
                            Not yet due
                        @endif
                    </dd></div>
                </dl>
            @endif
        </section>
    </div>

    {{-- Audit history --}}
    <section class="card" aria-labelledby="audit-heading">
        <div class="card-header">
            <span class="card-title" id="audit-heading">Audit history</span>
            @can(\App\Auth\Permission::AUDIT_VIEW)<a href="{{ route('admin.audit') }}" class="oversight-description">All audit logs</a>@endcan
        </div>
        @if($auditHistory->isEmpty())
            <p class="oversight-description order-pad">No admin actions on this order.</p>
        @else
            <ul class="order-list">
                @foreach($auditHistory as $entry)
                    <li><span><span class="audit-action">{{ $entry->action }}</span>
                        @foreach($entry->changes ?? [] as $field => $change)<span class="oversight-meta">{{ $field }}: {{ $change['from'] ?? '—' }} → {{ $change['to'] ?? '—' }}</span>@endforeach
                        @if(!empty($entry->metadata['reason']))<span class="oversight-meta">Reason: {{ $entry->metadata['reason'] }}</span>@endif</span>
                        <span class="oversight-meta">{{ $entry->actor?->full_name ?? 'System' }} · {{ $entry->created_at?->format('M d, Y h:i A') }}</span></li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
