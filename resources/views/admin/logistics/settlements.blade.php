@extends('admin.layout')
@section('title', 'Logistics Settlements')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-list.css'])
@endsection

@section('content')
@php
    $peso = fn ($n) => '₱'.number_format((float) $n, 2);
    $canManage = auth()->user()->can(\App\Auth\Permission::LOGISTICS_MANAGE);
@endphp
<p class="review-back"><a href="{{ route('admin.logistics.index') }}" class="btn btn-outline btn-sm">← Back to logistics</a></p>
<p class="oversight-description">Logistics companies pay PickSell for delivered parcels: the COD less the rider's pay and the company's share of the delivery fee, plus claims PickSell charged. Check each deposit slip against PickSell's bank statement before you confirm. A company that does not settle by its deadline is not offered at checkout until it does.</p>

<section class="card" aria-labelledby="pending-heading">
    <div class="card-header"><span class="card-title" id="pending-heading">Settlements to confirm ({{ $pending->count() }})</span></div>
    <div class="oversight-table-wrap">
        <table>
            <thead><tr><th>Company</th><th>Sent</th><th>Parcels</th><th>Amount</th><th>Reference</th>@if($canManage)<th>Decision</th>@endif</tr></thead>
            <tbody>
            @forelse($pending as $s)
                <tr>
                    <td><strong>{{ $s->company->name }}</strong><span class="oversight-meta">{{ $s->submitter?->full_name }}</span></td>
                    <td>{{ $s->created_at->format('M d, g:i A') }}</td>
                    <td class="oversight-number">{{ $s->settles_id ? '—' : number_format($s->parcel_count) }}</td>
                    <td class="oversight-number"><strong>{{ $peso($s->amount) }}</strong>@if((float) $s->claims_amount > 0)<span class="oversight-meta">incl. {{ $peso($s->claims_amount) }} claims</span>@endif</td>
                    <td>{{ $s->reference_no }} · <a href="{{ route('admin.logistics.settlements.slip', $s) }}" target="_blank" rel="noopener">View slip</a>
                        @if($s->settles)<div class="oversight-meta">Top-up for disputed ref {{ $s->settles->reference_no }}</div>@endif
                        @if($s->contested_at)<div class="oversight-meta">Contested {{ $s->contested_at->format('M d, g:i A') }}: “{{ $s->company_response }}”@if($s->contest_path) · <a href="{{ route('admin.logistics.settlements.evidence', $s) }}" target="_blank" rel="noopener">evidence</a>@endif</div>@endif
                        @if($s->note)<div class="oversight-meta">{{ $s->note }}</div>@endif</td>
                    @if($canManage)
                        <td>
                            <form method="POST" action="{{ route('admin.logistics.settlements.review', $s) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="decision" value="confirm">
                                <button type="submit" class="btn btn-primary btn-sm">Confirm {{ $peso($s->amount) }} received</button>
                            </form>
                            <details>
                                <summary class="oversight-meta">Does not match?</summary>
                                <form method="POST" action="{{ route('admin.logistics.settlements.review', $s) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="decision" value="dispute">
                                    <input name="bank_amount" type="number" step="0.01" min="0" class="form-input" placeholder="₱ on bank statement" aria-label="Amount on the bank statement" required>
                                    <input name="note" class="form-input" placeholder="What does not match" aria-label="What does not match" required>
                                    <button type="submit" class="btn btn-outline btn-sm">Dispute</button>
                                </form>
                            </details>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="6" class="oversight-meta">Nothing waiting for confirmation.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

@if($disputes->isNotEmpty())
<section class="card" aria-labelledby="disputes-heading">
    <div class="card-header"><span class="card-title" id="disputes-heading">Disputed with companies ({{ $disputes->count() }})</span></div>
    <div class="oversight-table-wrap">
        <table>
            <thead><tr><th>Company</th><th>Reference</th><th>Disputed</th><th>Sent</th><th>Bank showed</th><th>Short</th><th>Waiting for</th></tr></thead>
            <tbody>
            @foreach($disputes as $d)
                <tr>
                    <td>{{ $d->company->name }}</td>
                    <td>{{ $d->reference_no }}@if($d->note)<span class="oversight-meta">{{ $d->note }}</span>@endif</td>
                    <td>{{ $d->confirmed_at?->format('M d, g:i A') }}</td>
                    <td class="oversight-number">{{ $peso($d->amount) }}</td>
                    <td class="oversight-number">{{ $d->bank_amount !== null ? $peso($d->bank_amount) : '—' }}</td>
                    <td class="oversight-number">{{ $d->shortfall() > 0 ? $peso($d->shortfall()) : '—' }}</td>
                    <td>{{ $d->topUps->contains('status', 'submitted') ? 'PickSell: top-up to confirm above' : 'The company to top up or contest' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif

<section class="card" aria-labelledby="balance-heading">
    <div class="card-header"><span class="card-title" id="balance-heading">Owed by each company, not yet settled</span></div>
    <div class="oversight-table-wrap">
        <table>
            <thead><tr><th>Company</th><th>Parcels</th><th>Claims</th><th>Owed</th><th>Due</th><th>Checkout</th></tr></thead>
            <tbody>
            @foreach($companies as $row)
                @php $dl = $row['deadline']; @endphp
                <tr>
                    <td><strong>{{ $row['company']->name }}</strong></td>
                    <td class="oversight-number">{{ number_format($row['owed']['parcels']->count()) }}</td>
                    <td class="oversight-number">{{ $row['owed']['claims_total'] > 0 ? $peso($row['owed']['claims_total']) : '—' }}</td>
                    <td class="oversight-number"><strong>{{ $peso($dl['amount']) }}</strong></td>
                    <td>@if($dl['due_at']){{ $dl['due_at']->format('M d, g:i A') }}@if($dl['state'] !== 'ok') <span class="oversight-meta">{{ $dl['state'] === 'blocked' ? 'overdue, held back' : 'overdue' }}</span>@endif @else — @endif</td>
                    <td>{{ $dl['state'] === 'blocked' && ! $row['company']->is_default ? 'Not offered until it settles' : 'Offered' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>

@if($recent->isNotEmpty())
<section class="card" aria-labelledby="recent-heading">
    <div class="card-header"><span class="card-title" id="recent-heading">Recently confirmed, disputed or settled</span></div>
    <div class="oversight-table-wrap">
        <table>
            <thead><tr><th>Checked</th><th>Company</th><th>Amount</th><th>Reference</th><th>Status</th><th>By</th></tr></thead>
            <tbody>
            @foreach($recent as $s)
                <tr><td>{{ $s->confirmed_at?->format('M d, g:i A') }}</td><td>{{ $s->company->name }}</td><td class="oversight-number">{{ $peso($s->amount) }}@if($s->status === 'disputed' && $s->bank_amount !== null)<span class="oversight-meta">bank {{ $peso($s->bank_amount) }}</span>@endif</td>
                    <td>{{ $s->reference_no }} · <a href="{{ route('admin.logistics.settlements.slip', $s) }}" target="_blank" rel="noopener">slip</a></td><td>{{ ucfirst($s->status) }}</td><td>{{ $s->confirmer?->full_name }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif
@endsection
