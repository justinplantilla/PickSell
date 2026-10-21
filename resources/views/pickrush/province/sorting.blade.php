@extends('pickrush.layouts.app')
@section('title', 'Sorting')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Sorting</h1>
        <p>Scan a parcel; put it in the bin shown. Parcels straight off a vehicle are received in the same scan.</p>
    </div>
</div>

@if($result)
    <div @class(['pr-bin', 'is-exception' => $result['error']]) role="status" aria-live="assertive">
        <div>
            <div class="pr-bin-meta">{{ $result['tracking_no'] }}{{ ! empty($result['received']) ? ' · received' : '' }}</div>
            <div class="pr-bin-name">{{ $result['bin'] }}</div>
        </div>
        <div class="pr-bin-meta">{{ $result['error'] ? 'Exception bin: see Exceptions' : 'Next: '.($result['destination'] ?? '') }}</div>
    </div>
@endif

@include('pickrush.partials.scan-box', ['action' => route('pickrush.province.sorting.scan'), 'label' => 'Scan to sort', 'button' => 'Sort'])

<section class="pr-section">
    <h2>Bins</h2>
    <div class="pr-bin-grid">
        @forelse($bins as $bin)
            <div class="pr-bin-tile">
                <strong>{{ $bin->bin }}</strong>
                <span class="pr-muted">{{ $bin->hub?->name }}</span>
                <div>{{ $bin->sorted }} sorted of {{ $bin->total }}</div>
            </div>
        @empty
            <p class="pr-muted">Nothing on the floor to sort.</p>
        @endforelse
        @if($exceptionBin)
            <a class="pr-bin-tile is-exception" href="{{ route('pickrush.exceptions.index') }}" style="text-decoration:none;color:inherit">
                <strong>EXCEPTION</strong><span class="pr-muted">On hold</span><div>{{ $exceptionBin }} parcels</div>
            </a>
        @endif
    </div>
    <p class="pr-muted" style="margin-top:.75rem">When a bin is full or the cutoff passes, start an <a href="{{ route('pickrush.province.trips.index') }}">outbound trip</a> for it.</p>
</section>
@endsection
