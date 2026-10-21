@extends('pickrush.layouts.app')
@section('title', 'Reports')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Reports</h1>
        <p>Station performance and why deliveries fail. Failure reasons come from a closed list, so stations compare fairly.</p>
    </div>
    <a href="{{ route('pickrush.hq.reports.export', ['period' => $period]) }}" class="pr-btn pr-btn-outline">Export to Excel (CSV)</a>
</div>

@include('pickrush.hq.partials.period-tabs')

<div class="pr-two pr-section">
    <section class="pr-card">
        <h2>Outcomes</h2>
        <dl class="pr-dl">
            <dt>Delivered</dt><dd>{{ number_format($summary['delivered']) }}</dd>
            <dt>Returned</dt><dd>{{ number_format($summary['returned']) }}</dd>
            <dt>Success rate</dt><dd>{{ $summary['success_rate'] ?? '—' }}%</dd>
            <dt>First-attempt rate</dt><dd>{{ $summary['first_attempt_rate'] ?? '—' }}%</dd>
            <dt>Average delivery time</dt><dd>{{ $summary['avg_delivery_hours'] ?? '—' }} h</dd>
        </dl>
    </section>
    <section class="pr-card">
        <h2>Failure reasons</h2>
        @php $totalFails = max(1, $failures->sum()); @endphp
        @forelse($failures as $reason => $count)
            <div style="display:grid;grid-template-columns:1fr auto;gap:.2rem .75rem;margin-bottom:.5rem">
                <span>{{ config("pickrush.failure_reasons.{$reason}", $reason) }}</span><strong>{{ $count }}</strong>
                <div class="pr-bar" style="grid-column:1/-1"><span style="width: {{ round($count / $totalFails * 100) }}%; background: var(--pr-brand)"></span></div>
            </div>
        @empty
            <p class="pr-muted">No failed attempts in this period.</p>
        @endforelse
    </section>
</div>

<section class="pr-section">
    <h2>Stations</h2>
    @include('pickrush.hq.partials.ranking')
</section>
@endsection
