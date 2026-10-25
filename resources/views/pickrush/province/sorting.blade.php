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
        <span class="pr-bin-icon" aria-hidden="true">
            @if($result['error'])
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            @else
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
            @endif
        </span>
        <div class="pr-bin-main">
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
            @php($binPct = $bin->total > 0 ? min(100, round($bin->sorted / $bin->total * 100)) : 0)
            <div @class(['pr-bin-tile', 'is-complete' => $bin->total > 0 && $bin->sorted >= $bin->total])>
                <strong>{{ $bin->bin }}</strong>
                <span class="pr-muted">{{ $bin->hub?->name }}</span>
                <div>{{ $bin->sorted }} sorted of {{ $bin->total }}</div>
                <span class="pr-meter" aria-hidden="true"><span style="width: {{ $binPct }}%"></span></span>
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
