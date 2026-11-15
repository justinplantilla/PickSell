@extends('pickrush.layouts.app')
@section('title', 'Finance')

@section('content')
@php $peso = fn ($n) => '₱'.number_format((float) $n, 2); @endphp
<div class="pr-page-head">
    <div>
        <h1>Finance</h1>
        <p>Every parcel is cash on delivery. Cash moves rider → station → company. Check each deposit slip and reference against the bank statement before you confirm. If the bank shows a different amount, dispute it with that amount.</p>
    </div>
</div>

{{-- Paying PickSell ------------------------------------------------------------------------------ --}}
@if(in_array($owedDeadline['state'], ['overdue', 'blocked'], true))
    <div class="pr-alert {{ $owedDeadline['state'] === 'blocked' ? 'pr-alert-bad' : 'pr-alert-warn' }}" role="alert">
        <strong>{{ $peso($owedDeadline['amount']) }} was due at PickSell on {{ $owedDeadline['due_at']->format('M j, g:i A') }}.</strong>
        @if($owedDeadline['state'] === 'blocked')
            PickSell is not offering your company at checkout until you settle.
        @else
            Settle by {{ $owedDeadline['block_at']->format('M j, g:i A') }} or PickSell stops offering your company at checkout.
        @endif
        @if($owedDeadline['disputes'])Answer PickSell's {{ \Illuminate\Support\Str::plural('dispute', $owedDeadline['disputes']) }} below.@endif
    </div>
@endif

@foreach($settlementDisputes as $d)
    @php $short = $d->shortfall(); $topUp = $d->topUps->firstWhere('status', 'submitted'); @endphp
    <section class="pr-section pr-variances">
        <h2>PickSell disputed your settlement ref {{ $d->reference_no }}</h2>
        <dl class="pr-dl">
            <dt>You sent</dt><dd>{{ $peso($d->amount) }} · {{ $d->created_at->format('M j, g:i A') }}</dd>
            @if($d->bank_amount !== null)<dt>PickSell's bank shows</dt><dd>{{ $peso($d->bank_amount) }}</dd>@endif
            @if($short > 0)<dt>Short</dt><dd><span class="pr-badge pr-badge-bad">{{ $peso($short) }}</span></dd>@endif
            @if($d->note)<dt>PickSell says</dt><dd>{{ $d->note }}</dd>@endif
            @if($d->company_response)<dt>Your last answer</dt><dd>{{ $d->company_response }}</dd>@endif
        </dl>
        @if($topUp)
            <p class="pr-muted">Top-up of {{ $peso($topUp->amount) }} (ref {{ $topUp->reference_no }}) is waiting for PickSell to check it.</p>
        @elseif($canConfirm)
            <div class="pr-two">
                @if($short > 0)
                    <form method="POST" action="{{ route('pickrush.hq.finance.settlements.top-up', $d) }}" enctype="multipart/form-data" class="pr-card">
                        @csrf
                        <h3>Deposit the shortfall</h3>
                        <input type="hidden" name="shortfall" value="{{ number_format($short, 2, '.', '') }}">
                        <div class="pr-field"><label for="st_ref_{{ $d->id }}">Deposit reference</label><input id="st_ref_{{ $d->id }}" name="reference_no" class="pr-input" maxlength="60" required></div>
                        <div class="pr-field"><label for="st_slip_{{ $d->id }}">Deposit slip (photo or PDF, up to 5 MB)</label><input id="st_slip_{{ $d->id }}" name="slip" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" class="pr-input" required></div>
                        <button type="submit" class="pr-btn pr-btn-primary">Confirm {{ $peso($short) }} deposited</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('pickrush.hq.finance.settlements.contest', $d) }}" enctype="multipart/form-data" class="pr-card">
                    @csrf
                    <h3>PickSell is wrong: contest it</h3>
                    <div class="pr-field"><label for="st_contest_{{ $d->id }}">What PickSell should check</label><textarea id="st_contest_{{ $d->id }}" name="explanation" class="pr-input" rows="3" minlength="10" maxlength="1000" required></textarea></div>
                    <div class="pr-field"><label for="st_evidence_{{ $d->id }}">Evidence (optional, photo or PDF)</label><input id="st_evidence_{{ $d->id }}" name="evidence" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" class="pr-input"></div>
                    <button type="submit" class="pr-btn pr-btn-outline">Send back to PickSell</button>
                </form>
            </div>
        @endif
    </section>
@endforeach

<section class="pr-section">
    <h2>Owed to PickSell, not yet settled</h2>
    <p class="pr-muted">Delivered PickSell parcels whose cash HQ has confirmed from the stations: the COD less the rider's pay and your share of the delivery fee, plus claims PickSell charged. Settle them together whenever you are ready.@if($owedDeadline['state'] === 'ok') Due {{ $owedDeadline['due_at']->format('M j, g:i A') }}.@endif</p>
    @if($owed['parcels']->isEmpty() && $owed['claims']->isEmpty())
        <p class="pr-muted">Nothing owed right now.</p>
    @else
        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead><tr><th>Parcel</th><th>Cash at HQ</th><th class="pr-num">COD</th><th class="pr-num">Rider pay</th><th class="pr-num">Your share</th><th class="pr-num">Owed</th></tr></thead>
                <tbody>
                @foreach($owed['parcels'] as $p)
                    <tr><td>{{ $p->tracking_no }}</td><td>{{ \Illuminate\Support\Carbon::parse($p->cash_at_hq_at)->format('M d, g:i A') }}</td>
                        <td class="pr-num">{{ $peso($p->cod_amount) }}</td><td class="pr-num">−{{ $peso($p->rider_pay) }}</td><td class="pr-num">−{{ $peso($p->logistics_share) }}</td>
                        <td class="pr-num">{{ $peso(\App\Pickrush\Services\Hq\PicksellSettlement::owedFor($p)) }}</td></tr>
                @endforeach
                @foreach($owed['claims'] as $c)
                    <tr><td colspan="5">Claim #{{ $c->id }} charged by PickSell</td><td class="pr-num">{{ $peso($c->amount) }}</td></tr>
                @endforeach
                </tbody>
                <tfoot><tr><th colspan="5" style="text-align:left">Total to settle ({{ $owed['parcels']->count() }} {{ \Illuminate\Support\Str::plural('parcel', $owed['parcels']->count()) }}@if($owed['claims']->isNotEmpty()), {{ $owed['claims']->count() }} {{ \Illuminate\Support\Str::plural('claim', $owed['claims']->count()) }}@endif)</th><th class="pr-num">{{ $peso($owed['total']) }}</th></tr></tfoot>
            </table>
        </div>
        @if($canConfirm && $owed['total'] > 0)
            <details class="pr-section" @if($errors->any() && old('total') !== null) open @endif>
                <summary class="pr-btn pr-btn-outline">Settle {{ $peso($owed['total']) }} with PickSell</summary>
                <form method="POST" action="{{ route('pickrush.hq.finance.settlements.store') }}" enctype="multipart/form-data" class="pr-form-grid" style="margin-top:.75rem">
                    @csrf
                    <input type="hidden" name="total" value="{{ number_format($owed['total'], 2, '.', '') }}">
                    <div class="pr-field"><label for="settle_ref">Deposit reference</label><input id="settle_ref" name="reference_no" class="pr-input" maxlength="60" value="{{ old('reference_no') }}" required></div>
                    <div class="pr-field"><label for="settle_slip">Deposit slip (photo or PDF, up to 5 MB)</label><input id="settle_slip" name="slip" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" class="pr-input" required></div>
                    <div class="pr-field"><label for="settle_note">Note</label><input id="settle_note" name="note" class="pr-input" value="{{ old('note') }}"></div>
                    <div class="pr-field" style="justify-content:flex-end"><button type="submit" class="pr-btn pr-btn-primary">Confirm {{ $peso($owed['total']) }} deposited</button></div>
                </form>
                <p class="pr-muted">Deposit the full total to PickSell's account in one deposit. Each reference can be used once.</p>
            </details>
        @elseif($owed['total'] < 0)
            <p class="pr-muted">PickSell owes you {{ $peso(-$owed['total']) }} on these parcels (its discounts exceed what you collected). Contact PickSell.</p>
        @endif
    @endif
    @if($settlements->isNotEmpty())
        <div class="pr-table-scroll">
        <table class="pr-table" style="margin-top:1rem">
            <thead><tr><th>Sent</th><th class="pr-num">Amount</th><th>Reference</th><th>Status</th></tr></thead>
            <tbody>
            @foreach($settlements as $s)
                <tr><td>{{ $s->created_at->format('M d, g:i A') }}</td><td class="pr-num">{{ $peso($s->amount) }}</td>
                    <td>{{ $s->reference_no }}@if($s->settles_id) <span class="pr-badge pr-badge-warn">Top-up</span>@endif · <a href="{{ route('pickrush.hq.finance.settlements.slip', $s) }}" target="_blank" rel="noopener">Slip</a></td>
                    <td><span class="pr-badge {{ $s->isReceived() ? 'pr-badge-ok' : ($s->status === 'disputed' ? 'pr-badge-bad' : '') }}">{{ $s->status === 'submitted' ? 'Waiting for PickSell' : ucfirst($s->status) }}</span></td></tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif
</section>

<section class="pr-section">
    <h2>Station remittances to confirm ({{ $pending->count() }})</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Station</th><th>Submitted</th><th class="pr-num">Counted</th><th class="pr-num">Sent</th><th class="pr-num">Difference</th><th>Reference</th>@if($canConfirm)<th>Decision</th>@endif</tr></thead>
            <tbody>
            @forelse($pending as $r)
                <tr>
                    <td>{{ $r->station->name }}</td>
                    <td>{{ $r->created_at->format('M d, Y') }}</td>
                    <td class="pr-num">{{ $peso($r->expected_amount) }}</td>
                    <td class="pr-num">{{ $peso($r->remitted_amount) }}</td>
                    <td class="pr-num">@if((float) $r->variance != 0)<span class="pr-badge pr-badge-bad">{{ $peso($r->variance) }}</span>@else — @endif</td>
                    <td>{{ $r->reference_no }}
                        @if($r->settles)<div><span class="pr-badge pr-badge-warn">Top-up</span> for disputed ref {{ $r->settles->reference_no }}</div>@endif
                        @if($r->contested_at)<div><span class="pr-badge pr-badge-warn">Contested</span> {{ $r->contested_at->format('M d, g:i A') }}: “{{ $r->station_response }}”@if($r->bank_amount !== null) · you saw {{ $peso($r->bank_amount) }} before @endif
                            @if($r->contest_path) · <a href="{{ route('pickrush.hq.finance.evidence', $r) }}" target="_blank" rel="noopener">evidence</a>@endif</div>@endif
                        @if($r->slip_path)<div><a href="{{ route('pickrush.hq.finance.slip', $r) }}" target="_blank" rel="noopener">View deposit slip</a></div>@else<div class="pr-muted">No slip (before slips were required)</div>@endif
                        @if($r->note)<div class="pr-muted">{{ $r->note }}</div>@endif</td>
                    @if($canConfirm)
                        <td>
                            <form method="POST" action="{{ route('pickrush.hq.finance.confirm', $r) }}" class="pr-inline">
                                @csrf
                                <input type="hidden" name="decision" value="confirm">
                                <button type="submit" class="pr-btn pr-btn-primary pr-btn-sm">Confirm {{ $peso($r->remitted_amount) }} received</button>
                            </form>
                            <details class="pr-cash-differs">
                                <summary class="pr-muted">Does not match?</summary>
                                <form method="POST" action="{{ route('pickrush.hq.finance.confirm', $r) }}" class="pr-inline">
                                    @csrf
                                    <input type="hidden" name="decision" value="dispute">
                                    <input name="bank_amount" type="number" step="0.01" min="0" class="pr-input" placeholder="₱ on bank statement" aria-label="Amount on the bank statement" required style="width:150px">
                                    <input name="note" class="pr-input" placeholder="What does not match" aria-label="What does not match" required style="width:180px">
                                    <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm">Dispute</button>
                                </form>
                            </details>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="7" class="pr-muted">Nothing waiting for confirmation.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

@if($disputes->isNotEmpty())
    <section class="pr-section">
        <h2>Disputed with stations ({{ $disputes->count() }})</h2>
        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead><tr><th>Station</th><th>Reference</th><th>Disputed</th><th class="pr-num">Declared</th><th class="pr-num">Bank showed</th><th class="pr-num">Short</th><th>Waiting for</th></tr></thead>
                <tbody>
                @foreach($disputes as $d)
                    <tr>
                        <td>{{ $d->station->name }}</td>
                        <td>{{ $d->reference_no }}@if($d->note)<div class="pr-muted">{{ $d->note }}</div>@endif</td>
                        <td>{{ $d->confirmed_at?->format('M d, g:i A') }}</td>
                        <td class="pr-num">{{ $peso($d->remitted_amount) }}</td>
                        <td class="pr-num">{{ $d->bank_amount !== null ? $peso($d->bank_amount) : '—' }}</td>
                        <td class="pr-num">@if($d->shortfall() > 0)<span class="pr-badge pr-badge-bad">{{ $peso($d->shortfall()) }}</span>@else — @endif</td>
                        <td>{{ $d->topUps->contains('status', 'submitted') ? 'HQ: top-up to confirm above' : 'The station to top up or contest' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

<div class="pr-two pr-section">
    <section class="pr-card">
        <h2>Cash not yet at HQ</h2>
        <dl class="pr-dl">
            <dt>With riders</dt><dd>{{ $peso($cash['with_riders']) }}</dd>
            <dt>Counted at stations</dt><dd>{{ $peso($cash['at_stations']) }}</dd>
            <dt>Sent, awaiting confirmation</dt><dd>{{ $peso($cash['submitted_unconfirmed']) }}</dd>
            <dt>Disputed with stations (short)</dt><dd>{{ $peso($cash['disputed_with_stations']) }}</dd>
        </dl>
    </section>
    <section class="pr-card">
        <h2>Delivered COD and fees</h2>
        @include('pickrush.hq.partials.period-tabs')
        <div class="pr-table-scroll">
        <table class="pr-table">
            <thead><tr><th>Shipper</th><th class="pr-num">Parcels</th><th class="pr-num">COD collected</th><th class="pr-num">Logistics fees</th><th class="pr-num">Owed to shipper</th></tr></thead>
            <tbody>
            @foreach(['picksell' => 'Picksell marketplace', 'direct' => 'Direct shippers'] as $source => $label)
                <tr>
                    <td>{{ $label }}</td>
                    <td class="pr-num">{{ number_format($settlement[$source]['parcels']) }}</td>
                    <td class="pr-num">{{ $peso($settlement[$source]['cod']) }}</td>
                    <td class="pr-num">{{ $peso($settlement[$source]['fees']) }}</td>
                    <td class="pr-num"><strong>{{ $peso($settlement[$source]['owed']) }}</strong></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <p class="pr-muted">Buyers can ask for a return up to {{ config('pickrush.policies.return_window_days') }} days after delivery.</p>
    </section>
</div>

<section class="pr-section">
    <h2>Rider payroll</h2>
    <p class="pr-muted">Riders hand over all the COD; their pay goes to their wallet. Each week closes into payslips on Sunday night, the station manager releases them (or they release themselves on Tuesday), and riders withdraw. Withdrawals are simulated for now: no money is sent.</p>
    <div class="pr-two">
        <section class="pr-card">
            <h3>Pay earned in this period</h3>
            <table class="pr-table">
                <thead><tr><th>Task</th><th class="pr-num">Tasks</th><th class="pr-num">Pay</th></tr></thead>
                <tbody>
                @foreach(['pickup' => 'Pickups', 'delivery' => 'Deliveries', 'return' => 'Returns to sellers'] as $kind => $label)
                    <tr><td>{{ $label }}</td><td class="pr-num">{{ number_format($payroll['by_kind'][$kind]['tasks']) }}</td><td class="pr-num">{{ $peso($payroll['by_kind'][$kind]['amount']) }}</td></tr>
                @endforeach
                </tbody>
                <tfoot><tr><th style="text-align:left" colspan="2">Total</th><th class="pr-num">{{ $peso($payroll['earned']) }}</th></tr></tfoot>
            </table>
            <p class="pr-muted">Pickup pay comes out of the company's share of the delivery fee; the fee split reserves only the delivery rider's pay.</p>
        </section>
        <section class="pr-card">
            <h3>Owed to riders</h3>
            <dl class="pr-dl">
                <dt>Payroll payable (released, not withdrawn)</dt><dd><strong>{{ $peso($payroll['payable']) }}</strong></dd>
                <dt>Waiting for release (closed, not released)</dt><dd>{{ $peso($payroll['waiting']) }}</dd>
                <dt>Withdrawn in this period</dt><dd>{{ $peso($payroll['withdrawn']) }}</dd>
            </dl>
            <p class="pr-muted">Payable and waiting are as of now; the rest follows the period above.</p>
        </section>
    </div>
    <div class="pr-table-wrap" style="margin-top:1rem">
        <table class="pr-table">
            <thead><tr><th>Station</th><th class="pr-num">Payslips</th><th class="pr-num">Earned</th><th class="pr-num">Deductions</th><th class="pr-num">Released</th><th class="pr-num">Waiting</th></tr></thead>
            <tbody>
            @forelse($payroll['stations'] as $row)
                <tr>
                    <td>{{ $row['station'] }}</td><td class="pr-num">{{ number_format($row['payslips']) }}</td>
                    <td class="pr-num">{{ $peso($row['earned']) }}</td><td class="pr-num">@if($row['deductions'] > 0)−{{ $peso($row['deductions']) }}@else — @endif</td>
                    <td class="pr-num">{{ $peso($row['released']) }}</td><td class="pr-num">{{ $peso($row['waiting']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="pr-muted">No payslips for weeks starting in this period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="pr-section">
    <h2>Rider cash variances</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Date</th><th>Rider</th><th>Station</th><th class="pr-num">Expected</th><th class="pr-num">Received</th><th class="pr-num">Variance</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($variances as $v)
                <tr>
                    <td>{{ $v->remit_date->format('M d') }}</td><td>{{ $v->rider->full_name }}</td><td>{{ $v->rider->station?->name }}</td>
                    <td class="pr-num">{{ $peso($v->expected_amount) }}</td><td class="pr-num">{{ $peso($v->received_amount) }}</td>
                    <td class="pr-num"><span class="pr-badge {{ (float) $v->variance < 0 ? 'pr-badge-bad' : 'pr-badge-warn' }}">{{ $peso($v->variance) }}</span></td>
                    <td>{{ match ($v->status) { 'disputed' => 'Open', 'awaiting_rider' => 'Waiting for rider', 'rejected' => 'Rejected by rider', default => 'Resolved' } }}@if($v->resolution_note)<div class="pr-muted">{{ $v->resolution_note }}</div>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="pr-muted">No variances in this period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

@if($recent->isNotEmpty())
    <section class="pr-section">
        <h2>Recently confirmed, disputed or settled</h2>
        <ul>@foreach($recent as $r)<li>{{ $r->confirmed_at?->format('M d') }} · {{ $r->station->name }} · {{ $peso($r->remitted_amount) }}@if($r->bank_amount !== null) (bank {{ $peso($r->bank_amount) }})@endif · {{ ucfirst($r->status) }}@if($r->slip_path) · <a href="{{ route('pickrush.hq.finance.slip', $r) }}" target="_blank" rel="noopener">slip</a>@endif</li>@endforeach</ul>
    </section>
@endif
@endsection
