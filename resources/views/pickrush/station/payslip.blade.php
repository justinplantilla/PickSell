@extends('pickrush.layouts.app')
@section('title', 'Payslip')

@section('content')
@php
    $peso = fn ($v) => ((float) $v < 0 ? '−' : '').'₱'.number_format(abs((float) $v), 2);
    $kinds = ['pickup' => 'Pickup', 'delivery' => 'Delivery', 'return' => 'Return'];
@endphp
<div class="pr-page-head">
    <div>
        <p><a href="{{ route('pickrush.station.payroll', ['week' => $payslip->week_start->toDateString()]) }}">← Rider payroll</a></p>
        <h1>{{ $payslip->rider->full_name }}</h1>
        <p>Week of {{ $payslip->week_start->format('M j') }} – {{ $payslip->week_end->format('M j, Y') }}</p>
    </div>
    <div>
        @if($payslip->isReleased())
            <span class="pr-badge pr-badge-ok">Released {{ $payslip->released_at->format('M j') }} · {{ $payslip->releaser?->full_name ?? 'automatically' }}</span>
        @else
            <form method="POST" action="{{ route('pickrush.station.payroll.release', $payslip) }}">
                @csrf
                <button type="submit" class="pr-btn pr-btn-primary">Release {{ $peso($payslip->net) }}</button>
            </form>
        @endif
    </div>
</div>

<section class="pr-card" style="margin-bottom:1rem">
    <table class="pr-table">
        <tbody>
            <tr><td>{{ $payslip->pickups }} pickups · {{ $payslip->deliveries }} deliveries · {{ $payslip->returns }} returns</td><td class="pr-num">{{ $peso($payslip->earned) }}</td></tr>
            <tr><td>Deductions</td><td class="pr-num">@if((float) $payslip->deductions > 0)−{{ $peso($payslip->deductions) }}@else — @endif</td></tr>
        </tbody>
        <tfoot><tr><th style="text-align:left">Net pay</th><th class="pr-num">{{ $peso($payslip->net) }}</th></tr></tfoot>
    </table>
</section>

<div class="pr-two">
    <section class="pr-card">
        <h2>Earnings</h2>
        <div class="pr-table-scroll">
            <table class="pr-table">
                <thead><tr><th>Date</th><th>Task</th><th>Parcel</th><th class="pr-num">Pay</th></tr></thead>
                <tbody>
                @forelse($earnings as $e)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($e->earned_at)->format('M j, g:i A') }}</td>
                        <td>{{ $kinds[$e->kind] ?? ucfirst($e->kind) }}</td>
                        <td>{{ $e->tracking_no ?? '—' }}@if($e->parcel_class) <span class="pr-muted">{{ $e->parcel_class }}</span>@endif</td>
                        <td class="pr-num">{{ $peso($e->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="pr-muted">No earnings this week.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="pr-card">
        <h2>Deductions</h2>
        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead><tr><th>Date</th><th>What</th><th class="pr-num">Amount</th></tr></thead>
                <tbody>
                @forelse($deductions as $d)
                    <tr>
                        <td>{{ $d->occurred_at->format('M j') }}</td>
                        <td>{{ $d->label() }}@if($d->parcel) · {{ $d->parcel->tracking_no }}@endif<br><span class="pr-muted">{{ $d->reason }}</span>@if($d->decision === 'upheld')<br><span class="pr-muted">Dispute not accepted: {{ $d->decision_note }}</span>@endif</td>
                        <td class="pr-num">−{{ $peso($d->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="pr-muted">No deductions.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
