@extends('pickrush.layouts.app')
@section('title', 'Failed deliveries')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Failed deliveries</h1>
        <p>Retry through <a href="{{ route('pickrush.station.dispatch') }}">Dispatch</a>, or send the parcel back to the seller. After {{ $maxAttempts }} attempts, return is the only option.</p>
    </div>
</div>

<div class="pr-table-wrap">
    <table class="pr-table">
        <thead><tr><th>Tracking no.</th><th>Buyer</th><th>Last reason</th><th class="pr-num">Attempts</th><th>Return to seller</th></tr></thead>
        <tbody>
        @forelse($parcels as $parcel)
            <tr>
                <td><a href="{{ route('pickrush.parcels.show', $parcel) }}">{{ $parcel->tracking_no }}</a></td>
                <td>{{ $parcel->consignee_name }}<div class="pr-muted">{{ $parcel->consignee_mobile }}</div></td>
                <td>{{ $parcel->events->last()?->note ?? '—' }}<div class="pr-muted">{{ $parcel->events->last()?->occurred_at->diffForHumans() }}</div></td>
                <td class="pr-num">
                    {{ $parcel->attempt_count }} / {{ $maxAttempts }}
                    @if($parcel->attempt_count >= $maxAttempts)<div><span class="pr-badge pr-badge-bad">Limit reached</span></div>@endif
                </td>
                <td>
                    <form method="POST" action="{{ route('pickrush.station.failed.return', $parcel) }}" class="pr-inline">
                        @csrf
                        <input name="reason" class="pr-input" placeholder="Why" required aria-label="Reason" style="width:160px">
                        <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm">Return</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="pr-muted">No failed deliveries at this station.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($returning->isNotEmpty())
    <section class="pr-section" style="margin-top:1.25rem">
        <h2>Going back to sellers ({{ $returning->count() }})</h2>
        <p class="pr-muted">These leave on the next <a href="{{ route('pickrush.station.trips.index') }}">trip</a>, or with a rider when the seller is in this city.</p>
        <ul>@foreach($returning as $parcel)<li><a href="{{ route('pickrush.parcels.show', $parcel) }}">{{ $parcel->tracking_no }}</a> · {{ $parcel->shipper_name }}</li>@endforeach</ul>
    </section>
@endif
@endsection
