@extends('pickrush.layouts.app')
@section('title', 'Finance')

@section('content')
@php $peso = fn ($n) => '₱'.number_format((float) $n, 2); @endphp
<div class="pr-page-head">
    <div>
        <h1>Finance</h1>
        <p>Every parcel is cash on delivery. Cash moves rider → station → company; confirm what stations send against what they counted.</p>
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
                    <td>{{ $r->reference_no }}@if($r->note)<div class="pr-muted">{{ $r->note }}</div>@endif</td>
                    @if($canConfirm)
                        <td>
                            <form method="POST" action="{{ route('pickrush.hq.finance.confirm', $r) }}" class="pr-inline">
                                @csrf
                                <select name="decision" class="pr-select" aria-label="Decision"><option value="confirm">Received: confirm</option><option value="dispute">Does not match: dispute</option></select>
                                <input name="note" class="pr-input" placeholder="Note" aria-label="Note" style="width:150px">
                                <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm">Apply</button>
                            </form>
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

<div class="pr-two pr-section">
    <section class="pr-card">
        <h2>Cash not yet at HQ</h2>
        <dl class="pr-dl">
            <dt>With riders</dt><dd>{{ $peso($cash['with_riders']) }}</dd>
            <dt>Counted at stations</dt><dd>{{ $peso($cash['at_stations']) }}</dd>
            <dt>Sent, awaiting confirmation</dt><dd>{{ $peso($cash['submitted_unconfirmed']) }}</dd>
        </dl>
    </section>
    <section class="pr-card">
        <h2>Delivered COD and fees</h2>
        @include('pickrush.hq.partials.period-tabs')
        <table class="pr-table">
            <thead><tr><th>Shipper</th><th class="pr-num">Parcels</th><th class="pr-num">COD collected</th><th class="pr-num">Pickrush fees</th><th class="pr-num">Owed to shipper</th></tr></thead>
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
                    <td>{{ $v->status === 'disputed' ? 'Open' : 'Resolved' }}@if($v->resolution_note)<div class="pr-muted">{{ $v->resolution_note }}</div>@endif</td>
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
        <h2>Recently confirmed or disputed</h2>
        <ul>@foreach($recent as $r)<li>{{ $r->confirmed_at?->format('M d') }} · {{ $r->station->name }} · {{ $peso($r->remitted_amount) }} · {{ ucfirst($r->status) }}</li>@endforeach</ul>
    </section>
@endif
@endsection
