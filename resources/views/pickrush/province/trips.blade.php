@extends('pickrush.layouts.app')
@section('title', 'Outbound trips')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Outbound trips</h1>
        <p>One trip per destination: down to a station in this province, or across to another province hub.</p>
    </div>
    <form method="POST" action="{{ route('pickrush.province.trips.store') }}" class="pr-inline">
        @csrf
        <select name="to_hub_id" class="pr-select" required aria-label="Destination">
            <option value="">Destination…</option>
            <optgroup label="Stations">@foreach($allDestinations->where('type', \App\Pickrush\Enums\HubType::Station) as $to)<option value="{{ $to->id }}">{{ $to->name }}</option>@endforeach</optgroup>
            <optgroup label="Other province hubs">@foreach($allDestinations->where('type', \App\Pickrush\Enums\HubType::Province) as $to)<option value="{{ $to->id }}">{{ $to->name }}</option>@endforeach</optgroup>
        </select>
        <button type="submit" class="pr-btn pr-btn-primary">Start trip</button>
    </form>
</div>

@if($drafts->isNotEmpty())
    <div class="pr-alert pr-alert-warn">
        <strong>Being loaded:</strong>
        @foreach($drafts as $draft)
            <a href="{{ route('pickrush.province.trips.show', $draft) }}">{{ $draft->manifest_no }} → {{ $draft->toHub->name }}</a> ({{ $draft->parcel_count }})@if(! $loop->last), @endif
        @endforeach
    </div>
@endif

<section class="pr-section">
    <h2>Ready to leave, by destination</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Bin</th><th>Destination</th><th class="pr-num">Ready</th><th class="pr-num">Sorted</th><th></th></tr></thead>
            <tbody>
            @forelse($destinations as $row)
                <tr>
                    <td><strong>{{ $row->bin }}</strong></td>
                    <td>{{ $row->hub->name }}@if($row->hub->isProvince()) <span class="pr-badge pr-badge-brand">Inter-province</span>@endif</td>
                    <td class="pr-num">{{ $row->ready }}</td>
                    <td class="pr-num">{{ $row->sorted }}</td>
                    <td>
                        <form method="POST" action="{{ route('pickrush.province.trips.store') }}">@csrf
                            <input type="hidden" name="to_hub_id" value="{{ $row->hub->id }}">
                            <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm">Start trip</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="pr-muted">Nothing waiting to leave.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="pr-section">
    <h2>Recent trips</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Trip</th><th>Route</th><th class="pr-num">Parcels</th><th>Status</th><th>Updated</th></tr></thead>
            <tbody>
            @forelse($recent as $trip)
                <tr>
                    <td><a href="{{ route('pickrush.province.trips.show', $trip) }}">{{ $trip->manifest_no }}</a></td>
                    <td>{{ $trip->fromHub->name }} → {{ $trip->toHub->name }}</td>
                    <td class="pr-num">{{ $trip->parcel_count }}</td>
                    <td><span class="pr-badge">{{ ucfirst($trip->status) }}</span></td>
                    <td>{{ $trip->updated_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="pr-muted">No trips yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
