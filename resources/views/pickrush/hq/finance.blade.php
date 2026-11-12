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
        <p class="pr-muted">Seller payouts release {{ config('pickrush.policies.payout_release_days') }} days after delivery (the dispute window).</p>
    </section>
</div>

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
