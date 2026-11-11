@extends('pickrush.layouts.app')
@section('title', 'PickSell issues')

@section('content')
@php $peso = fn ($n) => '₱'.number_format((float) $n, 2); @endphp
<div class="pr-page-head">
    <div>
        <h1>PickSell issues</h1>
        <p>Problems PickSell is following up on your PickSell parcels. Reply to the ones waiting on you; PickSell decides who was at fault. Parcels you lose or damage are charged as claims against your share.</p>
    </div>
</div>

@if($errors->has('operation'))<div class="pr-alert pr-alert-bad">{{ $errors->first('operation') }}</div>@endif

<section class="pr-section">
    <h2>Waiting on your reply ({{ $waiting->count() }})</h2>
    @forelse($waiting as $issue)
        <article class="pr-card pr-section">
            <h3>#{{ $issue->id }} {{ $issue->label() }} <span class="pr-muted">· {{ $issue->parcel?->tracking_no ?? $issue->order?->order_number }}</span></h3>
            @if($issue->description)<p>{{ $issue->description }}</p>@endif
            @foreach($issue->events as $ask)
                <p><strong>PickSell asks:</strong> {{ $ask->note }} <span class="pr-muted">({{ $ask->created_at?->diffForHumans() }})</span></p>
            @endforeach
            @if($canRespond)
                <form method="POST" action="{{ route('pickrush.hq.issues.respond', $issue->id) }}">
                    @csrf
                    <textarea name="response" class="pr-input" rows="3" required minlength="10" maxlength="2000" aria-label="Your reply" placeholder="What happened, and what you found or did…" style="width:100%"></textarea>
                    @error('response')<div class="pr-muted">{{ $message }}</div>@enderror
                    <button type="submit" class="pr-btn pr-btn-sm">Send reply</button>
                </form>
            @else
                <p class="pr-muted">Your super admin or support staff can reply.</p>
            @endif
        </article>
    @empty
        <p class="pr-muted">Nothing is waiting on you.</p>
    @endforelse
</section>

<section class="pr-section">
    <h2>Other issues</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Issue</th><th>Parcel</th><th>Opened</th><th>Status</th><th>Outcome</th></tr></thead>
            <tbody>
            @forelse($recent as $issue)
                <tr>
                    <td>#{{ $issue->id }} {{ $issue->label() }}</td>
                    <td>{{ $issue->parcel?->tracking_no ?? '—' }}</td>
                    <td>{{ $issue->created_at->format('M d, Y') }}</td>
                    <td>{{ $issue->status === 'resolved' ? 'Resolved' : 'With PickSell' }}</td>
                    <td>{{ $issue->fault ? \App\Models\LogisticsIssue::FAULTS[$issue->fault] : '—' }}@if($issue->resolution_notes)<div class="pr-muted">{{ \Illuminate\Support\Str::limit($issue->resolution_notes, 120) }}</div>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="pr-muted">No issues yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="pr-section">
    <h2>Claims charged ({{ $peso($claimsTotal) }})</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Issue</th><th>Parcel</th><th>Date</th><th class="pr-num">Amount</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($claims as $claim)
                <tr>
                    <td>#{{ $claim->logistics_issue_id }} {{ $claim->issue?->label() }}</td>
                    <td>{{ $claim->issue?->parcel?->tracking_no ?? '—' }}</td>
                    <td>{{ $claim->created_at->format('M d, Y') }}</td>
                    <td class="pr-num">{{ $peso($claim->amount) }}</td>
                    <td>{{ $claim->status === 'charged' ? 'Charged' : 'Waived' }}@if($claim->waive_reason)<div class="pr-muted">{{ $claim->waive_reason }}</div>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="pr-muted">No claims.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
