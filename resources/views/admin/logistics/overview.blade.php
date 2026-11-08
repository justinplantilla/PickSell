@extends('admin.layout')
@section('title', 'Logistics')
@section('styles')
@vite(['resources/css/views/admin-oversight.css', 'resources/css/views/admin-list.css'])
@endsection

@section('content')
<p class="oversight-description">Logistics companies deliver PickSell orders. They register on the logistics portal and run their hubs, riders and parcel exceptions there.</p>

<section class="card" aria-labelledby="registrations-heading">
    <div class="card-header">
        <div>
            <span class="card-title" id="registrations-heading">Company registrations</span>
            <p class="oversight-description">Logistics companies that registered on the logistics portal. Review each one before it can deliver PickSell orders.</p>
        </div>
        <a class="btn btn-coral btn-sm" href="{{ route('admin.logistics.companies') }}">Review registrations</a>
    </div>
    <div class="oversight-pipeline">
        @foreach(['pending' => 'Waiting for review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'suspended' => 'Suspended'] as $status => $label)
            <a class="oversight-stage {{ $status === 'pending' && ($registrationCounts[$status] ?? 0) > 0 ? 'is-alert' : '' }}" href="{{ route('admin.logistics.companies', $status === 'pending' ? [] : ['status' => $status]) }}">
                <strong>{{ number_format($registrationCounts[$status] ?? 0) }}</strong><span>{{ $label }}</span>
            </a>
        @endforeach
    </div>
    @if($pendingCompanies->isEmpty())
        <div class="oversight-empty"><strong>No registration is waiting</strong>New logistics companies appear here as soon as they submit.</div>
    @else
        <div class="oversight-table-wrap">
            <table class="list-table">
                <thead><tr><th>Waiting for review</th><th>Owner</th><th>Submitted</th><th class="list-chevron"><span class="sr-only">Open</span></th></tr></thead>
                <tbody>
                @foreach($pendingCompanies as $pending)
                    <tr class="list-row" data-row-href="{{ route('admin.logistics.companies.show', $pending) }}">
                        <td class="list-cell-main" data-label="Company"><a href="{{ route('admin.logistics.companies.show', $pending) }}" class="list-title">{{ $pending->name }}</a></td>
                        <td data-label="Owner">{{ $pending->owner?->full_name }}<span class="oversight-meta">{{ $pending->owner?->email }}</span></td>
                        <td data-label="Submitted"><time datetime="{{ $pending->submitted_at?->toIso8601String() }}">{{ $pending->submitted_at?->diffForHumans() }}</time></td>
                        <td class="list-chevron" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if(($registrationCounts['pending'] ?? 0) > $pendingCompanies->count())
            <p class="oversight-description"><a href="{{ route('admin.logistics.companies') }}">See all {{ number_format($registrationCounts['pending']) }} waiting registrations</a></p>
        @endif
    @endif
</section>

<section class="card">
    <div class="card-header">
        <span class="card-title">Logistics companies</span>
        <span>
            <a class="btn btn-outline" href="{{ $portalUrl }}" target="_blank" rel="noopener">Open logistics portal</a>
        </span>
    </div>
    <div class="oversight-table-wrap">
        <table>
            <thead><tr><th>Company</th><th>Province hubs</th><th>Station hubs</th><th>Staff</th><th>Active riders</th><th>Status</th></tr></thead>
            <tbody>
                @foreach($companies as $row)
                    <tr>
                        <td>
                            <a href="{{ route('admin.logistics.companies.show', $row['model']) }}"><strong>{{ $row['model']->name }}</strong></a>
                            <span class="oversight-meta">{{ $row['model']->is_default ? 'Default carrier · ' : '' }}Tracking prefix {{ $row['model']->tracking_prefix }}</span>
                        </td>
                        <td class="oversight-number">{{ number_format($row['province_hubs']) }}</td>
                        <td class="oversight-number">{{ number_format($row['station_hubs']) }}</td>
                        <td class="oversight-number">{{ number_format($row['staff']) }}</td>
                        <td class="oversight-number">{{ number_format($row['riders']) }}</td>
                        <td><span class="badge badge-{{ $row['model']->status }}">{{ $row['model']->status === 'approved' ? 'Active' : 'Suspended' }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<section class="card" aria-labelledby="money-heading">
    <div class="card-header">
        <div>
            <span class="card-title" id="money-heading">Money by company</span>
            <p class="oversight-description">Delivered PickSell parcels and their delivery-fee split, as saved at checkout. COD collected is cash riders took on delivery; confirmed is what the company's HQ has confirmed from its stations.</p>
        </div>
        <nav class="oversight-tabs" aria-label="Period">
            <a href="{{ route('admin.logistics.index') }}" class="oversight-tab {{ $period === 'month' ? 'is-active' : '' }}" @if($period === 'month') aria-current="page" @endif>This month</a>
            <a href="{{ route('admin.logistics.index', ['period' => 'all']) }}" class="oversight-tab {{ $period === 'all' ? 'is-active' : '' }}" @if($period === 'all') aria-current="page" @endif>All time</a>
        </nav>
    </div>
    @php($peso = fn ($n) => '₱'.number_format((float) $n, 2))
    <div class="oversight-table-wrap">
        <table>
            <thead><tr>
                <th>Company</th><th>Delivered</th><th>In progress</th><th>Delivery fees</th><th>Rider pay</th>
                <th>Company share</th><th>PickSell commission</th><th>COD collected</th><th>COD confirmed</th>
            </tr></thead>
            <tbody>
                @foreach($money as $row)
                    <tr>
                        <td><a href="{{ route('admin.logistics.companies.show', $row['company']) }}"><strong>{{ $row['company']->name }}</strong></a>
                            @if($row['company']->status !== 'approved')<span class="oversight-meta">{{ ucfirst($row['company']->status) }}</span>@endif</td>
                        <td class="oversight-number">{{ number_format($row['delivered']) }}</td>
                        <td class="oversight-number">{{ number_format($row['in_progress']) }}</td>
                        <td class="oversight-number">{{ $peso($row['fees']) }}</td>
                        <td class="oversight-number">{{ $peso($row['rider']) }}</td>
                        <td class="oversight-number"><strong>{{ $peso($row['logistics']) }}</strong></td>
                        <td class="oversight-number">{{ $peso($row['picksell']) }}</td>
                        <td class="oversight-number">{{ $peso($row['cod_collected']) }}</td>
                        <td class="oversight-number">{{ $peso($row['cod_confirmed']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr>
                <th>Total</th>
                <th class="oversight-number">{{ number_format($money->sum('delivered')) }}</th>
                <th class="oversight-number">{{ number_format($money->sum('in_progress')) }}</th>
                @foreach(['fees', 'rider', 'logistics', 'picksell', 'cod_collected', 'cod_confirmed'] as $column)
                    <th class="oversight-number">{{ $peso($money->sum($column)) }}</th>
                @endforeach
            </tr></tfoot>
        </table>
    </div>
</section>

<section class="card">
    <div class="card-header"><span class="card-title">PickSell parcels</span></div>
    <div class="oversight-pipeline">
        <div class="oversight-stage"><strong>{{ number_format($parcelCounts['in_transit']) }}</strong><span>In transit</span></div>
        <div class="oversight-stage"><strong>{{ number_format($parcelCounts['delivered']) }}</strong><span>Delivered</span></div>
        <div class="oversight-stage"><strong>{{ number_format($parcelCounts['failed']) }}</strong><span>Failed or returning</span></div>
        <div class="oversight-stage {{ $parcelCounts['lost'] > 0 ? 'is-alert' : '' }}"><strong>{{ number_format($parcelCounts['lost']) }}</strong><span>Lost</span></div>
    </div>
</section>

<section class="card">
    <div class="card-header"><span class="card-title">Open logistics exceptions ({{ number_format($openExceptionCount) }})</span></div>
    @if($openExceptions->isEmpty())
        <div class="oversight-empty"><strong>No open logistics exceptions</strong>Missing, damaged or mis-sorted parcels reported by logistics hubs are shown here.</div>
    @else
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>Parcel</th><th>Hub</th><th>Exception</th><th>Opened</th></tr></thead>
                <tbody>
                @foreach($openExceptions as $exception)
                    <tr>
                        <td><strong>{{ $exception->parcel?->tracking_no ?? 'No parcel' }}</strong><span class="oversight-meta">{{ $exception->parcel?->current_status?->label() }}</span></td>
                        <td>{{ $exception->hub?->name }}</td>
                        <td>{{ \App\Pickrush\Models\ParcelException::TYPES[$exception->type] ?? $exception->type }}<span class="oversight-meta">{{ \Illuminate\Support\Str::limit($exception->note, 90) }}</span></td>
                        <td><time datetime="{{ $exception->created_at->toIso8601String() }}">{{ $exception->created_at->diffForHumans() }}</time></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection

@section('scripts')
@vite('resources/js/components/admin-row-links.js')
@endsection
