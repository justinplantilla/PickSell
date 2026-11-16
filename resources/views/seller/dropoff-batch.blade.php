@extends('seller.layout')
@section('styles')
@vite('resources/css/views/seller-order-detail.css')
@endsection
@section('title', 'Drop-off ' . $batch->batch_no)

@section('content')
@php($received = $batch->parcels->filter(fn ($p) => \App\Pickrush\Models\DropoffBatch::arrived($p))->count())
<div class="order-detail-spaced">
    <a href="/seller/orders" class="btn btn-outline btn-sm">← Back to Orders</a>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Drop-off {{ $batch->batch_no }}</span>
        <a href="{{ route('seller.dropoffs.print', $batch) }}" target="_blank" class="btn btn-coral btn-sm">Print Manifest</a>
    </div>
    <div class="card-body">
        @if($batch->status === 'received')
        <div class="alert alert-success order-detail-flush" role="status">
            <strong>Receipt:</strong> {{ $batch->originHub?->name }} received {{ $received }} of {{ $batch->parcel_count }} parcels
            on {{ $batch->received_at->format('M d, Y h:i A') }}@if($batch->receiver), checked by {{ $batch->receiver->full_name }}@endif.
            @if($received < $batch->parcel_count) Parcels marked "Not dropped off" must reach the station before their deadline or those orders are cancelled.@endif
        </div>
        @else
        <div class="alert alert-info order-detail-flush" role="status">
            Bring these {{ $batch->parcel_count }} parcels and this printed manifest to <strong>{{ $batch->originHub?->name }}</strong>@if($batch->originHub?->address), {{ $batch->originHub->address }}@endif.
            Drop-off hours: <strong>{{ \App\Models\Order::DROPOFF_HOURS }}</strong> daily. The station scans the manifest, then each parcel. No rider is sent.
            @if($batch->originHub?->lat && $batch->originHub?->lng)
            <a href="https://www.google.com/maps/search/?api=1&query={{ $batch->originHub->lat }},{{ $batch->originHub->lng }}" target="_blank" rel="noopener">Open in Maps</a>
            @endif
        </div>
        @endif

        <table>
            <tr><td class="order-detail-muted">Waybill</td><td class="order-detail-muted">Order</td><td class="order-detail-muted">Buyer</td><td class="order-detail-muted">Status</td></tr>
            @foreach($batch->parcels as $parcel)
            <tr>
                <td><strong>{{ $parcel->tracking_no }}</strong></td>
                <td>{{ $orderNumbers[$parcel->id] ?? '—' }}</td>
                <td>{{ $parcel->consignee_name }}</td>
                <td>
                    @if(\App\Pickrush\Models\DropoffBatch::arrived($parcel)) Received
                    @elseif($parcel->current_status === \App\Pickrush\Enums\ParcelStatus::Cancelled) Cancelled
                    @else {{ $batch->status === 'received' ? 'Not dropped off' : 'To drop off' }}
                    @endif
                </td>
            </tr>
            @endforeach
        </table>
    </div>
</div>
@endsection
