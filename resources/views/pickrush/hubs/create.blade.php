@extends('pickrush.layouts.app')
@section('title', 'Open a hub')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Open a hub</h1>
        <p>Open a province hub for each province you serve, then a station hub for each city or municipality you deliver in. A station sorts through its province hub, and buyers can choose {{ $portalCompanyName }} for a route only when both ends have an active station.</p>
    </div>
    <a href="{{ route('pickrush.hubs.index') }}" class="pr-btn pr-btn-outline">Back to hubs</a>
</div>

<section class="pr-card pr-section">
    <h2>Province hub</h2>
    <p class="pr-muted">The sorting center for one province. Each Metro Manila district counts as a province.</p>
    @if($provinces->isEmpty())
        <p class="pr-muted">Every province already has one of your province hubs.</p>
    @else
        <form method="POST" action="{{ route('pickrush.hubs.store') }}" class="pr-form-grid">
            @csrf
            <input type="hidden" name="type" value="province">
            <div class="pr-field"><label for="province_code">Province</label>
                <select id="province_code" name="province_code" class="pr-select" required data-center-pin="province-pin" data-center-zoom="10">
                    <option value="">Pick a province…</option>
                    @foreach($provinces as $province)
                        <option value="{{ $province->code }}" data-place="{{ $province->isDistrict() ? 'Metro Manila' : $province->name }}" @selected(old('type') === 'province' && old('province_code') === $province->code)>{{ $province->isDistrict() ? 'Metro Manila – '.$province->name : $province->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pr-field"><label for="province_name">Name <small class="pr-muted">(optional)</small></label>
                <input id="province_name" name="name" class="pr-input" maxlength="120" placeholder="e.g. Batangas Province Hub" value="{{ old('type') === 'province' ? old('name') : '' }}"></div>
            <div class="pr-field"><label for="province_address">Address <small class="pr-muted">(optional)</small></label>
                <input id="province_address" name="address" class="pr-input" maxlength="255" value="{{ old('type') === 'province' ? old('address') : '' }}"></div>
            <div class="pr-field" style="grid-column:1/-1"><label>Location on the map <small class="pr-muted">(optional)</small></label>
                @include('partials.map-pin', ['id' => 'province-pin', 'lat' => old('type') === 'province' ? old('lat') : null, 'lng' => old('type') === 'province' ? old('lng') : null])
                <small class="pr-muted">Pick the province, then click the hub's entrance to drop the pin.</small></div>
            <div class="pr-field" style="justify-content:flex-end"><button type="submit" class="pr-btn pr-btn-primary">Open province hub</button></div>
        </form>
    @endif
</section>

<section class="pr-card pr-section">
    <h2>Station hub</h2>
    <p class="pr-muted">The last-mile hub for one city or municipality: riders pick up and deliver from here.</p>
    @if($stationChoices->isEmpty())
        <p class="pr-muted">Open a province hub first; its cities and municipalities then appear here.</p>
    @elseif($stationChoices->flatten()->isEmpty())
        <p class="pr-muted">Every city and municipality under your province hubs already has a station.</p>
    @else
        <form method="POST" action="{{ route('pickrush.hubs.store') }}" class="pr-form-grid">
            @csrf
            <input type="hidden" name="type" value="station">
            <div class="pr-field"><label for="municipality_code">City / municipality</label>
                <select id="municipality_code" name="municipality_code" class="pr-select" required data-center-pin="station-pin" data-center-zoom="14">
                    <option value="">Pick a city or municipality…</option>
                    @foreach($stationChoices as $provinceHub => $municipalities)
                        @if($municipalities->isNotEmpty())
                            <optgroup label="{{ $provinceHub }}">
                                @foreach($municipalities as $municipality)
                                    <option value="{{ $municipality->code }}" data-place="{{ $municipality->name }}, {{ $municipality->province?->isDistrict() ? 'Metro Manila' : $municipality->province?->name }}" @selected(old('type') === 'station' && old('municipality_code') === $municipality->code)>{{ $municipality->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="pr-field"><label for="station_name">Name <small class="pr-muted">(optional)</small></label>
                <input id="station_name" name="name" class="pr-input" maxlength="120" placeholder="e.g. Tanauan City Station" value="{{ old('type') === 'station' ? old('name') : '' }}"></div>
            <div class="pr-field"><label for="station_address">Address <small class="pr-muted">(optional)</small></label>
                <input id="station_address" name="address" class="pr-input" maxlength="255" value="{{ old('type') === 'station' ? old('address') : '' }}"></div>
            <div class="pr-field" style="grid-column:1/-1"><label>Location on the map <small class="pr-muted">(optional)</small></label>
                @include('partials.map-pin', ['id' => 'station-pin', 'lat' => old('type') === 'station' ? old('lat') : null, 'lng' => old('type') === 'station' ? old('lng') : null])
                <small class="pr-muted">Pick the city, then click the station's entrance to drop the pin. The hub manager can fine-tune it later.</small></div>
            <div class="pr-field" style="justify-content:flex-end"><button type="submit" class="pr-btn pr-btn-primary">Open station hub</button></div>
        </form>
    @endif
</section>
@include('pickrush.partials.map-centering')
@endsection
