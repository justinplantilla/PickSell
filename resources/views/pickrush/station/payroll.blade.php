@extends('pickrush.layouts.app')
@section('title', 'Rider payroll')

@section('content')
@php
    $peso = fn ($v) => ((float) $v < 0 ? '−' : '').'₱'.number_format(abs((float) $v), 2);
    $closedCount = $payslips->where('status', \App\Pickrush\Models\RiderPayslip::CLOSED)->count();
@endphp
<div class="pr-page-head">
    <div>
        <h1>Rider payroll</h1>
        <p>Every pickup, delivery and return goes to the rider's wallet. Each week (Monday to Sunday) closes into a payslip on Sunday night: earnings less deductions. Release it so the rider can withdraw; payslips not released by Tuesday release themselves.</p>
    </div>
</div>

@if($disputed->isNotEmpty())
    <section class="pr-section pr-variances">
        <h2>Disputed deductions: decide these</h2>
        <p class="pr-muted">A disputed deduction is left off every payslip until you decide. Upheld, it comes off the next payslip; waived, it is dropped.</p>
        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead><tr><th>Rider</th><th>Deduction</th><th class="pr-num">Amount</th><th>Rider says</th><th>Decide</th></tr></thead>
                <tbody>
                @foreach($disputed as $d)
                    <tr>
                        <td>{{ $d->rider->full_name }}</td>
                        <td>{{ $d->label() }}@if($d->parcel) · {{ $d->parcel->tracking_no }}@endif<br><span class="pr-muted">{{ $d->reason }}</span></td>
                        <td class="pr-num">{{ $peso($d->amount) }}</td>
                        <td>{{ $d->dispute_reason }}<br><span class="pr-muted">{{ $d->disputed_at->format('M j, g:i A') }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('pickrush.station.payroll.decide', $d) }}" class="pr-inline">
                                @csrf
                                <input name="note" class="pr-input" placeholder="Why" required minlength="5" aria-label="Decision note" style="width:200px">
                                <button type="submit" name="decision" value="upheld" class="pr-btn pr-btn-outline pr-btn-sm">Uphold</button>
                                <button type="submit" name="decision" value="waived" class="pr-btn pr-btn-outline pr-btn-sm">Waive</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

<section class="pr-card" style="margin-bottom:1rem">
    <div class="pr-page-head" style="margin-bottom:.5rem">
        <h2 style="margin:0">Payslips</h2>
        @if($weeks->isNotEmpty())
            <form method="GET" class="pr-inline">
                <label for="week" class="pr-muted">Week of</label>
                <select id="week" name="week" class="pr-select" onchange="this.form.submit()">
                    @foreach($weeks as $w)
                        <option value="{{ $w }}" @selected($w === $week)>{{ \Illuminate\Support\Carbon::parse($w)->format('M j') }} – {{ \Illuminate\Support\Carbon::parse($w)->addDays(6)->format('M j, Y') }}</option>
                    @endforeach
                </select>
                <noscript><button class="pr-btn pr-btn-outline pr-btn-sm" type="submit">Show</button></noscript>
            </form>
        @endif
    </div>
    @if($week && $closedCount)
        <form method="POST" action="{{ route('pickrush.station.payroll.release-week') }}" class="pr-inline" style="margin-bottom:.75rem">
            @csrf
            <input type="hidden" name="week" value="{{ $week }}">
            <button type="submit" class="pr-btn pr-btn-primary">Release all {{ $closedCount }} {{ \Illuminate\Support\Str::plural('payslip', $closedCount) }}</button>
            <span class="pr-muted">They release themselves on {{ \Illuminate\Support\Carbon::parse($week)->addDays(6 + \App\Pickrush\Services\Rider\RiderPayroll::AUTO_RELEASE_AFTER_DAYS)->format('D, M j') }}.</span>
        </form>
    @endif
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Rider</th><th class="pr-num">Pickups</th><th class="pr-num">Deliveries</th><th class="pr-num">Returns</th><th class="pr-num">Earned</th><th class="pr-num">Deductions</th><th class="pr-num">Net</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($payslips as $p)
                <tr>
                    <td><a href="{{ route('pickrush.station.payroll.show', $p) }}">{{ $p->rider->full_name }}</a></td>
                    <td class="pr-num">{{ $p->pickups }}</td>
                    <td class="pr-num">{{ $p->deliveries }}</td>
                    <td class="pr-num">{{ $p->returns }}</td>
                    <td class="pr-num">{{ $peso($p->earned) }}</td>
                    <td class="pr-num">@if((float) $p->deductions > 0)−{{ $peso($p->deductions) }}@else — @endif</td>
                    <td class="pr-num"><strong>{{ $peso($p->net) }}</strong></td>
                    <td>
                        @if($p->isReleased())
                            <span class="pr-badge pr-badge-ok">Released</span>
                            <span class="pr-muted">{{ $p->released_at->format('M j') }} · {{ $p->releaser?->full_name ?? 'automatically' }}</span>
                        @else
                            <span class="pr-badge pr-badge-warn">To release</span>
                        @endif
                    </td>
                    <td>
                        @unless($p->isReleased())
                            <form method="POST" action="{{ route('pickrush.station.payroll.release', $p) }}">
                                @csrf
                                <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm">Release</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="pr-muted">No payslips yet. The first week closes on Sunday night.</td></tr>
            @endforelse
            </tbody>
            @if($payslips->isNotEmpty())
                <tfoot><tr><th colspan="4" style="text-align:left">Total ({{ $payslips->count() }})</th><th class="pr-num">{{ $peso($payslips->sum('earned')) }}</th><th class="pr-num">−{{ $peso($payslips->sum('deductions')) }}</th><th class="pr-num">{{ $peso($payslips->sum('net')) }}</th><th colspan="2"></th></tr></tfoot>
            @endif
        </table>
    </div>
</section>

<div class="pr-two">
    <section class="pr-card">
        <h2>This week so far</h2>
        <p class="pr-muted">Week of {{ $thisWeek->format('M j') }}: closes on Sunday night. Wallet is what the rider can withdraw now.</p>
        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead><tr><th>Rider</th><th class="pr-num">Earned</th><th class="pr-num">Deductions</th><th class="pr-num">Wallet</th></tr></thead>
                <tbody>
                @forelse($pending as $row)
                    <tr>
                        <td>{{ $row->rider->full_name }}</td>
                        <td class="pr-num">{{ $peso($row->pending_earned) }}</td>
                        <td class="pr-num">@if($row->pending_deductions > 0)−{{ $peso($row->pending_deductions) }}@else — @endif</td>
                        <td class="pr-num">{{ $peso($row->available) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="pr-muted">No pay this week yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="pr-card">
        <h2>Deduct from a rider's pay</h2>
        <p class="pr-muted">Only when the rider is at fault. A parcel the station, truck or hub damaged or lost comes off the company's share instead. Cash shortages are deducted on the Cash page.</p>
        <form method="POST" action="{{ route('pickrush.station.payroll.deduct') }}" class="pr-form-grid">
            @csrf
            <div class="pr-field"><label for="rider_id">Rider</label>
                <select id="rider_id" name="rider_id" class="pr-select" required>
                    <option value="">Choose…</option>
                    @foreach($riders as $r)<option value="{{ $r->id }}" @selected(old('rider_id') == $r->id)>{{ $r->full_name }}</option>@endforeach
                </select></div>
            <div class="pr-field"><label for="kind">What happened</label>
                <select id="kind" name="kind" class="pr-select" required>
                    @foreach(\Illuminate\Support\Arr::except(\App\Pickrush\Models\RiderDeduction::KINDS, 'shortage') as $value => $label)
                        <option value="{{ $value }}" @selected(old('kind') === $value)>{{ $label }}</option>
                    @endforeach
                </select></div>
            <div class="pr-field"><label for="amount">Amount (₱)</label><input id="amount" name="amount" type="number" step="0.01" min="0.01" class="pr-input" value="{{ old('amount') }}" required></div>
            <div class="pr-field"><label for="tracking_no">Tracking no. (optional)</label><input id="tracking_no" name="tracking_no" class="pr-input" value="{{ old('tracking_no') }}"></div>
            <div class="pr-field" style="grid-column:1/-1"><label for="reason">Reason</label><input id="reason" name="reason" class="pr-input" value="{{ old('reason') }}" required minlength="5" maxlength="255"></div>
            <div class="pr-field" style="justify-content:flex-end"><button class="pr-btn pr-btn-danger" type="submit">Deduct</button></div>
        </form>
    </section>
</div>
@endsection
