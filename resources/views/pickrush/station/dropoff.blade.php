@extends('pickrush.layouts.app')
@section('title', 'Drop-off '.$batch->batch_no)

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Drop-off {{ $batch->batch_no }}</h1>
        <p>{{ $batch->seller?->business_name ?: $batch->seller?->full_name }} · {{ $received }} of {{ $batch->parcel_count }} received
            @if($batch->status === 'received') · confirmed {{ $batch->received_at->format('M d h:i A') }}@if($batch->receiver) by {{ $batch->receiver->full_name }}@endif @endif</p>
    </div>
    @if($batch->status === 'open')
    <form method="POST" action="{{ route('pickrush.station.dropoffs.confirm', $batch) }}">
        @csrf
        <button type="submit" class="pr-btn pr-btn-primary" data-confirm="Confirm {{ $received }} of {{ $batch->parcel_count }} parcels received? The seller gets the receipt.">Confirm receipt</button>
    </form>
    @endif
</div>

@include('pickrush.partials.scan-box', ['action' => route('pickrush.station.dropoffs.scan', $batch), 'button' => 'Receive'])

<section class="pr-section">
    <h2>Parcels on this drop-off</h2>
    <p class="pr-muted">Weigh a few parcels at random; report any that are heavier than declared.</p>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Tracking no.</th><th>Buyer</th><th>Status</th></tr></thead>
            <tbody>
            @foreach($batch->parcels as $parcel)
                <tr>
                    <td>@include('pickrush.partials.tracking-no', ['parcel' => $parcel])</td>
                    <td>{{ $parcel->consignee_name }}</td>
                    <td>
                        @if(\App\Pickrush\Models\DropoffBatch::arrived($parcel))<span class="pr-badge pr-badge-ok">Received</span>
                        @elseif($parcel->current_status === \App\Pickrush\Enums\ParcelStatus::Cancelled)<span class="pr-badge pr-badge-bad">Cancelled</span>
                        @else<span class="pr-badge pr-badge-warn">{{ $batch->status === 'received' ? 'Missing' : 'Waiting' }}</span>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
