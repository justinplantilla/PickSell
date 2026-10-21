@extends('pickrush.layouts.app')
@section('title', 'Addresses to fix')

@section('content')
<div class="pr-page-head">
    <div>
        <h1>Addresses to fix</h1>
        <p>These parcels' addresses matched no station. Confirm the address with the buyer or seller, pick the right barangay, and the parcel routes again.</p>
    </div>
</div>

<div class="pr-table-wrap pr-section">
    <table class="pr-table">
        <thead><tr><th>Tracking no.</th><th>Problem</th><th>Contact</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($parcels as $parcel)
            <tr @if($selected === $parcel->tracking_no) style="background:#fff7ed" @endif>
                <td><a href="{{ route('pickrush.parcels.show', $parcel) }}">{{ $parcel->tracking_no }}</a><div class="pr-muted">{{ $parcel->created_at->diffForHumans() }}</div></td>
                <td>
                    @unless($parcel->origin_hub_id)<div><strong>Pickup:</strong> {{ $parcel->pickup_address ?? '—' }}</div>@endunless
                    @unless($parcel->dest_hub_id)<div><strong>Delivery:</strong> {{ $parcel->delivery_address ?? '—' }}</div>@endunless
                </td>
                <td>{{ $parcel->dest_hub_id ? $parcel->shipper_name.' · '.$parcel->shipper_mobile : $parcel->consignee_name.' · '.$parcel->consignee_mobile }}</td>
                <td>{{ $parcel->current_status->label() }}@if($parcel->currentHub)<div class="pr-muted">{{ $parcel->currentHub->name }}</div>@endif</td>
                <td><a href="{{ route('pickrush.hq.address-fix', ['parcel' => $parcel->tracking_no]) }}" class="pr-btn pr-btn-outline pr-btn-sm">Fix</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="pr-muted">Every parcel's address matches a station.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pr-pagination">{{ $parcels->links() }}</div>

@if($selected)
    <section class="pr-card" id="fix">
        <h2>Fix {{ $selected }}</h2>
        <form method="GET" action="{{ route('pickrush.hq.address-fix') }}#fix" class="pr-form-grid">
            <input type="hidden" name="parcel" value="{{ $selected }}">
            <div class="pr-field"><label for="province">Province / district</label>
                <select id="province" name="province" class="pr-select" onchange="this.form.submit()">
                    <option value="">Choose…</option>
                    @foreach($provinces as $p)<option value="{{ $p->code }}" @selected($province === $p->code)>{{ $p->isDistrict() ? 'Metro Manila – '.$p->name : $p->name }}</option>@endforeach
                </select></div>
            <div class="pr-field"><label for="municipality">City / municipality</label>
                <select id="municipality" name="municipality" class="pr-select" onchange="this.form.submit()" @disabled($municipalities->isEmpty())>
                    <option value="">Choose…</option>
                    @foreach($municipalities as $m)<option value="{{ $m->code }}" @selected($municipality === $m->code)>{{ $m->name }}</option>@endforeach
                </select></div>
        </form>
        @if($barangays->isNotEmpty())
            <form method="POST" action="{{ route('pickrush.hq.address-fix.update', $selected) }}" class="pr-form-grid" style="margin-top:1rem">
                @csrf @method('PUT')
                <div class="pr-field"><label for="side">Which address</label>
                    <select id="side" name="side" class="pr-select"><option value="delivery">Delivery (buyer)</option><option value="pickup">Pickup (seller)</option></select></div>
                <div class="pr-field"><label for="barangay_code">Barangay</label>
                    <select id="barangay_code" name="barangay_code" class="pr-select" required>@foreach($barangays as $b)<option value="{{ $b->code }}">{{ $b->name }}</option>@endforeach</select></div>
                <div class="pr-field" style="grid-column:1/-1"><label for="address_line">Full address (house, street)</label><input id="address_line" name="address_line" class="pr-input" placeholder="As confirmed with the customer"></div>
                <div class="pr-field"><button type="submit" class="pr-btn pr-btn-primary">Save address and reroute</button></div>
            </form>
        @endif
    </section>
@endif
@endsection
