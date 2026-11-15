@extends('pickrush.layouts.app')
@section('title', 'Cash (COD)')

@section('content')
@php $isManager = \App\Pickrush\Support\Actor::staff()->role === \App\Pickrush\Enums\StaffRole::HubManager; @endphp
<div class="pr-page-head">
    <div>
        <h1>Cash (COD)</h1>
        <p>Every parcel is cash on delivery. At the end of the shift each rider hands over all the COD they collected (their pay goes to their wallet and weekly payslip); confirm the system total. The rider then confirms your count in the rider app.</p>
    </div>
</div>

@if(in_array($deadline['state'], ['overdue', 'blocked'], true))
    <div class="pr-alert {{ $deadline['state'] === 'blocked' ? 'pr-alert-bad' : 'pr-alert-warn' }}" role="alert">
        <strong>₱{{ number_format($deadline['amount'], 2) }} was due at HQ on {{ $deadline['due_at']->format('M j, g:i A') }}.</strong>
        @if($deadline['state'] === 'blocked')
            Deliveries cannot be dispatched until it is remitted. Pickups and returns still go.
        @else
            Remit it by {{ $deadline['block_at']->format('M j, g:i A') }} or deliveries stop until you do.
        @endif
        @if($deadline['disputes'])Answer HQ's {{ \Illuminate\Support\Str::plural('dispute', $deadline['disputes']) }} below.@endif
        @if($deadline['open_variances'])Resolve the {{ $deadline['open_variances'] }} open {{ \Illuminate\Support\Str::plural('variance', $deadline['open_variances']) }} first so all of it can be remitted.@endif
    </div>
@elseif($deadline['state'] === 'ok')
    <p class="pr-muted">₱{{ number_format($deadline['amount'], 2) }} counted at the station. Remit to HQ by {{ $deadline['due_at']->format('M j, g:i A') }}.</p>
@endif

@foreach($disputes as $d)
    @php $short = $d->shortfall(); $topUp = $d->topUps->firstWhere('status', 'submitted'); @endphp
    <section class="pr-section pr-variances">
        <h2>HQ disputed your deposit ref {{ $d->reference_no }}</h2>
        <dl class="pr-dl">
            <dt>You declared</dt><dd>₱{{ number_format((float) $d->remitted_amount, 2) }} · {{ $d->created_at->format('M j, g:i A') }}</dd>
            @if($d->bank_amount !== null)<dt>Bank statement shows</dt><dd>₱{{ number_format((float) $d->bank_amount, 2) }}</dd>@endif
            @if($short > 0)<dt>Short</dt><dd><span class="pr-badge pr-badge-bad">₱{{ number_format($short, 2) }}</span></dd>@endif
            @if($d->note)<dt>HQ says</dt><dd>{{ $d->note }}</dd>@endif
            @if($d->station_response)<dt>Your last answer</dt><dd>{{ $d->station_response }}</dd>@endif
        </dl>
        @if($topUp)
            <p class="pr-muted">Top-up of ₱{{ number_format((float) $topUp->remitted_amount, 2) }} (ref {{ $topUp->reference_no }}) is waiting for HQ to check it.</p>
        @elseif($isManager)
            <div class="pr-two">
                @if($short > 0)
                    <form method="POST" action="{{ route('pickrush.station.cash.top-up', $d) }}" enctype="multipart/form-data" class="pr-card">
                        @csrf
                        <h3>Deposit the shortfall</h3>
                        <input type="hidden" name="shortfall" value="{{ number_format($short, 2, '.', '') }}">
                        <div class="pr-field"><label for="top_ref_{{ $d->id }}">Deposit reference</label><input id="top_ref_{{ $d->id }}" name="reference_no" class="pr-input" maxlength="60" required></div>
                        <div class="pr-field"><label for="top_slip_{{ $d->id }}">Deposit slip (photo or PDF, up to 5 MB)</label><input id="top_slip_{{ $d->id }}" name="slip" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" class="pr-input" required></div>
                        <button type="submit" class="pr-btn pr-btn-primary">Confirm ₱{{ number_format($short, 2) }} deposited</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('pickrush.station.cash.contest', $d) }}" enctype="multipart/form-data" class="pr-card">
                    @csrf
                    <h3>HQ is wrong: contest it</h3>
                    <div class="pr-field"><label for="contest_{{ $d->id }}">What HQ should check</label><textarea id="contest_{{ $d->id }}" name="explanation" class="pr-input" rows="3" minlength="10" maxlength="1000" required placeholder="e.g. The bank posted the deposit the next morning; see the statement."></textarea></div>
                    <div class="pr-field"><label for="evidence_{{ $d->id }}">Evidence (optional, photo or PDF)</label><input id="evidence_{{ $d->id }}" name="evidence" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" class="pr-input"></div>
                    <button type="submit" class="pr-btn pr-btn-outline">Send back to HQ</button>
                </form>
            </div>
        @else
            <p class="pr-muted">The hub manager answers this dispute.</p>
        @endif
        <p class="pr-muted">Until you answer, this counts as cash due at HQ: deliveries stop {{ config('pickrush.policies.remittance_grace_hours') }} hours after HQ disputed it.</p>
    </section>
@endforeach

@if($rejected->isNotEmpty())
    <section class="pr-section pr-variances">
        <h2>Counts riders rejected: count the cash again with them</h2>
        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead><tr><th>Rider</th><th>Counted by</th><th class="pr-num">Counted</th><th>Rider says</th><th>Rejected</th></tr></thead>
                <tbody>
                @foreach($rejected as $r)
                    <tr>
                        <td>{{ $r->rider->full_name }}</td>
                        <td>{{ $r->receiver?->full_name }}</td>
                        <td class="pr-num">₱{{ number_format((float) $r->received_amount, 2) }}</td>
                        <td>{{ $r->rider_note }}</td>
                        <td>{{ $r->rider_responded_at->format('M d, g:i A') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="pr-muted">The cash is back on "Riders holding cash" below. HQ sees every rejected count.</p>
    </section>
@endif

@if($awaiting->isNotEmpty())
    <section class="pr-section">
        <h2>Waiting for the rider to confirm</h2>
        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead><tr><th>Rider</th><th>Counted by</th><th class="pr-num">Expected</th><th class="pr-num">Counted</th><th>Counted at</th></tr></thead>
                <tbody>
                @foreach($awaiting as $r)
                    <tr>
                        <td>{{ $r->rider->full_name }}</td>
                        <td>{{ $r->receiver?->full_name }}</td>
                        <td class="pr-num">₱{{ number_format((float) $r->expected_amount, 2) }}</td>
                        <td class="pr-num">₱{{ number_format((float) $r->received_amount, 2) }}@if((float) $r->variance != 0) <span class="pr-badge {{ (float) $r->variance < 0 ? 'pr-badge-bad' : 'pr-badge-warn' }}">{{ (float) $r->variance < 0 ? 'Short' : 'Over' }} ₱{{ number_format(abs((float) $r->variance), 2) }}</span>@endif</td>
                        <td>{{ $r->created_at->format('M d, g:i A') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="pr-muted">The rider confirms or rejects the count in the rider app. Until then the cash is not available to remit and the rider cannot be dispatched.</p>
    </section>
@endif

@if($disputed->isNotEmpty())
    <section class="pr-section pr-variances">
        <h2><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg> Open variances: these riders cannot be dispatched</h2>
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
                                    @if((float) $r->variance < 0)
                                        <label class="pr-check"><input type="checkbox" name="charge_rider" value="1"> Deduct from rider's pay</label>
                                    @endif
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
            <thead><tr><th>Rider</th><th class="pr-num">COD collected</th><th class="pr-num">To remit</th>@if($isManager)<th>Confirm</th>@endif</tr></thead>
            <tbody>
            @forelse($riders as $row)
                <tr>
                    <td>{{ $row->rider->full_name }}</td>
                    <td class="pr-num">₱{{ number_format($row->cod, 2) }}</td>
                    <td class="pr-num"><strong>₱{{ number_format($row->expected, 2) }}</strong></td>
                    @if($isManager)
                        <td>
                            <form method="POST" action="{{ route('pickrush.station.cash.record', $row->rider) }}" class="pr-inline">
                                @csrf
                                <input type="hidden" name="expected_amount" value="{{ number_format($row->expected, 2, '.', '') }}">
                                @if($row->expected > $twoPersonAbove)
                                    <select name="second_counter_id" class="pr-select" required aria-label="Second counter">
                                        <option value="">Second counter</option>
                                        @foreach($colleagues as $c)<option value="{{ $c->id }}">{{ $c->full_name }}</option>@endforeach
                                    </select>
                                @endif
                                <button type="submit" name="outcome" value="matches" class="pr-btn pr-btn-primary pr-btn-sm">Confirm ₱{{ number_format($row->expected, 2) }} received</button>
                                <details class="pr-cash-differs">
                                    <summary class="pr-muted">Cash doesn't match?</summary>
                                    <input name="received_amount" type="number" step="0.01" min="0" class="pr-input" placeholder="Actual ₱ counted" aria-label="Actual cash counted" style="width:140px">
                                    <input name="note" class="pr-input" placeholder="What happened" aria-label="Note" style="width:160px">
                                    <button type="submit" name="outcome" value="differs" class="pr-btn pr-btn-outline pr-btn-sm">Record counted amount</button>
                                </details>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="4" class="pr-muted">No rider is holding cash.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($riders->contains(fn ($row) => $row->expected > $twoPersonAbove))<p class="pr-muted">Counts above ₱{{ number_format($twoPersonAbove) }} need a second staff member.</p>@endif
</section>

<div class="pr-two">
    <section class="pr-card">
        <h2>Collected, not yet remitted to HQ</h2>
        <p>Rider remittances the riders confirmed collect here through the day. Remit them to HQ together whenever you are ready.</p>
        @if($ready->isEmpty())
            <p class="pr-muted">Nothing collected yet.</p>
        @else
            <div class="pr-table-scroll">
            <table class="pr-table">
                <thead><tr><th>Rider</th><th>Counted</th><th class="pr-num">Amount</th></tr></thead>
                @foreach($ready->groupBy(fn ($r) => $r->created_at->toDateString()) as $day => $counts)
                    <tbody>
                    @foreach($counts as $r)
                        <tr><td>{{ $r->rider->full_name }}</td><td>{{ $r->created_at->format('M d, g:i A') }}</td><td class="pr-num">₱{{ number_format((float) $r->received_amount, 2) }}</td></tr>
                    @endforeach
                    @if($ready->groupBy(fn ($r) => $r->created_at->toDateString())->count() > 1)
                        <tr><td colspan="2" class="pr-muted">{{ \Illuminate\Support\Carbon::parse($day)->format('M d') }} subtotal ({{ $counts->count() }})</td><td class="pr-num">₱{{ number_format((float) $counts->sum('received_amount'), 2) }}</td></tr>
                    @endif
                    </tbody>
                @endforeach
                <tfoot><tr><th colspan="2" style="text-align:left">Total to remit ({{ $ready->count() }} {{ \Illuminate\Support\Str::plural('remittance', $ready->count()) }})</th><th class="pr-num">₱{{ number_format($cashOnHand, 2) }}</th></tr></tfoot>
            </table>
            </div>
        @endif
        @if($isManager && $cashOnHand > 0)
            <details class="pr-section" @if($errors->any() && old('reference_no') !== null) open @endif>
            <summary class="pr-btn pr-btn-outline">Remit ₱{{ number_format($cashOnHand, 2) }} to HQ</summary>
            <form method="POST" action="{{ route('pickrush.station.cash.remit') }}" enctype="multipart/form-data" class="pr-form-grid" style="margin-top:.75rem">
                @csrf
                <input type="hidden" name="expected_amount" value="{{ number_format($cashOnHand, 2, '.', '') }}">
                <div class="pr-field"><label for="reference_no">Deposit reference</label><input id="reference_no" name="reference_no" class="pr-input" value="{{ old('reference_no') }}" maxlength="60" required></div>
                <div class="pr-field"><label for="slip">Deposit slip (photo or PDF, up to 5 MB)</label><input id="slip" name="slip" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" class="pr-input" required></div>
                <div class="pr-field"><label for="remit_note">Note</label><input id="remit_note" name="note" class="pr-input" value="{{ old('note') }}"></div>
                <div class="pr-field" style="justify-content:flex-end"><button type="submit" class="pr-btn pr-btn-primary">Confirm ₱{{ number_format($cashOnHand, 2) }} deposited</button></div>
            </form>
            <p class="pr-muted">Deposit the full total in one deposit. Each deposit reference can be used once. HQ finance checks the slip and the reference against the bank statement.</p>
            </details>
        @endif
        @if($hubRemittances->isNotEmpty())
            <div class="pr-table-scroll">
            <table class="pr-table" style="margin-top:1rem">
                <thead><tr><th>Submitted</th><th class="pr-num">Amount</th><th>Reference</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($hubRemittances as $h)
                    <tr><td>{{ $h->created_at->format('M d') }}</td><td class="pr-num">₱{{ number_format((float) $h->remitted_amount, 2) }}</td>
                        <td>{{ $h->reference_no }}@if($h->slip_path) · <a href="{{ route('pickrush.station.cash.slip', $h) }}" target="_blank" rel="noopener">Slip</a>@endif</td>
                        <td><span class="pr-badge {{ in_array($h->status, ['confirmed', 'settled'], true) ? 'pr-badge-ok' : ($h->status === 'disputed' ? 'pr-badge-bad' : '') }}">{{ ucfirst($h->status) }}</span></td></tr>
                @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </section>
    <section class="pr-card">
        <h2>Recent rider remittances</h2>
        <div class="pr-table-scroll">
        <table class="pr-table">
            <thead><tr><th>Rider</th><th>Date</th><th class="pr-num">Pay kept</th><th class="pr-num">Received</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($recent as $r)
                <tr><td>{{ $r->rider->full_name }}</td><td>{{ $r->remit_date->format('M d') }}</td><td class="pr-num">₱{{ number_format((float) $r->rider_pay_kept, 2) }}</td><td class="pr-num">₱{{ number_format((float) $r->received_amount, 2) }}</td>
                    <td><span class="pr-badge {{ match ($r->status) { 'reconciled' => 'pr-badge-ok', 'awaiting_rider' => 'pr-badge-warn', default => 'pr-badge-bad' } }}">{{ match ($r->status) { 'awaiting_rider' => 'Waiting for rider', 'rejected' => 'Rejected by rider', default => ucfirst($r->status) } }}</span></td></tr>
            @empty
                <tr><td colspan="5" class="pr-muted">None yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </section>
</div>
@endsection
