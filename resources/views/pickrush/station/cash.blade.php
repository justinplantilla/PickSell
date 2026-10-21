@extends('pickrush.layouts.app')
@section('title', 'Cash (COD)')

@section('content')
@php $isManager = \App\Pickrush\Support\Actor::staff()->role === \App\Pickrush\Enums\StaffRole::HubManager; @endphp
<div class="pr-page-head">
    <div>
        <h1>Cash (COD)</h1>
        <p>Every parcel is cash on delivery. At the end of the shift each rider hands over the cash; the system total is the expected amount.</p>
    </div>
</div>

@if($disputed->isNotEmpty())
    <section class="pr-section">
        <h2>Open variances: these riders cannot be dispatched</h2>
        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead><tr><th>Rider</th><th>Date</th><th class="pr-num">Expected</th><th class="pr-num">Received</th><th class="pr-num">Variance</th><th>Resolve</th></tr></thead>
                <tbody>
                @foreach($disputed as $r)
                    <tr>
                        <td>{{ $r->rider->full_name }}</td>
                        <td>{{ $r->remit_date->format('M d') }}</td>
                        <td class="pr-num">₱{{ number_format((float) $r->expected_amount, 2) }}</td>
                        <td class="pr-num">₱{{ number_format((float) $r->received_amount, 2) }}</td>
                        <td class="pr-num"><span class="pr-badge {{ (float) $r->variance < 0 ? 'pr-badge-bad' : 'pr-badge-warn' }}">{{ (float) $r->variance < 0 ? 'Short' : 'Over' }} ₱{{ number_format(abs((float) $r->variance), 2) }}</span></td>
                        <td>
                            @if($isManager)
                                <form method="POST" action="{{ route('pickrush.station.cash.resolve', $r) }}" class="pr-inline">
                                    @csrf
                                    <input name="resolution" class="pr-input" placeholder="What happened, what was agreed" required minlength="10" aria-label="Resolution" style="width:240px">
                                    <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm">Resolve</button>
                                </form>
                            @else
                                <span class="pr-muted">Hub manager resolves</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

<section class="pr-section">
    <h2>Riders holding cash</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Rider</th><th class="pr-num">Expected (system)</th>@if($isManager)<th>Counted cash</th>@endif</tr></thead>
            <tbody>
            @forelse($riders as $row)
                <tr>
                    <td>{{ $row->rider->full_name }}</td>
                    <td class="pr-num"><strong>₱{{ number_format($row->expected, 2) }}</strong></td>
                    @if($isManager)
                        <td>
                            <form method="POST" action="{{ route('pickrush.station.cash.record', $row->rider) }}" class="pr-inline">
                                @csrf
                                <input name="received_amount" type="number" step="0.01" min="0" class="pr-input" placeholder="Counted ₱" required aria-label="Counted cash" style="width:130px">
                                @if($row->expected > $twoPersonAbove)
                                    <select name="second_counter_id" class="pr-select" required aria-label="Second counter">
                                        <option value="">Second counter</option>
                                        @foreach($colleagues as $c)<option value="{{ $c->id }}">{{ $c->full_name }}</option>@endforeach
                                    </select>
                                @endif
                                <input name="note" class="pr-input" placeholder="Note" aria-label="Note" style="width:140px">
                                <button type="submit" class="pr-btn pr-btn-primary pr-btn-sm">Record</button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="3" class="pr-muted">No rider is holding cash.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($riders->contains(fn ($row) => $row->expected > $twoPersonAbove))<p class="pr-muted">Counts above ₱{{ number_format($twoPersonAbove) }} need a second staff member.</p>@endif
</section>

<div class="pr-two">
    <section class="pr-card">
        <h2>Remit to HQ</h2>
        <p>Cash at the station from counted, reconciled rider remittances: <strong>₱{{ number_format($cashOnHand, 2) }}</strong></p>
        @if($isManager && $cashOnHand > 0)
            <form method="POST" action="{{ route('pickrush.station.cash.remit') }}" class="pr-form-grid">
                @csrf
                <div class="pr-field"><label for="amount">Amount deposited (₱)</label><input id="amount" name="amount" type="number" step="0.01" min="0" class="pr-input" value="{{ $cashOnHand }}" required></div>
                <div class="pr-field"><label for="reference_no">Deposit reference</label><input id="reference_no" name="reference_no" class="pr-input" required></div>
                <div class="pr-field"><label for="remit_note">Note</label><input id="remit_note" name="note" class="pr-input"></div>
                <div class="pr-field" style="justify-content:flex-end"><button type="submit" class="pr-btn pr-btn-primary">Submit remittance</button></div>
            </form>
        @endif
        @if($hubRemittances->isNotEmpty())
            <table class="pr-table" style="margin-top:1rem">
                <thead><tr><th>Submitted</th><th class="pr-num">Amount</th><th>Reference</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($hubRemittances as $h)
                    <tr><td>{{ $h->created_at->format('M d') }}</td><td class="pr-num">₱{{ number_format((float) $h->remitted_amount, 2) }}</td><td>{{ $h->reference_no }}</td><td><span class="pr-badge">{{ ucfirst($h->status) }}</span></td></tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
    <section class="pr-card">
        <h2>Recent rider remittances</h2>
        <table class="pr-table">
            <thead><tr><th>Rider</th><th>Date</th><th class="pr-num">Received</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($recent as $r)
                <tr><td>{{ $r->rider->full_name }}</td><td>{{ $r->remit_date->format('M d') }}</td><td class="pr-num">₱{{ number_format((float) $r->received_amount, 2) }}</td>
                    <td><span class="pr-badge {{ $r->status === 'reconciled' ? 'pr-badge-ok' : 'pr-badge-bad' }}">{{ ucfirst($r->status) }}</span></td></tr>
            @empty
                <tr><td colspan="4" class="pr-muted">None yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</div>
@endsection
