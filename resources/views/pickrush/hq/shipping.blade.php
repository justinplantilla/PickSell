@extends('pickrush.layouts.app')
@section('title', 'Marketplace shipping')

@section('content')
@php
    $peso = fn ($n) => '₱'.number_format((float) $n, 2);
    $kg = fn ($n) => rtrim(rtrim((string) $n, '0'), '.');
@endphp
<div class="pr-page-head">
    <div>
        <h1>Marketplace shipping</h1>
        <p>What PickSell buyers pay Pickrush to ship, by the seller's region and the buyer's region, and what riders earn per parcel class. A price is never edited in place: changes start on a date and the old price ends the day before, so placed orders keep their price.{{ $canEdit ? '' : ' Only a super admin can change these.' }}</p>
    </div>
</div>

<section class="pr-section">
    <h2>Logistics earnings from delivered marketplace parcels</h2>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>Period</th><th class="pr-num">Parcels</th><th class="pr-num">Delivery fees</th><th class="pr-num">Rider pay</th><th class="pr-num">PickSell commission</th><th class="pr-num">Logistics share</th></tr></thead>
            <tbody>
                @foreach(['This month' => $earnings['month'], 'All time' => $earnings['all']] as $label => $e)
                    <tr><td>{{ $label }}</td><td class="pr-num">{{ (int) $e->parcels }}</td><td class="pr-num">{{ $peso($e->fees) }}</td><td class="pr-num">{{ $peso($e->rider) }}</td><td class="pr-num">{{ $peso($e->picksell) }}</td><td class="pr-num"><strong>{{ $peso($e->logistics) }}</strong></td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <small class="pr-muted">Pickup riders' ₱{{ number_format((float) config('pickrush.rider_pay.pickup_per_parcel'), 2) }} per parcel is paid out of the logistics share. Failed deliveries earn no one anything.</small>
</section>

<section class="pr-card pr-section">
    <h2>Priced without the seller's region</h2>
    <p>
        <span class="pr-badge {{ $fallback['month'] ? 'pr-badge-warn' : '' }}">{{ $fallback['month'] }} this month</span>
        <span class="pr-badge">{{ $fallback['all'] }} of {{ $fallback['parcels'] }} marketplace parcels in all</span>
    </p>
    <p class="pr-muted">These used the generic (Metro Manila-origin) price because the seller's province matched no region, or their route had no price of its own.</p>
    @if($fallback['sellers']->isNotEmpty())
        <p><strong>{{ $fallback['sellers']->count() }} approved seller(s) without a matching province:</strong>
            {{ $fallback['sellers']->take(15)->map(fn ($s) => ($s->business_name ?: trim($s->first_name.' '.$s->last_name)).' ('.($s->province ?: 'no province').')')->join(', ') }}{{ $fallback['sellers']->count() > 15 ? ', …' : '' }}</p>
        <small class="pr-muted">They are asked to complete their address in the seller portal. Or map their province below.</small>
    @endif
</section>

<section class="pr-card pr-section">
    <h2>Route levels</h2>
    <p class="pr-muted">How far each route is. Rows are the seller's region, columns the buyer's. A route costs the same both ways. The NCR row is also the generic price used when a seller's region is unknown. These levels are a proposal for non-NCR sellers: review them against real costs.</p>
    <form method="POST" action="{{ route('pickrush.hq.shipping.route-levels.update') }}">
        @csrf @method('PUT')
        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead><tr><th>From \ To</th>@foreach($regions as $code => $name)<th>{{ $code }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach($regions as $origin => $name)
                    <tr>
                        <th>{{ $origin }}</th>
                        @foreach($regions as $destination => $_)
                            <td>
                                <select name="levels[{{ $origin }}][{{ $destination }}]" class="pr-select" aria-label="{{ $origin }} to {{ $destination }}" @disabled(! $canEdit)>
                                    @foreach($levels as $level => $prices)
                                        <option value="{{ $level }}" @selected((int) old("levels.$origin.$destination", $routeLevels[$origin][$destination] ?? -1) === $level)>{{ $level }}</option>
                                    @endforeach
                                </select>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @error('levels')<span class="pr-field-error">{{ $message }}</span>@enderror
        @if($canEdit)
            <div class="pr-form-grid">
                <div class="pr-field"><label for="levels-from">New prices from</label><input id="levels-from" name="effective_from" type="date" class="pr-input" value="{{ $today }}" min="{{ $today }}" required></div>
                <div class="pr-field"><button type="submit" class="pr-btn pr-btn-primary">Save route levels</button></div>
            </div>
        @endif
    </form>
</section>

<section class="pr-card pr-section">
    <h2>Price levels</h2>
    <p class="pr-muted">A parcel pays the smallest tier at or above its weight; above the heaviest tier, that tier's fee plus each started kg × the per-kg rate. Prices must rise with weight and stay above the ₱{{ number_format($minRiderPay, 2) }} minimum rider pay.</p>
    <form method="POST" action="{{ route('pickrush.hq.shipping.price-levels.update') }}">
        @csrf @method('PUT')
        <div class="pr-table-scroll">
            <table class="pr-table">
                <thead><tr><th>Level</th>@foreach($tiers as $t)<th class="pr-num">≤ {{ $kg($t) }} kg</th>@endforeach<th class="pr-num">Per kg above</th></tr></thead>
                <tbody>
                @foreach($levels as $level => $prices)
                    <tr>
                        <th>{{ $level }} · {{ $prices['name'] }}</th>
                        @foreach($prices['fees'] as $t => $fee)
                            <td><input name="prices[{{ $level }}][fees][{{ str_replace('.', '_', $t) }}]" type="number" step="0.01" min="0.01" class="pr-input" value="{{ $fee }}" aria-label="Level {{ $level }} up to {{ $kg($t) }} kg" @disabled(! $canEdit) required></td>
                        @endforeach
                        <td><input name="prices[{{ $level }}][per_kg]" type="number" step="0.01" min="0.01" class="pr-input" value="{{ $prices['per_kg'] }}" aria-label="Level {{ $level }} per kg" @disabled(! $canEdit) required></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @error('prices')<span class="pr-field-error">{{ $message }}</span>@enderror
        @if($canEdit)
            <div class="pr-form-grid">
                <div class="pr-field"><label for="prices-from">New prices from</label><input id="prices-from" name="effective_from" type="date" class="pr-input" value="{{ $today }}" min="{{ $today }}" required></div>
                <div class="pr-field"><button type="submit" class="pr-btn pr-btn-primary">Save price levels</button></div>
            </div>
        @endif
    </form>
</section>

<section class="pr-section">
    <h2>Prices today, up to {{ $tier ? $kg($tier) : '' }} kg</h2>
    <form method="GET" class="pr-form-grid">
        <div class="pr-field"><label for="tier">Weight tier</label>
            <select id="tier" name="tier" class="pr-select" onchange="this.form.submit()">@foreach($tiers as $t)<option value="{{ $t }}" @selected($t === $tier)>≤ {{ $kg($t) }} kg</option>@endforeach</select></div>
        <input type="hidden" name="from" value="{{ $from }}">
    </form>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>From \ To</th>@foreach($regions as $code => $name)<th class="pr-num">{{ $code }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach(array_merge(array_keys($regions->all()), ['GENERIC']) as $origin)
                    <tr>
                        <th>{{ $origin === 'GENERIC' ? 'Unknown (generic)' : $origin }}</th>
                        @foreach($regions as $destination => $_)
                            @php $fee = $priceGrid[$origin][$destination] ?? null; @endphp
                            <td class="pr-num">{!! $fee !== null ? e($peso($fee)) : '<span class="pr-muted" title="Uses the generic price">generic</span>' !!}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<section class="pr-card pr-section">
    <h2>Parcel classes</h2>
    <p class="pr-muted">By the parcel's weight. Rider pay is what the delivery rider earns when the parcel is delivered; it cannot be below ₱{{ number_format($minRiderPay, 2) }}.</p>
    @foreach($classes as $class)
        <form method="POST" action="{{ route('pickrush.hq.shipping.classes.update', $class) }}" class="pr-form-grid">
            @csrf @method('PUT')
            <div class="pr-field"><label>{{ $class->code }}: above (kg)</label><input name="min_kg_exclusive" type="number" step="0.01" class="pr-input" value="{{ $class->min_kg_exclusive }}" @disabled(! $canEdit) required></div>
            <div class="pr-field"><label>up to and including (kg)</label><input name="max_kg_inclusive" type="number" step="0.01" class="pr-input" value="{{ $class->max_kg_inclusive }}" @disabled(! $canEdit) required></div>
            <div class="pr-field"><label>Rider pay (₱)</label><input name="rider_pay" type="number" step="0.01" min="{{ $minRiderPay }}" class="pr-input" value="{{ $class->rider_pay }}" @disabled(! $canEdit) required></div>
            <div class="pr-field"><label><input type="checkbox" name="is_active" value="1" @checked($class->is_active) @disabled(! $canEdit)> Active</label>
                @if($canEdit)<button type="submit" class="pr-btn pr-btn-outline pr-btn-sm">Save {{ $class->code }}</button>@endif
                @error("class_{$class->code}")<span class="pr-field-error">{{ $message }}</span>@enderror
            </div>
        </form>
    @endforeach
    <small class="pr-muted">Heavier than {{ (float) config('pickrush.shipping.max_priced_weight_kg') }} kg (Rates &amp; policies): the buyer is told shipping will be quoted manually.</small>
</section>

<section class="pr-section">
    <h2>Rate rows</h2>
    <form method="GET" class="pr-form-grid">
        <div class="pr-field"><label for="from">From region</label>
            <select id="from" name="from" class="pr-select" onchange="this.form.submit()">
                <option value="GENERIC" @selected($from === 'GENERIC')>Generic (any seller)</option>
                @foreach($regions as $code => $name)<option value="{{ $code }}" @selected($from === $code)>{{ $code }}</option>@endforeach
                <option value="ALL" @selected($from === 'ALL')>All</option>
            </select></div>
        <input type="hidden" name="tier" value="{{ $tier }}">
    </form>
    <div class="pr-table-scroll">
        <table class="pr-table">
            <thead><tr><th>From</th><th>To</th><th class="pr-num">Up to</th><th class="pr-num">Fee</th><th>Valid</th><th></th></tr></thead>
            <tbody>
            @foreach($rates as $rate)
                @php $ended = $rate->effective_to && $rate->effective_to->toDateString() < $today; @endphp
                <tr @class(['pr-muted' => $ended])>
                    <td>{{ $rate->origin_region ?? 'Any' }}</td><td>{{ $rate->destination_region }}</td>
                    <td class="pr-num">{{ $kg($rate->max_weight_kg) }} kg</td><td class="pr-num">{{ $peso($rate->fee) }}</td>
                    <td>{{ $rate->effective_from->toDateString() }} → {{ $rate->effective_to?->toDateString() ?? 'open' }}</td>
                    <td>
                        @if($canEdit && ! $ended)
                            <form method="POST" action="{{ route('pickrush.hq.shipping.rates.end', $rate) }}">
                                @csrf @method('PUT')
                                <input type="date" name="effective_to" class="pr-input" value="{{ $today }}" aria-label="Last day">
                                <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm">End</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($canEdit)
        <p class="pr-muted">To change many routes at once, edit the route levels or price levels above. Add a single row here only for an exception.</p>
        <form method="POST" action="{{ route('pickrush.hq.shipping.rates.store') }}" class="pr-card pr-form-grid">
            @csrf
            <div class="pr-field"><label>From region</label><select name="origin_region" class="pr-select"><option value="">Any (generic)</option>@foreach($regions as $code => $name)<option value="{{ $code }}">{{ $code }}</option>@endforeach</select></div>
            <div class="pr-field"><label>To region</label><select name="destination_region" class="pr-select">@foreach($regions as $code => $name)<option value="{{ $code }}">{{ $code }}</option>@endforeach</select></div>
            <div class="pr-field"><label>Up to (kg)</label><input name="max_weight_kg" type="number" step="0.01" class="pr-input" required></div>
            <div class="pr-field"><label>Fee (₱)</label><input name="fee" type="number" step="0.01" class="pr-input" required></div>
            <div class="pr-field"><label>From date</label><input name="effective_from" type="date" class="pr-input" value="{{ $today }}" required>
                @error('effective_from')<span class="pr-field-error">{{ $message }}</span>@enderror</div>
            <div class="pr-field"><button type="submit" class="pr-btn pr-btn-primary">Add tier fee</button></div>
        </form>
    @endif

    <h3>Above the heaviest tier</h3>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr><th>From</th><th>To</th><th class="pr-num">Above</th><th class="pr-num">Per kg</th><th>Valid</th><th></th></tr></thead>
            <tbody>
            @foreach($overweight as $rate)
                @php $ended = $rate->effective_to && $rate->effective_to->toDateString() < $today; @endphp
                <tr @class(['pr-muted' => $ended])>
                    <td>{{ $rate->origin_region ?? 'Any' }}</td><td>{{ $rate->destination_region }}</td><td class="pr-num">{{ $kg($rate->over_kg) }} kg</td><td class="pr-num">{{ $peso($rate->per_kg_fee) }}</td>
                    <td>{{ $rate->effective_from->toDateString() }} → {{ $rate->effective_to?->toDateString() ?? 'open' }}</td>
                    <td>
                        @if($canEdit && ! $ended)
                            <form method="POST" action="{{ route('pickrush.hq.shipping.overweight.end', $rate) }}">
                                @csrf @method('PUT')
                                <input type="date" name="effective_to" class="pr-input" value="{{ $today }}" aria-label="Last day">
                                <button type="submit" class="pr-btn pr-btn-outline pr-btn-sm">End</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($canEdit)
        <form method="POST" action="{{ route('pickrush.hq.shipping.overweight.store') }}" class="pr-card pr-form-grid">
            @csrf
            <div class="pr-field"><label>From region</label><select name="origin_region" class="pr-select"><option value="">Any (generic)</option>@foreach($regions as $code => $name)<option value="{{ $code }}">{{ $code }}</option>@endforeach</select></div>
            <div class="pr-field"><label>To region</label><select name="destination_region" class="pr-select">@foreach($regions as $code => $name)<option value="{{ $code }}">{{ $code }}</option>@endforeach</select></div>
            <div class="pr-field"><label>Above (kg)</label><input name="over_kg" type="number" step="0.01" class="pr-input" value="10" required></div>
            <div class="pr-field"><label>Per kg (₱)</label><input name="per_kg_fee" type="number" step="0.01" class="pr-input" required></div>
            <div class="pr-field"><label>From date</label><input name="effective_from" type="date" class="pr-input" value="{{ $today }}" required></div>
            <div class="pr-field"><button type="submit" class="pr-btn pr-btn-primary">Add per-kg rate</button></div>
        </form>
    @endif
</section>

<section class="pr-card pr-section">
    <h2>Provinces by region</h2>
    <p class="pr-muted">The buyer's province decides the destination and the seller's the origin (names match ignoring case and extra spaces). Metro Manila cities and the address picker's district names are listed so city-only addresses still resolve.</p>
    @foreach($regions as $code => $name)
        <details>
            <summary><strong>{{ $code }}</strong> · {{ $name }} ({{ ($provinces[$code] ?? collect())->count() }})</summary>
            <p>
                @foreach($provinces[$code] ?? [] as $row)
                    <span class="pr-badge">{{ $row->name }}
                        @if($canEdit)
                            <form method="POST" action="{{ route('pickrush.hq.shipping.provinces.destroy') }}" style="display:inline">
                                @csrf @method('DELETE')
                                <input type="hidden" name="name" value="{{ $row->name }}">
                                <button type="submit" class="pr-btn pr-btn-sm" aria-label="Remove {{ $row->name }}">×</button>
                            </form>
                        @endif
                    </span>
                @endforeach
            </p>
        </details>
    @endforeach
    @if($canEdit)
        <form method="POST" action="{{ route('pickrush.hq.shipping.provinces.store') }}" class="pr-form-grid">
            @csrf
            <div class="pr-field"><label>Province, city or alias</label><input name="name" class="pr-input" maxlength="100" required></div>
            <div class="pr-field"><label>Region</label><select name="region_code" class="pr-select">@foreach($regions as $code => $name)<option value="{{ $code }}">{{ $code }}</option>@endforeach</select></div>
            <div class="pr-field"><button type="submit" class="pr-btn pr-btn-primary">Add or move</button></div>
        </form>
    @endif
</section>
@endsection
