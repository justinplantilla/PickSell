@extends('admin.layout')
@section('title', 'Logistics')
@section('styles')
@vite('resources/css/views/admin-oversight.css')
@endsection

@section('content')
<p class="oversight-description">Pickrush delivers every PickSell order. Hubs, riders and parcel exceptions are managed in the Pickrush portal.</p>

<section class="card">
    <div class="card-header">
        <span class="card-title">Logistics company</span>
        <a class="btn btn-outline" href="{{ $company['portal_url'] }}" target="_blank" rel="noopener">Open Pickrush portal</a>
    </div>
    <div class="oversight-table-wrap">
        <table>
            <thead><tr><th>Company</th><th>Province hubs</th><th>Station hubs</th><th>Staff</th><th>Active riders</th><th>Status</th></tr></thead>
            <tbody>
                <tr>
                    <td><strong>{{ $company['name'] }}</strong><span class="oversight-meta">{{ parse_url($company['portal_url'], PHP_URL_HOST) }}</span></td>
                    <td class="oversight-number">{{ number_format($company['province_hubs']) }}</td>
                    <td class="oversight-number">{{ number_format($company['station_hubs']) }}</td>
                    <td class="oversight-number">{{ number_format($company['staff']) }}</td>
                    <td class="oversight-number">{{ number_format($company['riders']) }}</td>
                    <td><span class="badge badge-approved">Active</span></td>
                </tr>
            </tbody>
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
        <div class="oversight-empty"><strong>No open logistics exceptions</strong>Missing, damaged or mis-sorted parcels reported by Pickrush hubs are shown here.</div>
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
