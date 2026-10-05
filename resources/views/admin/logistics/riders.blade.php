@extends('admin.layout')
@section('title', 'Rider Assignment')
@section('styles')
@vite('resources/css/views/admin-oversight.css')
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <span class="card-title">Rider assignments</span>
        <form method="GET" class="oversight-filters">
            <select name="status" class="filter-select" data-submit-on-change aria-label="Filter by delivery stage">
                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All rider stages</option>
                @foreach($statuses as $option)
                    <option value="{{ $option }}" {{ $status === $option ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
                @endforeach
            </select>
        </form>
    </div>
    @include('admin.logistics._tabs')
    @if($awaitingRider > 0)
        <p class="oversight-description oversight-note"><span class="oversight-flag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg> {{ number_format($awaitingRider) }} sorted {{ $awaitingRider === 1 ? 'parcel is' : 'parcels are' }} waiting for a rider.</span></p>
    @endif

    @if($parcels->isEmpty())
        <div class="oversight-empty"><strong>No parcels with riders</strong>Assigned and out-for-delivery parcels appear here.</div>
    @else
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>Parcel</th><th>Rider</th><th>Destination</th><th>Stage</th><th>Updated</th></tr></thead>
                <tbody>
                @foreach($parcels as $parcel)
                    <tr>
                        <td><strong>{{ $parcel->order_number }}</strong><span class="oversight-meta">{{ $parcel->waybill_number ?? 'No waybill' }}</span></td>
                        <td>{{ $parcel->courier?->full_name ?? 'Unassigned' }}</td>
                        <td>{{ $parcel->destinationBarangay?->name ?? '—' }}<span class="oversight-meta">{{ $parcel->destinationBranch?->name ?? 'Branch not set' }}</span></td>
                        <td><x-admin-order-status :status="$parcel->status" />@if($parcel->tracking_status)<span class="oversight-meta">{{ $parcel->tracking_status }}</span>@endif</td>
                        <td><time datetime="{{ $parcel->updated_at->toIso8601String() }}">{{ $parcel->updated_at->diffForHumans() }}</time></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($parcels->hasPages())<div class="dashboard-pagination">{{ $parcels->links() }}</div>@endif
    @endif
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Active rider roster</span></div>
    @if($roster->isEmpty())
        <div class="oversight-empty"><strong>No active riders</strong>Riders assigned to branches in the Logistics portal appear here.</div>
    @else
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>Rider</th><th>Branch</th><th>Active parcels</th></tr></thead>
                <tbody>
                @foreach($roster as $assignment)
                    <tr>
                        <td>{{ $assignment->rider?->full_name ?? '—' }}<span class="oversight-meta">{{ $assignment->rider?->contact_no }}</span></td>
                        <td>{{ $assignment->branch?->name ?? '—' }}</td>
                        <td class="oversight-number">{{ $activeLoad[$assignment->user_id] ?? 0 }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
