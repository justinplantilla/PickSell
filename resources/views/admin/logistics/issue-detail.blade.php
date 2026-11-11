@extends('admin.layout')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-application-detail.css', 'resources/css/views/admin-logistics-issues.css'])
@endsection
@section('title', 'Logistics issue')

@section('content')
@php $peso = fn ($n) => '₱'.number_format((float) $n, 2); @endphp
<p class="review-back"><a href="{{ route('admin.logistics.issues') }}" class="btn btn-outline btn-sm">← Back to logistics issues</a></p>

@if($errors->has('issue'))<div class="alert alert-danger" role="alert">{{ $errors->first('issue') }}</div>@endif

<div class="review-layout">
    <section class="card" aria-labelledby="issue-heading">
        <div class="card-header">
            <span class="card-title" id="issue-heading">#{{ $issue->id }} {{ $issue->label() }}</span>
            <span class="badge badge-{{ ['open' => 'pending', 'escalated' => 'suspended', 'resolved' => 'approved'][$issue->status] }}">{{ \App\Models\LogisticsIssue::STATUSES[$issue->status] }}</span>
        </div>
        @php
            $fields = array_filter([
                'Logistics company' => $issue->company?->name,
                'Source' => ['exception' => 'Raised by the company’s hub', 'parcel' => 'From the parcel status', 'report' => 'Reported by the '.$issue->reporter_role][$issue->source] ?? $issue->source,
                'Reported by' => $issue->reporter ? $issue->reporter->full_name.' ('.$issue->reporter->email.')' : null,
                'Order' => $issue->order?->order_number,
                'Product' => $issue->order?->product_name,
                'Buyer' => $issue->order?->buyer?->full_name,
                'Seller' => $issue->order?->seller ? ($issue->order->seller->business_name ?: $issue->order->seller->full_name) : null,
                'Tracking #' => $issue->parcel?->tracking_no,
                'Parcel status' => $issue->parcel?->current_status?->label(),
                'Opened' => $issue->created_at->format('M d, Y h:i A').' ('.$issue->created_at->diffForHumans().')',
                'Resolved' => $issue->resolved_at ? $issue->resolved_at->format('M d, Y h:i A').($issue->resolver ? ' by '.$issue->resolver->full_name : '') : null,
                'Fault' => $issue->fault ? \App\Models\LogisticsIssue::FAULTS[$issue->fault] : null,
            ]);
        @endphp
        <dl class="review-fields">
            @foreach($fields as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach
        </dl>
        @if($issue->description)<div class="card-body"><p class="issue-description">{{ $issue->description }}</p></div>@endif
        <div class="card-body issue-links">
            @if($issue->order)<a href="{{ route('admin.orders.show', $issue->order) }}" class="btn btn-outline btn-sm">Open order</a>@endif
            @if($issue->evidence_path)<a href="{{ route('admin.logistics.issues.evidence', $issue) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">View photo</a>@endif
            @if($issue->company)<a href="{{ route('admin.logistics.companies.show', $issue->company) }}" class="btn btn-outline btn-sm">Company profile</a>@endif
        </div>
    </section>

    @if($issue->company_response)
    <section class="card issue-response" aria-labelledby="response-heading">
        <div class="card-header"><span class="card-title" id="response-heading">{{ $issue->company?->name }} replied</span><span class="oversight-meta">{{ $issue->responded_at?->diffForHumans() }}</span></div>
        <div class="card-body"><p>{{ $issue->company_response }}</p></div>
    </section>
    @endif

    @if($issue->claim)
    <section class="card" aria-labelledby="claim-heading">
        <div class="card-header"><span class="card-title" id="claim-heading">Claim against {{ $issue->company?->name }}</span>
            <span class="badge badge-{{ $issue->claim->status === 'charged' ? 'disapproved' : 'pending' }}">{{ ucfirst($issue->claim->status) }}</span></div>
        <dl class="review-fields">
            <div><dt>Item value</dt><dd>{{ $peso($issue->claim->item_value) }}</dd></div>
            <div><dt>Delivery fee</dt><dd>{{ $peso($issue->claim->shipping_fee) }}</dd></div>
            <div><dt>Charged</dt><dd><strong>{{ $peso($issue->claim->amount) }}</strong>{{ $issue->claim->capped ? ' (capped at '.$peso($cap).')' : '' }}</dd></div>
            @if($issue->claim->waive_reason)<div><dt>Waived because</dt><dd>{{ $issue->claim->waive_reason }}</dd></div>@endif
        </dl>
        @if($canManage && $issue->claim->status === 'charged')
            <div class="card-body">
                <form method="POST" action="{{ route('admin.logistics.claims.waive', $issue->claim) }}" class="issue-inline-form" data-confirm="Waive this claim? The company will no longer be charged.">
                    @csrf @method('PATCH')
                    <input name="reason" class="form-control" placeholder="Why waive it?" required minlength="5" maxlength="1000" aria-label="Reason to waive">
                    <button type="submit" class="btn btn-outline btn-sm">Waive claim</button>
                </form>
            </div>
        @endif
    </section>
    @endif

    @if($canManage && $issue->status !== 'resolved')
    <section class="card" aria-labelledby="actions-heading">
        <div class="card-header"><span class="card-title" id="actions-heading">Decide</span></div>
        <div class="card-body issue-actions">
            <form method="POST" action="{{ route('admin.logistics.issues.resolve', $issue) }}" class="issue-form">
                @csrf @method('PATCH')
                <h3>Resolve</h3>
                <div class="form-group">
                    <label class="form-label" for="fault">Who was at fault? *</label>
                    <select id="fault" name="fault" class="form-control" required>
                        <option value="">Choose…</option>
                        @foreach(\App\Models\LogisticsIssue::FAULTS as $key => $label)<option value="{{ $key }}" @selected(old('fault') === $key)>{{ $label }}</option>@endforeach
                    </select>
                    @error('fault')<div class="account-danger-text">{{ $message }}</div>@enderror
                </div>
                @if($claimValue)
                    <div class="form-group">
                        <label class="form-label" for="claim_amount">Claim if the company is at fault</label>
                        <input id="claim_amount" type="number" name="claim_amount" step="0.01" min="0" max="{{ $cap }}" class="form-control"
                            value="{{ old('claim_amount', min($cap, $claimValue['item_value'] + $claimValue['shipping_fee'])) }}">
                        <p class="oversight-meta">Item value {{ $peso($claimValue['item_value']) }} + delivery fee {{ $peso($claimValue['shipping_fee']) }}, at most {{ $peso($cap) }}. Deducted from the company’s share.</p>
                        @error('claim_amount')<div class="account-danger-text">{{ $message }}</div>@enderror
                    </div>
                @endif
                <div class="form-group">
                    <label class="form-label" for="notes">What was found *</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3" required minlength="5" maxlength="2000">{{ old('notes') }}</textarea>
                    @error('notes')<div class="account-danger-text">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-coral btn-sm">Resolve issue</button>
            </form>

            @if($issue->status === 'open')
            <form method="POST" action="{{ route('admin.logistics.issues.escalate', $issue) }}" class="issue-form">
                @csrf @method('PATCH')
                <h3>Ask {{ $issue->company?->name }}</h3>
                <div class="form-group">
                    <label class="form-label" for="message">What do you need from them? *</label>
                    <textarea id="message" name="message" class="form-control" rows="3" required minlength="10" maxlength="2000" placeholder="e.g. Send the rider's proof of delivery and GPS for this drop.">{{ old('message') }}</textarea>
                    @error('message')<div class="account-danger-text">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-outline btn-sm">Send to company</button>
            </form>
            @else
                <p class="oversight-meta">Waiting for {{ $issue->company?->name }} to reply from their portal (sent {{ $issue->escalated_at?->diffForHumans() }}).</p>
            @endif
        </div>
    </section>
    @endif

    <section class="card" aria-labelledby="history-heading">
        <div class="card-header"><span class="card-title" id="history-heading">History</span></div>
        <ol class="issue-history">
            @foreach($issue->events as $event)
                <li>
                    <strong>{{ \App\Models\LogisticsIssueEvent::ACTIONS[$event->action] ?? $event->action }}</strong>
                    <span class="oversight-meta">{{ $event->actorName() }} · <time datetime="{{ $event->created_at?->toIso8601String() }}">{{ $event->created_at?->format('M d, Y h:i A') }}</time></span>
                    @if($event->note)<p>{{ $event->note }}</p>@endif
                </li>
            @endforeach
        </ol>
        @if($canManage)
            <div class="card-body">
                <form method="POST" action="{{ route('admin.logistics.issues.note', $issue) }}" class="issue-inline-form">
                    @csrf
                    <input name="note" class="form-control" placeholder="Add an internal note…" required maxlength="2000" aria-label="Note">
                    <button type="submit" class="btn btn-outline btn-sm">Add note</button>
                </form>
            </div>
        @endif
    </section>
</div>
@endsection
