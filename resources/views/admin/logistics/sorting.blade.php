@extends('admin.layout')
@section('title', 'Sorting Center')
@section('styles')
@vite('resources/css/views/admin-oversight.css')
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <span class="card-title">Sorting center queue</span>
        <form method="GET" class="oversight-filters">
            <select name="status" class="filter-select" data-submit-on-change aria-label="Filter by sorting stage">
                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All sorting stages</option>
                @foreach($statuses as $option)
                    <option value="{{ $option }}" {{ $status === $option ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
                @endforeach
            </select>
            <label><input type="checkbox" name="stale" value="1" {{ $staleOnly ? 'checked' : '' }} data-submit-on-change> Idle over {{ $staleAfterHours }}h only</label>
        </form>
    </div>
    @include('admin.logistics._tabs')

    @if($parcels->isEmpty())
        <div class="oversight-empty"><strong>Nothing in sorting</strong>{{ $staleOnly ? 'No parcel has been idle longer than ' . $staleAfterHours . ' hours.' : 'Parcels waiting for pickup or sorting appear here.' }}</div>
    @else
        <div class="oversight-table-wrap">
            <table>
                <thead><tr><th>Parcel</th><th>Seller</th><th>Route</th><th>Stage</th><th>In stage</th></tr></thead>
                <tbody>
                @foreach($parcels as $parcel)
                    @php $stale = $parcel->updated_at->lt(now()->subHours($staleAfterHours)); @endphp
                    <tr class="{{ $stale ? 'is-stale' : '' }}">
                        <td><strong>{{ $parcel->order_number }}</strong><span class="oversight-meta">{{ $parcel->waybill_number ?? 'No waybill yet' }}</span></td>
                        <td>{{ $parcel->seller?->business_name ?? $parcel->seller?->full_name ?? '—' }}</td>
                        <td>{{ $parcel->originBranch?->name ?? 'Origin not set' }} → {{ $parcel->destinationBranch?->name ?? 'Destination not set' }}</td>
                        <td><x-admin-order-status :status="$parcel->status" /></td>
                        <td>
                            <span class="oversight-number">{{ $parcel->updated_at->diffForHumans(null, true) }}</span>
                            @if($stale)
                                <span class="oversight-flag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 8v4l2.5 2.5M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z"/></svg> Idle</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($parcels->hasPages())<div class="dashboard-pagination">{{ $parcels->links() }}</div>@endif
    @endif
</div>
@endsection
